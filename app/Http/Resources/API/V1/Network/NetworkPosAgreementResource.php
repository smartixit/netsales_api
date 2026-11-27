<?php

namespace App\Http\Resources\API\V1\Network;

use App\Models\NetworkPosAgreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NetworkPosAgreement
 */

class NetworkPosAgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'agreement_id' => $this->agreement_id,
            'network_pos_id' => $this->network_pos_id,
            'version' => $this->version,
            'title' => $this->title,
            'reference' => $this->reference,
            'terms' => $this->terms,
            'starts_at' => $this->starts_at?->format('Y-m-d H:i:s'),
            'ends_at' => $this->ends_at?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'validity_state' => $this->resource->resolveValidityState(),
            'created_by' => $this->created_by,
            'activated_by' => $this->activated_by,
            'activated_at' => $this->activated_at?->format('Y-m-d H:i:s'),
            'terminated_at' => $this->terminated_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
