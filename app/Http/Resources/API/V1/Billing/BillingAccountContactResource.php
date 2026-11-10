<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingAccountContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'contact_id' => $this->contact_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_primary' => (bool) $this->is_primary,
            'receives_invoices' => (bool) $this->receives_invoices,
        ];
    }
}
