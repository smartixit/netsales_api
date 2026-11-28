<?php

namespace App\Services\Billing;

use App\Enums\BillingFeatureType;
use App\Models\BillingSubscription;
use App\Models\BillingUsageAggregate;
use App\Models\BillingUsageEvent;
use App\Models\BillingUsageMeter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OverflowException;

class BillingUsageService
{
    private const TIMEZONE = 'Asia/Aden';

    public function __construct(
        private BillingEntitlementService $entitlements,
        private BillingUsageAggregationService $aggregation,
        private BillingUsagePeriodResolver $periods
    ) {}

    public function record(
        int $subscriptionId,
        string $meterKey,
        string $quantity,
        string $idempotencyKey,
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $dimensions = []
    ): BillingUsageEvent {
        $quantityMicros = $this->toMicros($quantity);
        if ($quantityMicros <= 0) {
            throw ValidationException::withMessages(['quantity' => ['يجب أن تكون كمية الاستهلاك أكبر من صفر.']]);
        }

        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مطلوب ولا يتجاوز 128 حرفاً.']]);
        }

        return DB::transaction(function () use (
            $subscriptionId,
            $meterKey,
            $quantityMicros,
            $idempotencyKey,
            $sourceType,
            $sourceId,
            $dimensions
        ): BillingUsageEvent {
            $subscription = BillingSubscription::query()
                ->with('planVersion.features')
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            $meter = BillingUsageMeter::query()
                ->where('meter_key', $meterKey)
                ->where('is_active', true)
                ->first();

            if (! $meter) {
                throw ValidationException::withMessages(['meter_key' => ['عداد الاستخدام غير موجود أو غير نشط.']]);
            }

            $existing = BillingUsageEvent::query()
                ->with('meter')
                ->where('billing_subscription_id', $subscriptionId)
                ->where('meter_id', $meter->meter_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if ($this->toMicros((string) $existing->quantity) !== $quantityMicros) {
                    throw ValidationException::withMessages(['idempotency_key' => ['مفتاح الاستهلاك أعيد استخدامه بكمية مختلفة.']]);
                }

                return $existing;
            }

            $feature = $subscription->planVersion?->features->firstWhere('feature_key', $meterKey);
            if (! $feature || $feature->feature_type !== BillingFeatureType::METERED) {
                throw ValidationException::withMessages(['feature' => ['العداد غير متضمن كميزة استهلاك مقاس في نسخة الخطة الحالية.']]);
            }

            $this->entitlements->assertEnabled($subscription, $meterKey);

            $aggregation = strtoupper((string) $feature->aggregation);
            $resetPeriod = strtoupper((string) $feature->reset_period);
            if (! in_array($aggregation, ['SUM', 'COUNT', 'LAST', 'MAX', 'MIN'], true)
                || ! in_array($resetPeriod, ['DAY', 'WEEK', 'MONTH', 'YEAR', 'NEVER'], true)) {
                throw ValidationException::withMessages([
                    'aggregation' => ['إعدادات تجميع أو تصفير العداد غير صالحة.'],
                ]);
            }

            if ($meter->aggregation !== $aggregation || $meter->reset_period !== $resetPeriod) {
                throw ValidationException::withMessages([
                    'meter_key' => ['إعداد العداد لا يطابق إعداد الميزة المنشورة.'],
                ]);
            }

            $now = CarbonImmutable::now(self::TIMEZONE);
            $window = $this->periods->resolve($now, $resetPeriod);
            $billingPeriodStart = $subscription->current_period_start?->toDateString();
            $billingPeriodEnd = $subscription->current_period_end?->toDateString();

            if (! $billingPeriodStart || ! $billingPeriodEnd) {
                throw ValidationException::withMessages(['billing_period' => ['فترة فوترة الاشتراك غير محددة.']]);
            }

            $aggregate = BillingUsageAggregate::query()->firstOrCreate(
                [
                    'billing_subscription_id' => $subscriptionId,
                    'meter_id' => $meter->meter_id,
                    'period_start' => $window['start'],
                    'period_end' => $window['end'],
                ],
                [
                    'quantity' => '0.000000',
                    'amount_minor' => 0,
                    'currency_id' => $subscription->currency_id,
                ]
            );

            $aggregate = BillingUsageAggregate::query()
                ->lockForUpdate()
                ->findOrFail($aggregate->usage_aggregate_id);

            $currentMicros = $this->toMicros((string) $aggregate->quantity);
            $newMicros = $this->aggregation->aggregate(
                $currentMicros,
                $quantityMicros,
                $aggregation
            );

            $limitValue = trim((string) $feature->value);
            $isUnlimited = $limitValue === '-1';
            $limitMicros = $isUnlimited ? PHP_INT_MAX : $this->toMicros($limitValue);
            $behavior = strtoupper((string) $feature->overage_behavior);

            if (! in_array($behavior, ['BLOCK', 'TRACK', 'INVOICE'], true)) {
                throw ValidationException::withMessages(['overage_behavior' => ['سياسة تجاوز الحد غير مدعومة.']]);
            }
            if ($behavior === 'INVOICE' && ! in_array($aggregation, ['SUM', 'COUNT'], true)) {
                throw ValidationException::withMessages(['overage_behavior' => ['لا يمكن فوترة تجاوز LAST أو MAX أو MIN؛ استخدم BLOCK أو TRACK.']]);
            }

            $overage = $this->aggregation->overage($currentMicros, $newMicros, $limitMicros);
            $previousOverage = $isUnlimited ? 0 : $overage['previous'];
            $newOverage = $isUnlimited ? 0 : $overage['current'];
            if ($newOverage > 0 && $behavior === 'BLOCK') {
                throw ValidationException::withMessages([
                    'quota' => ['تم تجاوز الحد المقاس للاستهلاك في فترة إعادة التصفير الحالية.'],
                ]);
            }

            $eventAmountMinor = 0;
            if ($newOverage > $previousOverage && $behavior === 'INVOICE') {
                $configuration = (array) ($feature->configuration ?? []);
                $overagePrice = collect($configuration['overage_prices'] ?? [])
                    ->first(fn (array $price): bool => (int) ($price['currency_id'] ?? 0) === (int) $subscription->currency_id);
                $unitSizeMicros = (int) ($overagePrice['unit_size_micros'] ?? 0);
                $unitAmountMinor = (int) ($overagePrice['unit_amount_minor'] ?? -1);

                if ($unitSizeMicros <= 0 || $unitAmountMinor < 0) {
                    throw ValidationException::withMessages([
                        'configuration' => ['تعرفة تجاوز الاستخدام غير معرفة بعملة الاشتراك الحالية.'],
                    ]);
                }

                $previousUnits = intdiv($previousOverage, $unitSizeMicros)
                    + (($previousOverage % $unitSizeMicros) > 0 ? 1 : 0);
                $newUnits = intdiv($newOverage, $unitSizeMicros)
                    + (($newOverage % $unitSizeMicros) > 0 ? 1 : 0);
                $additionalUnits = $newUnits - $previousUnits;

                if ($unitAmountMinor > 0 && $additionalUnits > intdiv(PHP_INT_MAX, $unitAmountMinor)) {
                    throw new OverflowException('رسوم تجاوز الاستخدام تتجاوز السعة الرقمية المسموحة.');
                }

                $eventAmountMinor = $additionalUnits * $unitAmountMinor;
                $aggregate->amount_minor = $this->safeAdd((int) $aggregate->amount_minor, $eventAmountMinor);
            }

            $event = BillingUsageEvent::query()->create([
                'billing_subscription_id' => $subscriptionId,
                'meter_id' => $meter->meter_id,
                'idempotency_key' => $idempotencyKey,
                'quantity' => $this->fromMicros($quantityMicros),
                'amount_minor' => $eventAmountMinor,
                'occurred_at' => $now,
                'period_start' => $billingPeriodStart,
                'period_end' => $billingPeriodEnd,
                'dimensions' => $dimensions,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            $aggregate->quantity = $this->fromMicros($newMicros);
            $aggregate->last_event_at = $now;
            $aggregate->save();

            return $event->load(['subscription', 'meter']);
        }, 3);
    }

    private function toMicros(string $value): int
    {
        $value = trim($value);
        if (! preg_match('/^(\d+)(?:\.(\d{1,6}))?$/', $value, $matches)) {
            throw ValidationException::withMessages(['quantity' => ['صيغة كمية الاستهلاك غير صالحة.']]);
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        if (strlen($whole) > 12) {
            throw new OverflowException('كمية الاستهلاك تتجاوز السعة الرقمية المدعومة.');
        }

        $fraction = str_pad($matches[2] ?? '', 6, '0');
        $digits = ltrim($whole.$fraction, '0');
        $digits = $digits === '' ? '0' : $digits;
        $result = filter_var($digits, FILTER_VALIDATE_INT);
        if ($result === false) {
            throw new OverflowException('كمية الاستهلاك تتجاوز السعة الرقمية المدعومة.');
        }

        return (int) $result;
    }

    private function fromMicros(int $value): string
    {
        return intdiv($value, 1_000_000).'.'.str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new OverflowException('إجمالي الاستهلاك يتجاوز السعة الرقمية المدعومة.');
        }

        return $left + $right;
    }
}
