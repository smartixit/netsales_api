<?php

namespace App\Http\Resources\API\V1\User;

use App\Enums\DeviceType;
use App\Enums\UserDeviceStatus;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserDevice
 */
class UserDeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_id' => $this->device_id,
            'user_id' => $this->user_id,
            'device_uuid' => $this->device_uuid,
            'device_name' => $this->device_name,
            'device_type' => $this->device_type instanceof DeviceType ? $this->device_type->value : $this->device_type,
            'device_type_label' => $this->device_type instanceof DeviceType
                ? $this->device_type->label()
                : (DeviceType::tryFrom((string) $this->device_type)?->label()),
            'device_os' => $this->device_os,
            'device_ip' => $this->device_ip,
            'push_token' => $this->push_token,
            'is_primary' => (bool) $this->is_primary,
            'status' => $this->status instanceof UserDeviceStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof UserDeviceStatus
                ? $this->status->label()
                : (UserDeviceStatus::tryFrom((string) $this->status)?->label()),
            'last_activity_at' => $this->last_used_at ?? $this->last_activity_at
                ? Carbon::parse($this->last_used_at ?? $this->last_activity_at)->toDateTimeString()
                : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
