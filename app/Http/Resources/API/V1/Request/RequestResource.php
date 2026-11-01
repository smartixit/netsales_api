<?php

namespace App\Http\Resources\API\V1\Request;

use App\Enums\RequestStatus;
use App\Enums\SaleType;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Http\Resources\API\V1\User\UserResource;
use App\Models\Request as RequestModel;
use Carbon\Carbon;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestModel
 */
class RequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(HttpRequest $request): array
    {
        return [
            'request_id' => $this->request_id,
            'network_id' => $this->network_id,
            'pos_id' => $this->pos_id,
            'request_uuid' => $this->request_uuid,
            'sale_type' => $this->sale_type instanceof SaleType ? $this->sale_type->value : $this->sale_type,
            'sale_type_label' => $this->sale_type instanceof SaleType
                ? $this->sale_type->label()
                : (SaleType::tryFrom((string) $this->sale_type)?->label()),
            'request_total_amount' => (float) $this->request_total_amount,
            'request_date' => $this->request_date ? Carbon::parse($this->request_date)->toDateTimeString() : null,
            'status' => $this->status instanceof RequestStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof RequestStatus
                ? $this->status->label()
                : (RequestStatus::tryFrom((string) $this->status)?->label()),
            'created_by' => $this->created_by,
            'reviewed_by' => $this->reviewed_by,
            'review_date' => $this->review_date ? Carbon::parse($this->review_date)->toDateTimeString() : null,
            'request_note' => $this->request_note,
            'invoice_id' => $this->invoice?->invoice_id,
            'invoice_uuid' => $this->invoice?->invoice_uuid,
            'modifications_summary' => $this->when($this->relationLoaded('invoice') || $this->invoice !== null, function () {
                $invoice = $this->invoice;
                if (! $invoice) {
                    return null;
                }
                $invoiceItems = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->get();
                $originalItems = $this->relationLoaded('items') ? $this->items : $this->items()->get();

                $origMap = $originalItems->keyBy('package_id');
                $invMap = $invoiceItems->keyBy('package_id');

                $hasChanges = round((float) $invoice->invoice_total_amount, 2) !== round((float) $this->request_total_amount, 2)
                    || $originalItems->count() !== $invoiceItems->count();

                if (! $hasChanges) {
                    foreach ($originalItems as $orig) {
                        $inv = $invMap->get($orig->package_id);
                        if (! $inv || (int) $inv->voucher_count !== (int) $orig->voucher_count) {
                            $hasChanges = true;
                            break;
                        }
                    }
                }

                return [
                    'has_modifications' => $hasChanges,
                    'original_total' => (float) $this->request_total_amount,
                    'approved_total' => (float) $invoice->invoice_total_amount,
                    'original_items_count' => $originalItems->count(),
                    'approved_items_count' => $invoiceItems->count(),
                ];
            }),
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,

            // Eager Loaded Relationships
            'network' => $this->whenLoaded('network', fn () => new NetworkResource($this->network)),
            'pos' => $this->whenLoaded('pos', fn () => new PosResource($this->pos)),
            'created_by_user' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'reviewed_by_user' => $this->whenLoaded('reviewedBy', fn () => new UserResource($this->reviewedBy)),
            'items' => $this->whenLoaded('items', fn () => RequestItemResource::collection($this->items)),
        ];
    }
}
