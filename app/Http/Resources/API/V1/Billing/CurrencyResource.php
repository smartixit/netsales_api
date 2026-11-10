<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency_id' => $this->currency_id,
            'code' => $this->code,
            'numeric_code' => $this->numeric_code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'symbol' => $this->symbol,
            'decimal_places' => (int) $this->decimal_places,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
