<?php

namespace App\Http\Resources\API\V1\SubscriptionPlan;

use App\Enums\ProviderSubscriptionStatus;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\ProviderSubscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderSubscription
 */
class ProviderSubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'subscription_id' => $this->subscription_id,
            'network_id' => $this->network_id,
            'plan_id' => $this->plan_id,
            'subscription_start_date' => $this->subscription_start_date ? Carbon::parse($this->subscription_start_date)->toDateTimeString() : null,
            'subscription_end_date' => $this->subscription_end_date ? Carbon::parse($this->subscription_end_date)->toDateTimeString() : null,
            'status' => $this->status instanceof ProviderSubscriptionStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof ProviderSubscriptionStatus
                ? $this->status->label()
                : (ProviderSubscriptionStatus::tryFrom((string) $this->status)?->label()),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'consumptions' => $this->whenLoaded('consumptions', fn () => SubscriptionPlanFeatureConsumptionResource::collection($this->consumptions)),
        ];
    }
}
