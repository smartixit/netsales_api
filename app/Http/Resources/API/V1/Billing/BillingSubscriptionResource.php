<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'billing_subscription_id' => $this->billing_subscription_id,
            'billing_account_id' => $this->billing_account_id,
            'plan_id' => $this->plan_id,
            'plan_version_id' => $this->plan_version_id,
            'price_id' => $this->price_id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'collection_method' => $this->collection_method,
            'quantity' => (int) $this->quantity,
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
            'plan' => $this->whenLoaded('plan', fn () => [
                'plan_id' => $this->plan?->plan_id,
                'plan_key' => $this->plan?->plan_key,
                'name' => $this->plan?->name,
            ]),
            'terms_snapshot' => $this->terms_snapshot,
            'current_period_start' => $this->current_period_start?->toDateTimeString(),
            'current_period_end' => $this->current_period_end?->toDateTimeString(),
            'trial_start' => $this->trial_start?->toDateTimeString(),
            'trial_end' => $this->trial_end?->toDateTimeString(),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'grace_period_ends_at' => $this->grace_period_ends_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
