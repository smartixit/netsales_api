<?php

namespace App\Http\Resources\API\V1\Request;

use App\Enums\RequestStatus;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\Request as RequestModel;
use Carbon\Carbon;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestModel
 */
class RequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(HttpRequest $request): array
    {
        return [
            'request_id' => $this->request_id,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'request_uuid' => $this->request_uuid,
            'request_total_amount' => (float) $this->request_total_amount,
            'request_date' => $this->request_date ? Carbon::parse($this->request_date)->toDateTimeString() : null,
            'status' => $this->status instanceof RequestStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof RequestStatus
                ? $this->status->label()
                : (RequestStatus::tryFrom((string) $this->status)?->label()),
            'created_by' => $this->created_by,
            'reviewed_by' => $this->reviewed_by,
            'review_date' => $this->review_date ? Carbon::parse($this->review_date)->toDateTimeString() : null,
            'request_note' => $this->request_note,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'pos' => $this->whenLoaded('pos', fn () => new PosResource($this->pos)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'reviewed_by_user' => $this->whenLoaded('reviewedBy', fn () => new UserResource($this->reviewedBy)),
            'items' => $this->whenLoaded('items', fn () => RequestItemResource::collection($this->items)),
        ];
    }
}
