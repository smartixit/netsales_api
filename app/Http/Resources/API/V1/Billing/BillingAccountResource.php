<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'billing_account_id' => $this->billing_account_id,
            'account_number' => $this->account_number,
            'network_id' => $this->network_id,
            'network_name' => $this->whenLoaded('network', fn () => $this->network?->network_name),
            'legal_name' => $this->legal_name,
            'billing_email' => $this->billing_email,
            'tax_number' => $this->tax_number,
            'billing_address' => $this->billing_address,
            'status' => $this->status,
            'default_currency' => new CurrencyResource($this->whenLoaded('defaultCurrency')),
            'payment_terms_days' => (int) $this->payment_terms_days,
            'contacts' => BillingAccountContactResource::collection($this->whenLoaded('contacts')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
