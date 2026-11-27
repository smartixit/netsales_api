<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Models\BillingSubscriptionEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillingSubscriptionEvent
 */

class BillingSubscriptionEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'occurred_at' => $this->occurred_at?->toDateTimeString(),
        ];
    }
}
