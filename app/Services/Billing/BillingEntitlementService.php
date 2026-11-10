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

    public function activeSubscriptionForNetwork(int $networkId): ?BillingSubscription
    {
        return BillingSubscription::query()
            ->with(['planVersion.features', 'currency'])
            ->whereHas('billingAccount', fn ($account) => $account->where('network_id', $networkId))
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
