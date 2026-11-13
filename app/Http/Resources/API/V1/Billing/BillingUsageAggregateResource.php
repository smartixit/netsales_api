<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingUsageAggregateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'usage_aggregate_id' => $this->usage_aggregate_id,
            'billing_subscription_id' => $this->billing_subscription_id,
            'meter' => $this->whenLoaded('meter', fn () => [
                'meter_id' => $this->meter?->meter_id,
                'meter_key' => $this->meter?->meter_key,
                'name' => $this->meter?->name,
                'unit' => $this->meter?->unit,
                'aggregation' => $this->meter?->aggregation,
                'reset_period' => $this->meter?->reset_period,
            ]),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'quantity' => (string) $this->quantity,
            'amount_minor' => (int) $this->amount_minor,
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
            'last_event_at' => $this->last_event_at?->toDateTimeString(),
        ];
    }
}
