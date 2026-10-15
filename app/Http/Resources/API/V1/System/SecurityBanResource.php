<?php

namespace App\Http\Resources\API\V1\System;

use App\Enums\BanType;
use App\Http\Resources\API\V1\User\UserDeviceResource;
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
            'device_id' => $this->device_id,
            'banned_by' => $this->banned_by,
            'ban_type' => $this->ban_type instanceof BanType ? $this->ban_type->value : $this->ban_type,
            'ban_type_label' => $this->ban_type instanceof BanType
                ? $this->ban_type->label()
                : (BanType::tryFrom((string) $this->ban_type)?->label()),
            'ban_reason' => $this->ban_reason,
            'start_at' => $this->start_at ? Carbon::parse($this->start_at)->toDateTimeString() : null,
            'end_at' => $this->end_at ? Carbon::parse($this->end_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'device' => $this->whenLoaded('device', fn () => new UserDeviceResource($this->device)),
            'banned_by_user' => $this->whenLoaded('bannedBy', fn () => new UserResource($this->bannedBy)),
        ];
    }
}
