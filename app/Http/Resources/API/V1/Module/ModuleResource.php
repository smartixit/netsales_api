<?php

namespace App\Http\Resources\API\V1\Module;

use App\Http\Resources\API\V1\RBAC\PermissionResource;
use App\Models\Module;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Module
 */
class ModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'module_id' => $this->module_id,
            'module_key' => $this->module_key,
            'module_name' => $this->module_name,
            'module_icon' => $this->module_icon,
            'module_desc' => $this->module_desc,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'permissions' => $this->whenLoaded('permissions', fn () => PermissionResource::collection($this->permissions)),
        ];
    }
}
