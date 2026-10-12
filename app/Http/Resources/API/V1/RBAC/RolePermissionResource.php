<?php

namespace App\Http\Resources\API\V1\RBAC;

use App\Models\RolePermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RolePermission
 */
class RolePermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role_permission_id' => $this->role_permission_id,
            'role_id' => $this->role_id,
            'permission_id' => $this->permission_id,
            'created_at' => $this->created_at? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'role' => $this->whenLoaded('role', fn () => new RoleResource($this->role)),
            'permission' => $this->whenLoaded('permission', fn () => new PermissionResource($this->permission)),
        ];
    }
}
