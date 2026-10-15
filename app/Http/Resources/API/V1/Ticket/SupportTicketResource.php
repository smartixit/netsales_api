<?php

namespace App\Http\Resources\API\V1\Ticket;

use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportTicket
 */
class SupportTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_id' => $this->ticket_id,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'ticket_uuid' => $this->ticket_uuid,
            'ticket_subject' => $this->ticket_subject,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'pos' => $this->whenLoaded('pos', fn () => new PosResource($this->pos)),
            'messages' => $this->whenLoaded('messages', fn () => SupportTicketMessageResource::collection($this->messages)),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => new SupportTicketMessageResource($this->latestMessage)),
        ];
    }
}
