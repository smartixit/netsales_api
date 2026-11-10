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
                'trial_days' => $this->currentVersion?->trial_days,
                'prices' => BillingPriceResource::collection($this->currentVersion?->prices ?? collect()),
                'features' => BillingPlanFeatureResource::collection($this->currentVersion?->features ?? collect()),
            ]),
        ];
    }
}
