<?php

namespace App\Http\Resources\API\V1\System;

use App\Http\Resources\API\V1\User\UserResource;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SystemLog
 */
class SystemLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'log_id' => $this->log_id,
            'user_id' => $this->user_id,
            'log_level' => $this->log_level,
            'log_action' => $this->log_action,
            'log_message' => $this->log_message,
            'log_context' => $this->log_context,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
