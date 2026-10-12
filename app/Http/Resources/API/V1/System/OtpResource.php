<?php

namespace App\Http\Resources\API\V1\System;

use App\Enums\OtpType;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\Otp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Otp
 */
class OtpResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'otp_id' => $this->otp_id,
            'user_id' => $this->user_id,
            'otp_code' => $this->otp_code,
            'otp_type' => $this->otp_type instanceof OtpType ? $this->otp_type->value : $this->otp_type,
            'otp_type_label' => $this->otp_type instanceof OtpType
                ? $this->otp_type->label()
                : (OtpType::tryFrom((string) $this->otp_type)?->label()),
            'otp_failure_attempts' => (int) $this->otp_failure_attempts,
            'is_used' => (bool) $this->is_used,
            'expires_at' => $this->expires_at ? Carbon::parse($this->expires_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
