<?php

namespace App\Http\Resources\API\V1\SubscriptionPlan;

use App\Http\Resources\API\V1\User\UserResource;
use App\Models\SubscriptionPlanFeature;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlanFeature
 */
class SubscriptionPlanFeatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'subscription_plan_feature_id' => $this->subscription_plan_feature_id,
            'plan_id' => $this->plan_id,
            'feature_id' => $this->feature_id,
            'plan_feature_value' => $this->plan_feature_value,
            'status' => (bool) $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
            'feature' => $this->whenLoaded('feature', fn () => new FeatureResource($this->feature)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
        ];
    }
}
