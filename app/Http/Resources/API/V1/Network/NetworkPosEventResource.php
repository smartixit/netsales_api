<?php

namespace App\Http\Resources\API\V1\Network;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkPosEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->event_id,
            'network_pos_id' => $this->network_pos_id,
            'event_type' => $this->event_type,
            'payload' => $this->payload,
            'actor' => $this->whenLoaded('actor', fn () => [
                'user_id' => $this->actor?->user_id,
                'user_full_name' => $this->actor?->user_full_name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
