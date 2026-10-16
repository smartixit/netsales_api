<?php

namespace App\Http\Resources\API\V1\Pos;

use App\Enums\PosStatus;
use App\Http\Resources\API\V1\Geography\DirectorateResource;
use App\Http\Resources\API\V1\Geography\GovernorateResource;
use App\Http\Resources\API\V1\User\UserResource;
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
            'owner_id' => $this->owner_id,
            'pos_uuid' => $this->pos_uuid,
            'pos_name' => $this->pos_name,
            'pos_profile_logo' => $this->pos_profile_logo,
            'pos_holder_name' => $this->owner->user_full_name,
            'pos_phone_number' => $this->pos_phone_number,
            'pos_email' => $this->pos_email,
            'governorate_id' => $this->governorate_id,
            'directorate_id' => $this->directorate_id,
            'pos_address' => $this->pos_address,
            'approved_by' => $this->approved_by,
            'rejected_by' => $this->rejected_by,
            'approved_at' => $this->approved_at ? Carbon::parse($this->approved_at)->toDateTimeString() : null,
            'rejected_at' => $this->rejected_at ? Carbon::parse($this->rejected_at)->toDateTimeString() : null,
            'rejection_reason' => $this->rejection_reason,
            'status' => $this->status instanceof PosStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof PosStatus
                ? $this->status->label()
                : (PosStatus::tryFrom((string) $this->status)?->label()),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'owner' => $this->whenLoaded('owner', fn () => new UserResource($this->owner)),
            'governorate' => $this->whenLoaded('governorate', fn () => new GovernorateResource($this->governorate)),
            'directorate' => $this->whenLoaded('directorate', fn () => new DirectorateResource($this->directorate)),
            'approved_by_user' => $this->whenLoaded('approvedBy', fn () => new UserResource($this->approvedBy)),
            'rejected_by_user' => $this->whenLoaded('rejectedBy', fn () => new UserResource($this->rejectedBy)),
            'wallets' => $this->whenLoaded('wallets', fn () => PosWalletResource::collection($this->wallets)),
        ];
    }
}
