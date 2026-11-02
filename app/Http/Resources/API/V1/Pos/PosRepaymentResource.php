<?php

namespace App\Http\Resources\API\V1\Pos;

use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Models\PosRepayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosRepayment
 */
class PosRepaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'repayment_id' => $this->repayment_id,
            'repayment_uuid' => $this->repayment_uuid,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'repayment_date' => $this->repayment_date?->toIso8601String(),
            'status' => $this->status,
            'created_by' => $this->created_by,
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversed_by' => $this->reversed_by,
            'reversal_reason' => $this->reversal_reason,
            'reversal_repayment_id' => $this->reversal_repayment_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'allocations' => PosRepaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'network' => new NetworkResource($this->whenLoaded('network')),
            'pos' => new PosResource($this->whenLoaded('pos')),
        ];
    }
}

