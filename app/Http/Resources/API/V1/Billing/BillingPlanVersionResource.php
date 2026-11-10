<?php

namespace App\\Http\\Resources\\API\\V1\\Billing;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

class BillingPlanVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'plan_version_id' => $this->plan_version_id,
            'plan_id' => $this->plan_id,
            'version_number' => (int) $this->version_number,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'trial_policy_version_id' => $this->trial_policy_version_id,
            'dunning_policy_version_id' => $this->dunning_policy_version_id,
            'trial_policy' => $this->whenLoaded('trialPolicyVersion', fn () => $this->trialPolicyVersion ? [
                'trial_policy_version_id' => $this->trialPolicyVersion->trial_policy_version_id,
                'policy_key' => $this->trialPolicyVersion->policy?->policy_key,
                'is_enabled' => (bool) $this->trialPolicyVersion->is_enabled,
                'duration_value' => (int) $this->trialPolicyVersion->duration_value,
                'duration_unit' => $this->trialPolicyVersion->duration_unit,
                'status' => $this->trialPolicyVersion->status,
            ] : null),
            'dunning_policy' => $this->whenLoaded('dunningPolicyVersion', fn () => $this->dunningPolicyVersion ? [
                'dunning_policy_version_id' => $this->dunningPolicyVersion->dunning_policy_version_id,
                'policy_key' => $this->dunningPolicyVersion->policy?->policy_key,
                'grace_period_days' => (int) $this->dunningPolicyVersion->grace_period_days,
                'auto_suspend_after_grace' => (bool) $this->dunningPolicyVersion->auto_suspend_after_grace,
                'reminder_schedule_days' => $this->dunningPolicyVersion->reminder_schedule_days,
                'pre_renewal_alert_days' => $this->dunningPolicyVersion->pre_renewal_alert_days,
                'status' => $this->dunningPolicyVersion->status,
            ] : null),
            'prices' => BillingPriceResource::collection($this->whenLoaded('prices')),
            'features' => BillingPlanFeatureResource::collection($this->whenLoaded('features')),
            'published_at' => $this->published_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
