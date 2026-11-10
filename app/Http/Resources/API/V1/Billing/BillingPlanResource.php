<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'plan_id' => $this->plan_id,
            'plan_key' => $this->plan_key,
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => (bool) $this->is_public,
            'is_active' => (bool) $this->is_active,
            'product' => $this->whenLoaded('product', fn () => [
                'product_id' => $this->product?->product_id,
                'product_key' => $this->product?->product_key,
                'name' => $this->product?->name,
            ]),
            'current_version' => $this->whenLoaded('currentVersion', fn () => [
                'plan_version_id' => $this->currentVersion?->plan_version_id,
                'version_number' => $this->currentVersion?->version_number,
                'status' => $this->currentVersion?->status?->value,
                'status_label' => $this->currentVersion?->status?->label(),
                'trial_policy' => $this->currentVersion?->trialPolicyVersion ? [
                    'trial_policy_version_id' => $this->currentVersion->trialPolicyVersion->trial_policy_version_id,
                    'policy_key' => $this->currentVersion->trialPolicyVersion->policy?->policy_key,
                    'is_enabled' => (bool) $this->currentVersion->trialPolicyVersion->is_enabled,
                    'duration_value' => (int) $this->currentVersion->trialPolicyVersion->duration_value,
                    'duration_unit' => $this->currentVersion->trialPolicyVersion->duration_unit,
                ] : null,
                'dunning_policy_version_id' => $this->currentVersion?->dunning_policy_version_id,
                'prices' => BillingPriceResource::collection($this->currentVersion?->prices ?? collect()),
                'features' => BillingPlanFeatureResource::collection($this->currentVersion?->features ?? collect()),
            ]),
        ];
    }
}
