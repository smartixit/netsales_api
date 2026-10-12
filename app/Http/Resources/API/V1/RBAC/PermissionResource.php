<?php

namespace App\Http\Resources\API\V1\RBAC;

use App\Http\Resources\API\V1\ActionType\ActionTypeResource;
use App\Http\Resources\API\V1\Module\ModuleResource;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Permission
 */
class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'permission_id' => $this->permission_id,
            'module_id' => $this->module_id,
            'action_type_id' => $this->action_type_id,
            'permission_key' => $this->permission_key,
            'permission_name' => $this->permission_name,
            'permission_desc' => $this->permission_desc,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'module' => $this->whenLoaded('module', fn () => new ModuleResource($this->module)),
            'action_type' => $this->whenLoaded('actionType', fn () => new ActionTypeResource($this->actionType)),
        ];
    }
}
