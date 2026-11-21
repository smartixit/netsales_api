<?php

namespace App\Http\Resources\API\V1\Network;

use App\Enums\LinkingChallengeStatus;
use App\Models\NetworkPosLinkingChallenge;
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
        $status = $this->status instanceof LinkingChallengeStatus
            ? $this->status
            : LinkingChallengeStatus::tryFrom((string) $this->status);

        return [
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'network_name' => $this->network?->network_name,
            'pos_name' => $this->pos?->pos_name,
        ];
    }
}
