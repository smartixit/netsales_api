<?php

namespace App\Http\Resources\API\V1\RBAC;

use App\Http\Resources\API\V1\User\UserResource;
use App\Models\UserPermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserPermission
 */
class UserPermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_permission_id' => $this->user_permission_id,
            'user_id' => $this->user_id,
            'permission_id' => $this->permission_id,
            'created_at' => $this->created_at? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'permission' => $this->whenLoaded('permission', fn () => new PermissionResource($this->permission)),
        ];
    }
}
