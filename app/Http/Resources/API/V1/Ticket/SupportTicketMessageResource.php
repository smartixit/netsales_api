<?php

namespace App\Http\Resources\API\V1\Ticket;

use App\Http\Resources\API\V1\User\UserResource;
use App\Models\SupportTicketMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportTicketMessage
 */
class SupportTicketMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message_id' => $this->message_id,
            'ticket_id' => $this->ticket_id,
            'sent_by' => $this->sent_by,
            'message_body' => $this->message_body,
            'sent_at' => $this->sent_at ? Carbon::parse($this->sent_at)->toDateTimeString() : null,
            'is_read' => (bool) $this->is_read,
            'read_at' => $this->read_at ? Carbon::parse($this->read_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'sent_by_user' => $this->whenLoaded('sentBy', fn () => new UserResource($this->sentBy)),
        ];
    }
}
