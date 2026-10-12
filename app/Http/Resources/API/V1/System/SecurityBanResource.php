<?php

namespace App\Http\Resources\API\V1\System;

use App\Enums\BanType;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\SecurityBan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SecurityBan
 */
class SecurityBanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ban_id' => $this->ban_id,
            'user_id' => $this->user_id,
            'banned_by' => $this->banned_by,
            'ban_type' => $this->ban_type instanceof BanType ? $this->ban_type->value : $this->ban_type,
            'ban_type_label' => $this->ban_type instanceof BanType
                ? $this->ban_type->label()
                : (BanType::tryFrom((string) $this->ban_type)?->label()),
            'ban_reason' => $this->ban_reason,
            'ban_start_date' => $this->start_at ?? $this->ban_start_date
                ? Carbon::parse($this->start_at ?? $this->ban_start_date)->toDateTimeString()
                : null,
            'ban_end_date' => $this->end_at ?? $this->ban_end_date
                ? Carbon::parse($this->end_at ?? $this->ban_end_date)->toDateTimeString()
                : null,
            'is_permanent' => (bool) $this->is_permanent,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'banned_by_user' => $this->whenLoaded('bannedByUser', fn () => new UserResource($this->bannedByUser)),
        ];
    }
}
