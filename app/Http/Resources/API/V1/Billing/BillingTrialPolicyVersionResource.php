<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingTrialPolicyVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'trial_policy_version_id' => $this->trial_policy_version_id,
            'trial_policy_id' => $this->trial_policy_id,
            'policy_key' => $this->policy?->policy_key,
            'version_number' => (int) $this->version_number,
            'status' => $this->status,
            'is_enabled' => (bool) $this->is_enabled,
            'duration_value' => (int) $this->duration_value,
            'duration_unit' => $this->duration_unit,
            'start_trigger' => $this->start_trigger,
            'redemption_scope' => $this->redemption_scope,
            'eligibility_mode' => $this->eligibility_mode,
            'requires_manual_approval' => (bool) $this->requires_manual_approval,
            'end_behavior' => $this->end_behavior,
            'entitlement_mode' => $this->entitlement_mode,
            'entitlement_config' => $this->entitlement_config,
            'published_at' => $this->published_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
