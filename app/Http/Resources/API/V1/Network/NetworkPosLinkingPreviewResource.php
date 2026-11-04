<?php

namespace App\Http\Resources\API\V1\Network;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NetworkPosLinkingPreviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'challenge_uuid' => $this->resource['challenge_uuid'],
            'network' => $this->resource['network'],
            'pos' => $this->resource['pos'],
            'expires_at' => $this->resource['expires_at'],
            'is_targeted' => $this->resource['is_targeted'],
        ];
    }
}
