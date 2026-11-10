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
            ->where('status', BillingSubscriptionStatus::ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('current_period_end')
                    ->orWhere('current_period_end', '>', now('Asia/Aden'));
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
        if ($subscription->status !== BillingSubscriptionStatus::ACTIVE
            || ($subscription->current_period_end && $subscription->current_period_end->lte(now('Asia/Aden')))) {
            return false;
        }

        $feature = $subscription->planVersion?->features->firstWhere('feature_key', $featureKey);
        if (! $feature) {
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
        if ($limit === false || $limit < 0 || $currentCount < 0 || $requested < 1) {
            throw ValidationException::withMessages(['feature' => ['قيمة حد الموارد غير صالحة.']]);
        }

        if ($currentCount > $limit - $requested) {
            throw ValidationException::withMessages(['quota' => ['تم تجاوز حد الموارد المسموح به في الاشتراك.']]);
        }
    }
}
