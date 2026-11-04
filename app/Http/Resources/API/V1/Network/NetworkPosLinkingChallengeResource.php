<?php

namespace App\Http\Resources\API\V1\Network;

use App\Enums\LinkingChallengeStatus;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Models\NetworkPosLinkingChallenge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد تحويل بيانات تحدي ودعوة ربط نقطة البيع بالشبكة عبر رمز QR.
 *
 * @mixin NetworkPosLinkingChallenge
 */
class NetworkPosLinkingChallengeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'challenge_id' => $this->challenge_id,
            'challenge_uuid' => $this->challenge_uuid,
            'payload' => $this->payload_token,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'status' => $this->status instanceof LinkingChallengeStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof LinkingChallengeStatus
                ? $this->status->label()
                : (LinkingChallengeStatus::tryFrom((string) $this->status)?->label()),
            'expires_at' => $this->expires_at ? Carbon::parse($this->expires_at)->toDateTimeString() : null,
            'consumed_at' => $this->consumed_at ? Carbon::parse($this->consumed_at)->toDateTimeString() : null,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'pos' => $this->whenLoaded('pos', fn () => new PosResource($this->pos)),
        ];
    }
}
