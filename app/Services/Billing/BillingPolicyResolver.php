<?php

namespace App\Services\Billing;

use App\Enums\{BillingTrialEligibilityMode, BillingTrialRedemptionScope, BillingTrialRedemptionStatus};
use App\Models\{BillingAccount, BillingDunningPolicy, BillingDunningPolicyVersion, BillingPlan, BillingPlanVersion, BillingPrice, BillingSubscription, BillingTrialPolicy, BillingTrialPolicyVersion, BillingTrialRedemption, Network};
use Illuminate\Validation\ValidationException;

class BillingPolicyResolver
{
    /**
     * @return array{trial_version: BillingTrialPolicyVersion|null, dunning_version: BillingDunningPolicyVersion}
     */
    public function resolveForPurchase(BillingPrice $price, BillingAccount $account, Network $network): array
    {
        $planVersion = $price->planVersion;
        $trialVersion = $price->trial_policy_version_id
            ? BillingTrialPolicyVersion::query()->with('policy')->find($price->trial_policy_version_id)
            : ($planVersion->trial_policy_version_id
                ? BillingTrialPolicyVersion::query()->with('policy')->find($planVersion->trial_policy_version_id)
                : $this->defaultTrialPolicy());

        $dunningVersion = $price->dunning_policy_version_id
            ? BillingDunningPolicyVersion::query()->with('policy')->find($price->dunning_policy_version_id)
            : ($planVersion->dunning_policy_version_id
                ? BillingDunningPolicyVersion::query()->with('policy')->find($planVersion->dunning_policy_version_id)
                : $this->defaultDunningPolicy());

        if (! $dunningVersion || $dunningVersion->status !== 'PUBLISHED' || ! $dunningVersion->policy->is_active) {
            throw ValidationException::withMessages([
                'dunning_policy' => ['لا توجد سياسة تحصيل منشورة وفعالة لهذا السعر.'],
            ]);
        }

        $trialEnabled = $trialVersion
            && $trialVersion->status === 'PUBLISHED'
            && $trialVersion->policy->is_active
            && $trialVersion->is_enabled
            && $trialVersion->duration_value > 0;

        if ($trialEnabled && ! $this->isEligible($trialVersion, $account, $network, $planVersion->plan)) {
            $trialEnabled = false;
        }

        return [
            'trial_version' => $trialEnabled ? $trialVersion : null,
            'dunning_version' => $dunningVersion,
        ];
    }

    public function reserveRedemption(
        BillingTrialPolicyVersion $version,
        BillingSubscription $subscription,
        BillingAccount $account,
        Network $network,
        BillingPlan $plan
    ): BillingTrialRedemption {
        $scope = BillingTrialRedemptionScope::from($version->redemption_scope);
        $subject = match ($scope) {
            BillingTrialRedemptionScope::NETWORK => 'network:'.$network->network_id,
            BillingTrialRedemptionScope::BILLING_ACCOUNT => 'billing-account:'.$account->billing_account_id,
            BillingTrialRedemptionScope::PRODUCT => 'network:'.$network->network_id.':product:'.$plan->product_id,
        };

        $requiresApproval = (bool) $version->requires_manual_approval;
        $startsOnPurchase = $version->start_trigger === 'PURCHASE' && ! $requiresApproval;

        return BillingTrialRedemption::query()->create([
            'trial_policy_id' => $version->trial_policy_id,
            'trial_policy_version_id' => $version->trial_policy_version_id,
            'billing_subscription_id' => $subscription->billing_subscription_id,
            'network_id' => $network->network_id,
            'billing_account_id' => $account->billing_account_id,
            'product_id' => $plan->product_id,
            'redemption_scope' => $scope->value,
            'redemption_subject_key' => $subject,
            'status' => $requiresApproval
                ? BillingTrialRedemptionStatus::PENDING_APPROVAL->value
                : ($startsOnPurchase
                    ? BillingTrialRedemptionStatus::ACTIVE->value
                    : BillingTrialRedemptionStatus::RESERVED->value),
            'reserved_at' => now('Asia/Aden'),
            'started_at' => $startsOnPurchase ? now('Asia/Aden') : null,
            'metadata' => [
                'eligibility_mode' => $version->eligibility_mode,
                'start_trigger' => $version->start_trigger,
                'requires_manual_approval' => $requiresApproval,
                'policy_version_number' => (int) $version->version_number,
            ],
        ]);
    }

