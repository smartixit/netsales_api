<?php

namespace App\Services\Billing;

use App\Enums\BillingSubscriptionStatus;
use App\Enums\BillingFeatureType;
use App\Models\BillingSubscription;
use Illuminate\Validation\ValidationException;

class BillingEntitlementService
{
    public function activeSubscriptionForAccount(int $billingAccountId): ?BillingSubscription
    {
        $this->startEligibleFirstActivation($billingAccountId);

        return BillingSubscription::query()
            ->with(['planVersion.features', 'currency'])
            ->where('billing_account_id', $billingAccountId)
            ->where(function ($query): void {
                $query->where(function ($active): void {
                    $active->whereIn('status', [
                        BillingSubscriptionStatus::ACTIVE,
                        BillingSubscriptionStatus::TRIALING,
                    ])->where(function ($period): void {
                        $period->whereNull('current_period_end')
                            ->orWhere('current_period_end', '>', now('Asia/Aden'));
                    });
                })->orWhere(function ($grace): void {
                    $grace->where('status', BillingSubscriptionStatus::PAST_DUE)
                        ->where('grace_period_ends_at', '>', now('Asia/Aden'));
                });
            })
            ->latest('billing_subscription_id')
            ->first();
    }

    /**
     * Read-only subscription preview for the billing center. Unlike the operational
     * entitlement resolver, this method must never start FIRST_ACTIVATION trials.
     */
    public function previewSubscriptionForAccount(int $billingAccountId): ?BillingSubscription
    {
        return BillingSubscription::query()
            ->with(['planVersion.features', 'currency', 'trialRedemption.policyVersion'])
            ->where('billing_account_id', $billingAccountId)
            ->whereIn('status', [
                BillingSubscriptionStatus::INCOMPLETE,
                BillingSubscriptionStatus::TRIALING,
                BillingSubscriptionStatus::ACTIVE,
                BillingSubscriptionStatus::PAST_DUE,
                BillingSubscriptionStatus::PAUSED,
                BillingSubscriptionStatus::SUSPENDED,
            ])
            ->latest('billing_subscription_id')
            ->first();
    }

    public function activeSubscriptionForNetwork(int $networkId): ?BillingSubscription
    {
        // Resolve the subscription first; never choose an arbitrary account on a multi-currency network.
        $candidate = BillingSubscription::query()
            ->whereHas('billingAccount', fn ($account) => $account
                ->where('network_id', $networkId)
                ->where('status', 'ACTIVE'))
            ->where(function ($query): void {
                $query->where(function ($activation): void {
                    $activation->where('status', BillingSubscriptionStatus::INCOMPLETE)
                        ->whereNotNull('trial_policy_snapshot')
                        ->where('trial_policy_snapshot->start_trigger', 'FIRST_ACTIVATION');
                })->orWhereIn('status', [
                    BillingSubscriptionStatus::ACTIVE,
                    BillingSubscriptionStatus::TRIALING,
                    BillingSubscriptionStatus::PAST_DUE,
                ]);
            })
            ->orderByDesc('billing_subscription_id')
            ->first();

        if ($candidate && $candidate->status === BillingSubscriptionStatus::INCOMPLETE) {
            $this->startEligibleFirstActivation((int) $candidate->billing_account_id);
        }

        return BillingSubscription::query()
            ->with(['planVersion.features', 'currency'])
            ->whereHas('billingAccount', fn ($account) => $account->where('network_id', $networkId)->where('status', 'ACTIVE'))
            ->where(function ($query): void {
                $query->where(function ($active): void {
                    $active->whereIn('status', [
                        BillingSubscriptionStatus::ACTIVE,
                        BillingSubscriptionStatus::TRIALING,
                    ])->where(function ($period): void {
                        $period->whereNull('current_period_end')
                            ->orWhere('current_period_end', '>', now('Asia/Aden'));
                    });
                })->orWhere(function ($grace): void {
                    $grace->where('status', BillingSubscriptionStatus::PAST_DUE)
                        ->where('grace_period_ends_at', '>', now('Asia/Aden'));
                });
            })
            ->latest('billing_subscription_id')
            ->first();
    }


