<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingPlanFeatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'plan_feature_id' => $this->plan_feature_id,
            'feature_key' => $this->feature_key,
            'name' => $this->name,
            'feature_type' => $this->feature_type?->value,
            'feature_type_label' => $this->feature_type?->label(),
            'value' => $this->value,
            'unit' => $this->unit,
            'aggregation' => $this->aggregation,
            'reset_period' => $this->reset_period,
            'overage_behavior' => $this->overage_behavior,
            'configuration' => $this->configuration,
        ];
    }
}
