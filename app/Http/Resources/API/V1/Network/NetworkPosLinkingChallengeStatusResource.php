<?php

namespace App\Http\Resources\API\V1\Network;

use App\Models\NetworkPosLinkingChallenge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe lifecycle projection for an authorized network challenge.
 *
 * @mixin NetworkPosLinkingChallenge
 */
class NetworkPosLinkingChallengeStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'expires_at' => $this->expires_at
                ? Carbon::parse($this->expires_at)->toIso8601String()
                : null,
        ];
    }
}
