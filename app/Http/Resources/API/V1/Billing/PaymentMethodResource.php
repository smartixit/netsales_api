<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_method_id' => $this->payment_method_id,
            'method_key' => $this->method_key,
            'name' => $this->name,
            'method_type' => $this->method_type,
            'requires_proof' => (bool) $this->requires_proof,
            'is_active' => (bool) $this->is_active,
            'instructions' => $this->instructions,
            'currencies' => CurrencyResource::collection($this->whenLoaded('currencies')),
        ];
    }
}