    private function startEligibleFirstActivation(int $billingAccountId): void
    {
        $subscriptionId = BillingSubscription::query()
            ->where('billing_account_id', $billingAccountId)
            ->where('status', BillingSubscriptionStatus::INCOMPLETE)
            ->whereNotNull('trial_policy_snapshot')
            ->where('trial_policy_snapshot->start_trigger', 'FIRST_ACTIVATION')
            ->orderBy('billing_subscription_id')
            ->value('billing_subscription_id');

        if (! $subscriptionId) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($subscriptionId): void {
            $subscription = BillingSubscription::query()
                ->with('trialRedemption')
                ->lockForUpdate()
                ->find($subscriptionId);

            if (! $subscription
                || $subscription->status !== BillingSubscriptionStatus::INCOMPLETE
                || ! $subscription->trialRedemption
                || $subscription->trialRedemption->status !== 'RESERVED') {
                return;
            }

            $snapshot = (array) $subscription->trial_policy_snapshot;
            if (($snapshot['start_trigger'] ?? null) !== 'FIRST_ACTIVATION'
                || (($snapshot['requires_manual_approval'] ?? false)
                    && empty($subscription->trialRedemption->metadata['approved_at']))) {
                return;
            }

            $now = \Carbon\CarbonImmutable::now('Asia/Aden');
            $end = match ($snapshot['duration_unit'] ?? null) {
                'HOUR' => $now->addHours((int) $snapshot['duration_value']),
                'DAY' => $now->addDays((int) $snapshot['duration_value']),
                'WEEK' => $now->addWeeks((int) $snapshot['duration_value']),
                'MONTH' => $now->addMonthsNoOverflow((int) $snapshot['duration_value']),
                default => null,
            };

            if (! $end || $end->lte($now)) {
                return;
            }

            $subscription->status = BillingSubscriptionStatus::TRIALING;
            $subscription->trial_start = $now;
            $subscription->trial_end = $end;
            $subscription->current_period_start = $now;
            $subscription->current_period_end = $end;
            $subscription->save();

            $subscription->trialRedemption->status = 'ACTIVE';
            $subscription->trialRedemption->started_at = $now;
            $subscription->trialRedemption->save();

            app(BillingDunningService::class)->scheduleSubscriptionAlerts($subscription);
            $subscription->events()->create([
                'event_type' => 'TRIAL_STARTED_ON_FIRST_ACTIVATION',
                'from_status' => BillingSubscriptionStatus::INCOMPLETE->value,
                'to_status' => BillingSubscriptionStatus::TRIALING->value,
                'payload' => [
                    'trial_redemption_id' => $subscription->trialRedemption->trial_redemption_id,
                    'trial_end' => $end->toDateTimeString(),
                ],
                'occurred_at' => $now,
            ]);
        }, 3);
    }

    public function featureValue(BillingSubscription $subscription, string $featureKey): mixed
    {
        $feature = $subscription->planVersion?->features
            ->firstWhere('feature_key', $featureKey);

        if (! $feature) {
            return null;
        }

        return match ($feature->feature_type) {
            BillingFeatureType::BOOLEAN => filter_var($feature->value, FILTER_VALIDATE_BOOLEAN),
            BillingFeatureType::RESOURCE_LIMIT, BillingFeatureType::METERED => $feature->value,
            BillingFeatureType::TEXT => $feature->value,
            BillingFeatureType::JSON => is_string($feature->value) ? json_decode($feature->value, true) : $feature->value,
        };
    }

    public function allows(BillingSubscription $subscription, string $featureKey): bool
    {
        $active = in_array($subscription->status, [
            BillingSubscriptionStatus::ACTIVE,
            BillingSubscriptionStatus::TRIALING,
        ], true) && (! $subscription->current_period_end || $subscription->current_period_end->gt(now('Asia/Aden')));
        $grace = $subscription->status === BillingSubscriptionStatus::PAST_DUE
            && $subscription->grace_period_ends_at
            && $subscription->grace_period_ends_at->gt(now('Asia/Aden'));

        if (! $active && ! $grace) {
            return false;
        }

        $feature = $subscription->planVersion?->features->firstWhere('feature_key', $featureKey);
        if (! $feature) {
            return false;
        }

        if ($active && $subscription->status === BillingSubscriptionStatus::TRIALING) {
            $trialPolicy = (array) $subscription->trial_policy_snapshot;
            $mode = $trialPolicy['entitlement_mode'] ?? 'FULL_PLAN';
            if ($mode === 'NONE') {
                return false;
            }
            if ($mode === 'RESTRICTED') {
                $allowed = (array) ($trialPolicy['entitlement_config']['allowed_features'] ?? []);
                if (! in_array($featureKey, $allowed, true)) {
                    return false;
                }
            }
        }

        if ($grace && $feature->feature_type === BillingFeatureType::METERED) {
            return false;
        }

        return match ($feature->feature_type) {
            BillingFeatureType::BOOLEAN => filter_var($feature->value, FILTER_VALIDATE_BOOLEAN),
            BillingFeatureType::RESOURCE_LIMIT, BillingFeatureType::METERED => true,
            BillingFeatureType::TEXT, BillingFeatureType::JSON => true,
        };
    }

    public function assertEnabled(BillingSubscription $subscription, string $featureKey): void
    {
        if (! $this->allows($subscription, $featureKey)) {
            throw ValidationException::withMessages([
                'feature' => ["الميزة [{$featureKey}] غير مفعلة ضمن اشتراكك الحالي."],
            ]);
        }
    }

    public function assertResourceLimit(BillingSubscription $subscription, string $featureKey, int $currentCount, int $requested = 1): void
    {
        $feature = $subscription->planVersion?->features->firstWhere('feature_key', $featureKey);

        if (! $feature || $feature->feature_type !== BillingFeatureType::RESOURCE_LIMIT) {
            throw ValidationException::withMessages(['feature' => ['الميزة غير معرفة كحد موارد في الخطة.']]);
        }

        $limit = filter_var($feature->value, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < -1 || $currentCount < 0 || $requested < 1) {
            throw ValidationException::withMessages(['feature' => ['قيمة حد الموارد غير صالحة.']]);
        }

        if ($limit !== -1 && $currentCount > $limit - $requested) {
            throw ValidationException::withMessages(['quota' => ['تم تجاوز حد الموارد المسموح به في الاشتراك.']]);
        }
    }
}
