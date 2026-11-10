<?php

namespace App\Services\Billing;

use App\Enums\{BillingFeatureType, BillingSubscriptionStatus};
use App\Models\{BillingSubscription, BillingUsageAggregate, BillingUsageEvent, BillingUsageMeter};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OverflowException;

class BillingUsageService
{
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

        return DB::transaction(function () use ($subscriptionId, $meterKey, $quantity, $quantityMicros, $idempotencyKey, $sourceType, $sourceId, $dimensions): BillingUsageEvent {
            $subscription = BillingSubscription::query()
                ->with('planVersion.features')
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            if ($subscription->status !== BillingSubscriptionStatus::ACTIVE
                || ($subscription->current_period_end && $subscription->current_period_end->lte(now('Asia/Aden')))) {
                throw ValidationException::withMessages(['subscription_id' => ['لا يوجد اشتراك نشط يسمح بتسجيل الاستهلاك.']]);
            }

            $existing = BillingUsageEvent::query()
                ->where('billing_subscription_id', $subscriptionId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if ($existing->meter->meter_key !== $meterKey || $this->toMicros((string) $existing->quantity) !== $quantityMicros) {
                    throw ValidationException::withMessages(['idempotency_key' => ['مفتاح الاستهلاك أعيد استخدامه ببيانات مختلفة.']]);
                }

                return $existing;
            }

            $meter = BillingUsageMeter::query()
                ->where('meter_key', $meterKey)
                ->where('is_active', true)
                ->first();

            if (! $meter) {
                throw ValidationException::withMessages(['meter_key' => ['عداد الاستخدام غير موجود أو غير نشط.']]);
            }

            $feature = $subscription->planVersion?->features->firstWhere('feature_key', $meterKey);
            if (! $feature || $feature->feature_type !== BillingFeatureType::METERED) {
                throw ValidationException::withMessages(['feature' => ['العداد غير متضمن كميزة استهلاك مقاس في نسخة الخطة الحالية.']]);
            }

            $periodStart = $subscription->current_period_start?->toDateString();
            $periodEnd = $subscription->current_period_end?->toDateString();
            if (! $periodStart || ! $periodEnd) {
                throw ValidationException::withMessages(['billing_period' => ['فترة فوترة الاشتراك غير محددة.']]);
            }

            $aggregate = BillingUsageAggregate::query()->firstOrCreate(
                [
                    'billing_subscription_id' => $subscriptionId,
                    'meter_id' => $meter->meter_id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
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
            $limitMicros = $this->toMicros((string) $feature->value);
            $newMicros = $this->safeAdd($currentMicros, $quantityMicros);

            $behavior = strtoupper((string) $feature->overage_behavior);
            if ($newMicros > $limitMicros && $behavior === 'BLOCK') {
                throw ValidationException::withMessages([
                    'quota' => ['تم تجاوز الحد المقاس للاستهلاك في فترة الفوترة الحالية.'],
                ]);
            }

            $event = BillingUsageEvent::query()->create([
                'billing_subscription_id' => $subscriptionId,
                'meter_id' => $meter->meter_id,
                'idempotency_key' => $idempotencyKey,
                'quantity' => $this->fromMicros($quantityMicros),
                'occurred_at' => now('Asia/Aden'),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'dimensions' => $dimensions,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            $aggregate->quantity = $this->fromMicros($newMicros);
            $aggregate->last_event_at = now('Asia/Aden');
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
        return intdiv($value, 1000000).'.'.str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new OverflowException('إجمالي الاستهلاك يتجاوز السعة الرقمية المدعومة.');
        }

        return $left + $right;
    }
}
