<?php

namespace App\Http\Resources\API\V1\SaleInvoice;

use App\Enums\InvoicePaymentStatus;
use App\Enums\PaymentType;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\SaleInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleInvoice
 */
class SaleInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_id' => $this->invoice_id,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'invoice_uuid' => $this->invoice_uuid,
            'invoice_sale_type' => $this->invoice_sale_type instanceof PaymentType ? $this->invoice_sale_type->value : $this->invoice_sale_type,
            'invoice_sale_type_label' => $this->invoice_sale_type instanceof PaymentType
                ? $this->invoice_sale_type->label()
                : (PaymentType::tryFrom((string) $this->invoice_sale_type)?->label()),
            'invoice_total_amount' => (float) $this->invoice_total_amount,
            'paid_amount' => (float) ($this->paid_amount ?? 0.0),
            'remaining_amount' => (float) ($this->remaining_amount ?? 0.0),
            'payment_status' => $this->payment_status instanceof InvoicePaymentStatus ? $this->payment_status->value : $this->payment_status,
            'payment_status_label' => $this->payment_status instanceof InvoicePaymentStatus
                ? $this->payment_status->label()
                : (InvoicePaymentStatus::tryFrom((string) $this->payment_status)?->label()),
            'is_fully_paid' => (float) ($this->remaining_amount ?? 0.0) <= 0.001,
            'invoice_date' => $this->invoice_date ? Carbon::parse($this->invoice_date)->toDateTimeString() : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'pos' => $this->whenLoaded('pos', fn () => new PosResource($this->pos)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'items' => $this->whenLoaded('items', fn () => SaleInvoiceItemResource::collection($this->items)),
        ];
    }
}
