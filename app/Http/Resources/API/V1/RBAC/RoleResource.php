<?php

namespace App\Http\Resources\API\V1\RBAC;

use App\Enums\RoleKey;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role_id' => $this->role_id,
            'role_key' => $this->role_key instanceof RoleKey ? $this->role_key->value : $this->role_key,
            'role_key_label' => $this->role_key instanceof RoleKey
                ? $this->role_key->label()
                : (RoleKey::tryFrom((string) $this->role_key)?->label()),
            'role_name' => $this->role_name,
            'role_desc' => $this->role_desc,
            'is_system' => (bool) $this->is_system,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'permissions' => $this->whenLoaded('permissions', fn () => PermissionResource::collection($this->permissions)),
        ];
    }
}