    public function isEligible(
        BillingTrialPolicyVersion $version,
        BillingAccount $account,
        Network $network,
        BillingPlan $plan
    ): bool {
        $mode = BillingTrialEligibilityMode::from($version->eligibility_mode);

        if ($mode === BillingTrialEligibilityMode::NEW_NETWORK
            && BillingTrialRedemption::query()->where('network_id', $network->network_id)->exists()) {
            return false;
        }

        if ($mode === BillingTrialEligibilityMode::NEW_BILLING_ACCOUNT
            && BillingTrialRedemption::query()->where('billing_account_id', $account->billing_account_id)->exists()) {
            return false;
        }

        $scope = BillingTrialRedemptionScope::from($version->redemption_scope);
        $subject = match ($scope) {
            BillingTrialRedemptionScope::NETWORK => 'network:'.$network->network_id,
            BillingTrialRedemptionScope::BILLING_ACCOUNT => 'billing-account:'.$account->billing_account_id,
            BillingTrialRedemptionScope::PRODUCT => 'network:'.$network->network_id.':product:'.$plan->product_id,
        };

        return ! BillingTrialRedemption::query()
            ->where('trial_policy_id', $version->trial_policy_id)
            ->where('redemption_scope', $scope->value)
            ->where('redemption_subject_key', $subject)
            ->exists();
    }

    public function defaultTrialPolicy(): ?BillingTrialPolicyVersion
    {
        $policy = BillingTrialPolicy::query()
            ->where('policy_key', 'platform-default')
            ->where('is_active', true)
            ->first();

        return $policy?->currentVersion()->first();
    }

    public function defaultDunningPolicy(): ?BillingDunningPolicyVersion
    {
        $policy = BillingDunningPolicy::query()
            ->where('policy_key', 'platform-default')
            ->where('is_active', true)
            ->first();

        return $policy?->currentVersion()->first();
    }

    /** @return array<string, mixed> */
    public function snapshot(BillingTrialPolicyVersion $version): array
    {
        return [
            'trial_policy_id' => (int) $version->trial_policy_id,
            'trial_policy_version_id' => (int) $version->trial_policy_version_id,
            'policy_key' => $version->policy->policy_key,
            'version_number' => (int) $version->version_number,
            'is_enabled' => (bool) $version->is_enabled,
            'duration_value' => (int) $version->duration_value,
            'duration_unit' => $version->duration_unit,
            'start_trigger' => $version->start_trigger,
            'redemption_scope' => $version->redemption_scope,
            'eligibility_mode' => $version->eligibility_mode,
            'requires_manual_approval' => (bool) $version->requires_manual_approval,
            'end_behavior' => $version->end_behavior,
            'entitlement_mode' => $version->entitlement_mode,
            'entitlement_config' => $version->entitlement_config,
        ];
    }

    /** @return array<string, mixed> */
    public function dunningSnapshot(BillingDunningPolicyVersion $version): array
    {
        return [
            'dunning_policy_id' => (int) $version->dunning_policy_id,
            'dunning_policy_version_id' => (int) $version->dunning_policy_version_id,
            'policy_key' => $version->policy->policy_key,
            'version_number' => (int) $version->version_number,
            'grace_period_days' => (int) $version->grace_period_days,
            'auto_suspend_after_grace' => (bool) $version->auto_suspend_after_grace,
            'reminder_schedule_days' => $version->reminder_schedule_days,
            'pre_renewal_alert_days' => $version->pre_renewal_alert_days,
        ];
    }
}
