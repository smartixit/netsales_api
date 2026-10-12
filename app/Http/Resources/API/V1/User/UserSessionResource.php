<?php

namespace App\Http\Resources\API\V1\User;

use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserSession
 */
class UserSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'logged_at' => $this->logged_at ? Carbon::parse($this->logged_at)->toDateTimeString() : null,
            'last_activity_at' => $this->last_activity_at ? Carbon::parse($this->last_activity_at)->toDateTimeString() : null,
            'expires_at' => $this->expires_at ? Carbon::parse($this->expires_at)->toDateTimeString() : null,
            'revoked_at' => $this->revoked_at ? Carbon::parse($this->revoked_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
