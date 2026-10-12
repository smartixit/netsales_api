<?php

namespace App\Http\Resources\API\V1\Pos;

use App\Enums\PosStatus;
use App\Http\Resources\API\V1\Geography\DirectorateResource;
use App\Models\Pos;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pos
 */
class PosResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pos_id' => $this->pos_id,
            'directorate_id' => $this->directorate_id,
            'pos_name' => $this->pos_name,
            'pos_phone' => $this->pos_phone,
            'pos_address' => $this->pos_address,
            'pos_lat' => $this->pos_lat !== null ? (float) $this->pos_lat : null,
            'pos_lng' => $this->pos_lng !== null ? (float) $this->pos_lng : null,
            'status' => $this->status instanceof PosStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof PosStatus
                ? $this->status->label()
                : (PosStatus::tryFrom((string) $this->status)?->label()),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'directorate' => $this->whenLoaded('directorate', fn () => new DirectorateResource($this->directorate)),
            'wallets' => $this->whenLoaded('wallets', fn () => PosWalletResource::collection($this->wallets)),
        ];
    }
}
