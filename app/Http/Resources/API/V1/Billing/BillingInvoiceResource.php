<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Services\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $places = (int) ($currency?->decimal_places ?? 2);
        $code = (string) ($currency?->code ?? 'XXX');

        return [
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice_number,
            'billing_account_id' => $this->billing_account_id,
            'billing_subscription_id' => $this->billing_subscription_id,
            'invoice_type' => $this->invoice_type,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'currency' => new CurrencyResource($currency),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'issued_on' => $this->issued_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'subtotal_minor' => (int) $this->subtotal_minor,
            'subtotal' => (new Money((int) $this->subtotal_minor, $code))->toDecimalString($places),
            'discount_minor' => (int) $this->discount_minor,
            'discount' => (new Money((int) $this->discount_minor, $code))->toDecimalString($places),
            'tax_minor' => (int) $this->tax_minor,
            'tax' => (new Money((int) $this->tax_minor, $code))->toDecimalString($places),
            'total_minor' => (int) $this->total_minor,
            'total' => (new Money((int) $this->total_minor, $code))->toDecimalString($places),
            'paid_minor' => (int) $this->paid_minor,
            'credited_minor' => (int) $this->credited_minor,
            'paid' => (new Money((int) $this->paid_minor, $code))->toDecimalString($places),
            'remaining_minor' => (int) $this->remaining_minor,
            'remaining' => (new Money((int) $this->remaining_minor, $code))->toDecimalString($places),
            'customer_snapshot' => $this->customer_snapshot,
            'pricing_snapshot' => $this->pricing_snapshot,
            'memo' => $this->memo,
            'lines' => BillingInvoiceLineResource::collection($this->whenLoaded('lines')),
            'credit_notes' => $this->whenLoaded('creditNotes', fn () => $this->creditNotes->map(fn ($note) => [
                'credit_note_id' => $note->credit_note_id,
                'credit_note_number' => $note->credit_note_number,
                'status' => $note->status,
                'total_minor' => (int) $note->total_minor,
                'applied_to_receivable_minor' => (int) $note->applied_to_receivable_minor,
                'refundable_minor' => (int) $note->refundable_minor,
                'refunded_minor' => (int) $note->refunded_minor,
                'issued_at' => $note->issued_at?->toDateTimeString(),
            ])),
            'created_at' => $this->created_at?->toDateTimeString(),
            'finalized_at' => $this->finalized_at?->toDateTimeString(),
            'paid_at' => $this->paid_at?->toDateTimeString(),
        ];
    }
}
