<?php

namespace App\Http\Resources\API\V1\Notification;

use App\Enums\NotificationType;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'notification_id' => $this->notification_id,
            'user_id' => $this->user_id,
            'notification_type' => $this->notification_type instanceof NotificationType ? $this->notification_type->value : $this->notification_type,
            'notification_type_label' => $this->notification_type instanceof NotificationType
                ? $this->notification_type->label()
                : (NotificationType::tryFrom((string) $this->notification_type)?->label()),
            'notification_title' => $this->notification_title,
            'notification_message' => $this->notification_message,
            'notification_image' => $this->notification_image,
            'is_read' => (bool) $this->is_read,
            'read_at' => $this->read_at ? Carbon::parse($this->read_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
