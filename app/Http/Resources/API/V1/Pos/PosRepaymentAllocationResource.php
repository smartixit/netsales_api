<?php

namespace App\Http\Resources\API\V1\Pos;

use App\Http\Resources\API\V1\SaleInvoice\SaleInvoiceResource;
use App\Models\PosRepaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PosRepaymentAllocation
 */
class PosRepaymentAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'allocation_id' => $this->allocation_id,
            'repayment_id' => $this->repayment_id,
            'invoice_id' => $this->invoice_id,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'allocated_amount' => (float) $this->allocated_amount,
            'status' => $this->status,
            'allocated_at' => $this->allocated_at?->toIso8601String(),
            'invoice' => new SaleInvoiceResource($this->whenLoaded('invoice')),
        ];
    }
}
