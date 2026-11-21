<?php

namespace App\Http\Resources\API\V1\Network;

use App\Models\Pos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal network-scoped POS candidate projection.
 *
 * @mixin Pos
 */
class NetworkLinkablePosResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pos_id' => $this->pos_id,
            'pos_uuid' => $this->pos_uuid,
            'pos_name' => $this->pos_name,
        ];
    }
}
