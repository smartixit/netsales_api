<?php

namespace App\Http\Resources\API\V1\ActionType;

use App\Http\Resources\API\V1\RBAC\PermissionResource;
use App\Models\ActionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActionType
 */
class ActionTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'action_type_id' => $this->action_type_id,
            'action_type_key' => $this->action_type_key,
            'action_type_name' => $this->action_type_name,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'permissions' => $this->whenLoaded('permissions', fn () => PermissionResource::collection($this->permissions)),
        ];
    }
}
