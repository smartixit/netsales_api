<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Services\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $places = (int) ($currency?->decimal_places ?? 2);
        $code = (string) ($currency?->code ?? 'XXX');

        return [
            'price_id' => $this->price_id,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'unit_amount_minor' => (int) $this->unit_amount_minor,
            'unit_amount' => (new Money((int) $this->unit_amount_minor, $code))->toDecimalString($places),
            'setup_fee_minor' => (int) $this->setup_fee_minor,
            'setup_fee' => (new Money((int) $this->setup_fee_minor, $code))->toDecimalString($places),
            'billing_interval' => $this->billing_interval?->value,
            'billing_interval_label' => $this->billing_interval?->label(),
            'interval_count' => (int) $this->interval_count,
            'tax_behavior' => $this->tax_behavior,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
