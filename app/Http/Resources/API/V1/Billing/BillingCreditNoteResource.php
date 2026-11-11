<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Services\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingCreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $places = (int) ($currency?->decimal_places ?? 2);
        $code = (string) ($currency?->code ?? 'XXX');

        return [
            'credit_note_id' => $this->credit_note_id,
            'credit_note_number' => $this->credit_note_number,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'status' => $this->status,
            'reason' => $this->reason,
            'accounting_treatment' => $this->accounting_treatment,
            'currency' => new CurrencyResource($currency),
            'subtotal_minor' => (int) $this->subtotal_minor,
            'tax_minor' => (int) $this->tax_minor,
            'total_minor' => (int) $this->total_minor,
            'total' => (new Money((int) $this->total_minor, $code))->toDecimalString($places),
            'applied_to_receivable_minor' => (int) $this->applied_to_receivable_minor,
            'refundable_minor' => (int) $this->refundable_minor,
            'refunded_minor' => (int) $this->refunded_minor,
            'issued_at' => $this->issued_at?->toDateTimeString(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'credit_note_line_id' => $line->credit_note_line_id,
                'invoice_line_id' => $line->invoice_line_id,
                'description' => $line->description,
                'quantity_micros' => (int) $line->quantity_micros,
                'amount_minor' => (int) $line->amount_minor,
            ])->values()),
        ];
    }
}
