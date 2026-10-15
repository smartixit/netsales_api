<?php

namespace App\Http\Resources\API\V1\SubscriptionPlan;

use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlan
 */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'plan_id' => $this->plan_id,
            'plan_uuid' => $this->plan_uuid,
            'plan_name' => $this->plan_name,
            'plan_price' => (float) $this->plan_price,
            'plan_discount' => (float) $this->plan_discount,
            'plan_validity' => (int) $this->plan_validity,
            'plan_desc' => $this->plan_desc,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'features' => $this->whenLoaded('features', fn () => FeatureResource::collection($this->features)),
            'plan_features' => $this->whenLoaded('planFeatures', fn () => SubscriptionPlanFeatureResource::collection($this->planFeatures)),
        ];
    }
}
