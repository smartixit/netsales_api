<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Services\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $places = (int) ($currency?->decimal_places ?? 2);
        $code = (string) ($currency?->code ?? 'XXX');

        return [
            'invoice_line_id' => $this->invoice_line_id,
            'line_type' => $this->line_type,
            'description' => $this->description,
            'quantity' => (string) $this->quantity,
            'currency' => new CurrencyResource($currency),
            'unit_amount_minor' => (int) $this->unit_amount_minor,
            'unit_amount' => (new Money((int) $this->unit_amount_minor, $code))->toDecimalString($places),
            'subtotal_minor' => (int) $this->subtotal_minor,
            'discount_minor' => (int) $this->discount_minor,
            'tax_minor' => (int) $this->tax_minor,
            'total_minor' => (int) $this->total_minor,
            'source_snapshot' => $this->source_snapshot,
        ];
    }
}
