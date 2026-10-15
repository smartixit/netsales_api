<?php

namespace App\Http\Resources\API\V1\User;

use App\Enums\UserStatus;
use App\Http\Resources\API\V1\RBAC\RoleResource;
use App\Http\Resources\API\V1\System\SecurityBanResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'role_id' => $this->role_id,
            'user_full_name' => $this->user_full_name,
            'user_profile_logo' => $this->user_profile_logo,
            'user_phone_number' => $this->user_phone_number,
            'user_email' => $this->user_email,
            'user_2fa_enabled' => (bool) $this->user_2fa_enabled,
            'user_login_attempts' => (int) $this->user_login_attempts,
            'status' => $this->status instanceof UserStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof UserStatus
                ? $this->status->label()
                : (UserStatus::tryFrom((string) $this->status)?->label()),
            'last_login_at' => $this->last_login_at ? Carbon::parse($this->last_login_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'role' => $this->whenLoaded('role', fn () => new RoleResource($this->role)),
            'devices' => $this->whenLoaded('devices', fn () => UserDeviceResource::collection($this->devices)),
            'sessions' => $this->whenLoaded('sessions', fn () => UserSessionResource::collection($this->sessions)),
            'security_bans' => $this->whenLoaded('securityBans', fn () => SecurityBanResource::collection($this->securityBans)),
        ];
    }
}
