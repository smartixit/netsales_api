<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Services\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $places = (int) ($currency?->decimal_places ?? 2);
        $code = (string) ($currency?->code ?? 'XXX');

        return [
            'payment_id' => $this->payment_id,
            'payment_reference' => $this->payment_reference,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_number),
            'billing_account_id' => $this->billing_account_id,
            'currency' => new CurrencyResource($currency),
            'amount_minor' => (int) $this->amount_minor,
            'amount' => (new Money((int) $this->amount_minor, $code))->toDecimalString($places),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => [
                'payment_method_id' => $this->paymentMethod?->payment_method_id,
                'method_key' => $this->paymentMethod?->method_key,
                'name' => $this->paymentMethod?->name,
            ]),
            'external_reference' => $this->external_reference,
            'received_at' => $this->received_at?->toDateTimeString(),
            'verified_at' => $this->verified_at?->toDateTimeString(),
            'review_note' => $this->review_note,
            'proofs' => BillingPaymentProofResource::collection($this->whenLoaded('proofs')),
        ];
    }
}
