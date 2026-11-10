<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Services\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyConversionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $from = $this->fromCurrency;
        $to = $this->toCurrency;
        $fromPlaces = (int) ($from?->decimal_places ?? 2);
        $toPlaces = (int) ($to?->decimal_places ?? 2);

        return [
            'conversion_id' => $this->conversion_id,
            'exchange_rate_id' => $this->exchange_rate_id,
            'from_currency' => new CurrencyResource($from),
            'to_currency' => new CurrencyResource($to),
            'source_amount_minor' => (int) $this->source_amount_minor,
            'source_amount' => (new Money((int) $this->source_amount_minor, (string) ($from?->code ?? 'XXX')))->toDecimalString($fromPlaces),
            'converted_amount_minor' => (int) $this->converted_amount_minor,
            'converted_amount' => (new Money((int) $this->converted_amount_minor, (string) ($to?->code ?? 'XXX')))->toDecimalString($toPlaces),
            'fee_amount_minor' => (int) $this->fee_amount_minor,
            'rounding_mode' => $this->rounding_mode,
            'rate_snapshot' => $this->rate_snapshot,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'converted_at' => $this->converted_at?->toDateTimeString(),
        ];
    }
}
