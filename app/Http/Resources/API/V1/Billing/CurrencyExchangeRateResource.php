<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyExchangeRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'exchange_rate_id' => $this->exchange_rate_id,
            'base_currency' => $this->whenLoaded('baseCurrency', fn () => new CurrencyResource($this->baseCurrency)),
            'quote_currency' => $this->whenLoaded('quoteCurrency', fn () => new CurrencyResource($this->quoteCurrency)),
            'rate_numerator' => (int) $this->rate_numerator,
            'rate_denominator' => (int) $this->rate_denominator,
            'rate' => $this->rate_numerator.'/'.$this->rate_denominator,
            'source' => $this->source,
            'effective_at' => $this->effective_at?->toDateTimeString(),
            'expires_at' => $this->expires_at?->toDateTimeString(),
        ];
    }
}
