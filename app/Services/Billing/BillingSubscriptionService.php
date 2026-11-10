<?php

namespace App\Services\Billing;

use App\Enums\{BillingInvoiceStatus, BillingPlanVersionStatus, BillingSubscriptionStatus, BillingInterval};
use App\Models\{BillingAccount, BillingPrice, BillingSubscription, BillingSubscriptionEvent, BillingTrialPolicyVersion, Network, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingSubscriptionService
{
    public function __construct(
        private BillingAuthorizationService $authorization,
        private BillingInvoiceService $invoices,
        private BillingDunningService $dunning,
        private BillingPolicyResolver $policies
    ) {}

    /**
     * Creates an incomplete subscription and its first invoice; entitlements are not activated before verified payment.
     *
     * @return array{subscription: BillingSubscription, invoice: ?\App\Models\BillingInvoice}
     */
    public function purchase(int $billingAccountId, int $priceId, User $actor): array
    {
        return DB::transaction(function () use ($billingAccountId, $priceId, $actor): array {
            $initialAccount = BillingAccount::query()->findOrFail($billingAccountId);
            $this->authorization->assertNetworkOwner($actor, (int) $initialAccount->network_id);

            $network = Network::query()->lockForUpdate()->findOrFail($initialAccount->network_id);
            if ((int) $network->owner_id !== (int) $actor->user_id || ! $network->isActive()) {
                throw ValidationException::withMessages([
                    'billing_account_id' => ['الشبكة غير متاحة أو لم تعد مملوكة للمستخدم الحالي.'],
                ]);
            }

            $account = BillingAccount::query()
                ->with('defaultCurrency')
                ->lockForUpdate()
                ->findOrFail($billingAccountId);

            if ((int) $account->network_id !== (int) $network->network_id) {
                throw ValidationException::withMessages(['billing_account_id' => ['حساب الفوترة لا يتبع الشبكة المحددة.']]);
            }

            if ($account->status !== 'ACTIVE') {
                throw ValidationException::withMessages(['billing_account_id' => ['حساب الفوترة غير نشط.']]);
            }

            $price = BillingPrice::query()
                ->with(['currency', 'planVersion.plan.product', 'planVersion.features', 'trialPolicyVersion.policy', 'dunningPolicyVersion.policy'])
                ->lockForUpdate()
                ->findOrFail($priceId);

            if (! $price->is_active
                || ! $price->currency?->is_active
                || $price->planVersion?->status !== BillingPlanVersionStatus::PUBLISHED
                || ! $price->planVersion->plan?->is_active) {
                throw ValidationException::withMessages(['price_id' => ['السعر أو نسخة الخطة غير منشورة أو غير مفعلة.']]);
            }

            if ((int) $price->currency_id !== (int) $account->default_currency_id) {
                throw ValidationException::withMessages(['price_id' => ['عملة السعر لا تطابق العملة التعاقدية لحساب الفوترة.']]);
            }

            if ($price->billing_interval === BillingInterval::ONE_TIME) {
                throw ValidationException::withMessages(['billing_interval' => ['هذا السعر مخصص للشراء لمرة واحدة وليس لاشتراك دوري.']]);
            }

            $liveStatuses = [
                BillingSubscriptionStatus::INCOMPLETE->value,
                BillingSubscriptionStatus::TRIALING->value,
                BillingSubscriptionStatus::ACTIVE->value,
                BillingSubscriptionStatus::PAST_DUE->value,
                BillingSubscriptionStatus::PAUSED->value,
                BillingSubscriptionStatus::SUSPENDED->value,
            ];

            $existing = BillingSubscription::query()
                ->whereIn('status', $liveStatuses)
                ->whereHas('billingAccount', fn ($query) => $query->where('network_id', $network->network_id))
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'billing_account_id' => ['توجد للشبكة رخصة اشتراك قائمة بإحدى عملاتها؛ يجب إنهاؤها أو تنفيذ تغيير الخطة بدلاً من إنشاء اشتراك متوازٍ.'],
                ]);
            }

            $now = CarbonImmutable::now('Asia/Aden');
            $intervalCount = max(1, (int) $price->interval_count);
            $periodEnd = match ($price->billing_interval) {
                BillingInterval::DAY => $now->addDays($intervalCount),
                BillingInterval::WEEK => $now->addWeeks($intervalCount),
                BillingInterval::MONTH => $now->addMonthsNoOverflow($intervalCount),
                BillingInterval::YEAR => $now->addYearsNoOverflow($intervalCount),
                BillingInterval::ONE_TIME => null,
            };

            $resolvedPolicies = $this->policies->resolveForPurchase($price, $account, $network);
            $trialVersion = $resolvedPolicies['trial_version'];
            $dunningVersion = $resolvedPolicies['dunning_version'];
            $isTrial = $trialVersion !== null;
            $trialDays = $isTrial ? $this->trialDurationDays($trialVersion) : 0;
            $trialStartsAtPurchase = $isTrial && $trialVersion->start_trigger === 'PURCHASE' && ! $trialVersion->requires_manual_approval;
            $trialStart = $trialStartsAtPurchase ? $now : null;
            $trialEnd = $trialStartsAtPurchase ? $this->trialEnd($now, $trialVersion) : null;
            $subscription = BillingSubscription::query()->create([
                'billing_account_id' => $account->billing_account_id,
                'plan_id' => $price->planVersion->plan_id,
                'plan_version_id' => $price->plan_version_id,
                'price_id' => $price->price_id,
                'currency_id' => $price->currency_id,
                'trial_policy_version_id' => $trialVersion?->trial_policy_version_id,
                'dunning_policy_version_id' => $dunningVersion->dunning_policy_version_id,
                'trial_policy_snapshot' => $trialVersion ? $this->policies->snapshot($trialVersion) : null,
                'dunning_policy_snapshot' => $this->policies->dunningSnapshot($dunningVersion),
                'status' => $isTrial && $trialStartsAtPurchase ? BillingSubscriptionStatus::TRIALING : BillingSubscriptionStatus::INCOMPLETE,
                'collection_method' => 'MANUAL',
                'quantity' => 1,
                'current_period_start' => $trialStart,
                'current_period_end' => $trialEnd,
                'trial_start' => $trialStart,
                'trial_end' => $trialEnd,
                'terms_snapshot' => [
                    'plan_name' => $price->planVersion->plan->name,
                    'plan_key' => $price->planVersion->plan->plan_key,
                    'version_number' => $price->planVersion->version_number,
                    'price_id' => $price->price_id,
                    'unit_amount_minor' => $price->unit_amount_minor,
                    'setup_fee_minor' => $price->setup_fee_minor,
                    'currency_code' => $price->currency->code,
                    'decimal_places' => $price->currency->decimal_places,
                    'interval' => $price->billing_interval->value,
                    'interval_count' => $intervalCount,
                    'features' => $price->planVersion->features->map(static fn ($feature): array => [
                        'feature_key' => $feature->feature_key,
                        'name' => $feature->name,
                        'type' => $feature->feature_type->value,
                        'value' => $feature->value,
                        'unit' => $feature->unit,
                    ])->values()->all(),
                ],
                'created_by' => $actor->user_id,
            ]);

            $subscription->items()->create([
                'item_type' => 'PLAN',
                'description' => $price->planVersion->plan->name,
                'quantity' => 1,
                'unit_amount_minor' => $price->unit_amount_minor,
                'amount_minor' => $price->unit_amount_minor,
                'currency_id' => $price->currency_id,
                'terms_snapshot' => $subscription->terms_snapshot,
            ]);

            BillingSubscriptionEvent::query()->create([
                'billing_subscription_id' => $subscription->billing_subscription_id,
                'event_type' => $isTrial ? 'TRIAL_STARTED' : 'SUBSCRIPTION_CREATED',
                'from_status' => null,
                'to_status' => $subscription->status->value,
                'payload' => ['price_id' => $price->price_id],
                'actor_id' => $actor->user_id,
                'occurred_at' => $now,
            ]);

            if ($isTrial) {
                $redemption = $this->policies->reserveRedemption($trialVersion, $subscription, $account, $network, $price->planVersion->plan);
                if ($trialStartsAtPurchase) {
                    $this->dunning->scheduleSubscriptionAlerts($subscription);
                }
                $subscription->events()->create([
                    'event_type' => $trialVersion->requires_manual_approval ? 'TRIAL_PENDING_APPROVAL' : 'TRIAL_RESERVED',
                    'from_status' => null,
                    'to_status' => $subscription->status->value,
                    'payload' => ['trial_redemption_id' => $redemption->trial_redemption_id, 'trial_policy_version_id' => $trialVersion->trial_policy_version_id, 'trial_end' => $trialEnd?->toDateTimeString()],
                    'actor_id' => $actor->user_id,
                    'occurred_at' => $now,
                ]);

                return ['subscription' => $subscription->load(['planVersion.features', 'currency', 'price', 'trialRedemption']), 'invoice' => null];
            }

            $invoice = $this->invoices->createForSubscription($subscription, $actor);
            $invoice = $this->invoices->finalize((int) $invoice->invoice_id, $actor);

            if ($invoice->status === BillingInvoiceStatus::PAID && (int) $invoice->total_minor === 0) {
                $subscription->status = BillingSubscriptionStatus::ACTIVE;
                $subscription->current_period_start = $now;
                $subscription->current_period_end = $periodEnd;
                $subscription->save();

                $subscription->events()->create([
                    'event_type' => 'ZERO_VALUE_SUBSCRIPTION_ACTIVATED',
                    'from_status' => BillingSubscriptionStatus::INCOMPLETE->value,
                    'to_status' => BillingSubscriptionStatus::ACTIVE->value,
                    'payload' => ['invoice_id' => $invoice->invoice_id, 'currency_id' => $subscription->currency_id],
                    'actor_id' => $actor->user_id,
                    'occurred_at' => now('Asia/Aden'),
                ]);
            } elseif ($invoice->status !== BillingInvoiceStatus::PAID) {
                $graceEndsAt = now('Asia/Aden')->addDays((int) $subscription->dunning_policy_snapshot['grace_period_days']);
                $subscription->grace_period_ends_at = $graceEndsAt;
                $subscription->save();

                $subscription->events()->create([
                    'event_type' => 'INITIAL_INVOICE_ISSUED',
                    'from_status' => BillingSubscriptionStatus::INCOMPLETE->value,
                    'to_status' => BillingSubscriptionStatus::INCOMPLETE->value,
                    'payload' => [
                        'invoice_id' => $invoice->invoice_id,
                        'grace_period_ends_at' => $graceEndsAt->toDateTimeString(),
                    ],
                    'actor_id' => $actor->user_id,
                    'occurred_at' => now('Asia/Aden'),
                ]);

                $this->dunning->scheduleForInvoice($invoice);
            }

            return [
                'subscription' => $subscription->refresh()->load(['planVersion.features', 'currency', 'price']),
                'invoice' => $invoice,
            ];
        }, 3);
    }

    private function trialDurationDays(BillingTrialPolicyVersion $version): int
    {
        return match ($version->duration_unit) {
            'HOUR' => max(1, (int) ceil($version->duration_value / 24)),
            'DAY' => (int) $version->duration_value,
            'WEEK' => (int) $version->duration_value * 7,
            'MONTH' => (int) $version->duration_value * 30,
            default => throw ValidationException::withMessages(['trial_policy' => ['وحدة مدة التجربة غير مدعومة.']]),
        };
    }

    private function trialEnd(CarbonImmutable $start, BillingTrialPolicyVersion $version): CarbonImmutable
    {
        return match ($version->duration_unit) {
            'HOUR' => $start->addHours($version->duration_value),
            'DAY' => $start->addDays($version->duration_value),
            'WEEK' => $start->addWeeks($version->duration_value),
            'MONTH' => $start->addMonthsNoOverflow($version->duration_value),
            default => throw ValidationException::withMessages(['trial_policy' => ['وحدة مدة التجربة غير مدعومة.']]),
        };
    }

    public function cancel(int $subscriptionId, bool $atPeriodEnd, User $actor): BillingSubscription
    {
        return DB::transaction(function () use ($subscriptionId, $atPeriodEnd, $actor): BillingSubscription {
            $subscription = BillingSubscription::query()->with('billingAccount')->lockForUpdate()->findOrFail($subscriptionId);
            $this->authorization->assertNetworkOwner($actor, (int) $subscription->billingAccount->network_id);

            if (! in_array($subscription->status, [
                BillingSubscriptionStatus::ACTIVE,
                BillingSubscriptionStatus::TRIALING,
                BillingSubscriptionStatus::PAST_DUE,
                BillingSubscriptionStatus::PAUSED,
                BillingSubscriptionStatus::INCOMPLETE,
            ], true)) {
                throw ValidationException::withMessages(['subscription_id' => ['لا يمكن إلغاء الاشتراك في حالته الحالية.']]);
            }

            $oldStatus = $subscription->status->value;
            if ($atPeriodEnd) {
                $subscription->cancel_at_period_end = true;
                $eventType = 'CANCELLATION_SCHEDULED';
            } else {
                $subscription->status = BillingSubscriptionStatus::CANCELED;
                $subscription->cancel_at_period_end = false;
                $subscription->canceled_at = now('Asia/Aden');
                $subscription->ended_at = now('Asia/Aden');
                $eventType = 'SUBSCRIPTION_CANCELED';
            }

            $subscription->save();
            $subscription->events()->create([
                'event_type' => $eventType,
                'from_status' => $oldStatus,
                'to_status' => $subscription->status->value,
                'payload' => ['cancel_at_period_end' => $atPeriodEnd],
                'actor_id' => $actor->user_id,
                'occurred_at' => now('Asia/Aden'),
            ]);

            return $subscription->refresh()->load(['currency', 'planVersion']);
        }, 3);
    }
}
