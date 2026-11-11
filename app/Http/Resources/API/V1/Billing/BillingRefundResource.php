<?php

namespace App\\Http\\Resources\\API\\V1\\Billing;

use App\\Services\\Billing\\Money;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

class BillingRefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $places = (int) ($currency?->decimal_places ?? 2);
        $code = (string) ($currency?->code ?? 'XXX');

        return [
            'refund_id' => $this->refund_id,
            'refund_reference' => $this->refund_reference,
            'payment_id' => $this->payment_id,
            'payment_reference' => $this->whenLoaded('payment', fn () => $this->payment?->payment_reference),
            'invoice_id' => $this->whenLoaded('payment', fn () => $this->payment?->invoice_id),
            'invoice_number' => $this->whenLoaded('payment', fn () => $this->payment?->invoice?->invoice_number),
            'credit_note_number' => $this->whenLoaded('creditNote', fn () => $this->creditNote?->credit_note_number),
            'currency' => new CurrencyResource($currency),
            'amount_minor' => (int) $this->amount_minor,
            'amount' => (new Money((int) $this->amount_minor, $code))->toDecimalString($places),
            'status' => $this->status,
            'reason' => $this->reason,
            'processed_at' => $this->processed_at?->toDateTimeString(),
        ];
    }
}
