<?php

namespace App\Http\Resources\API\V1\SubscriptionPlan;

use App\Models\SubscriptionPlanFeatureConsumption;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlanFeatureConsumption
 */
class SubscriptionPlanFeatureConsumptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'consumption_id' => $this->consumption_id,
            'subscription_id' => $this->subscription_id,
            'feature_id' => $this->feature_id,
            'consumption_value' => $this->consumption_value,
            'last_consumption_at' => $this->last_consumption_at ? Carbon::parse($this->last_consumption_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'plan_feature' => $this->whenLoaded('planFeature', fn () => new SubscriptionPlanFeatureResource($this->planFeature)),
        ];
    }
}
