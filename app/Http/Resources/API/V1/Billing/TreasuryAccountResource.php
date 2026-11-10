<?php

namespace App\Http\Resources\API\V1\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreasuryAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'treasury_account_id' => $this->treasury_account_id,
            'account_code' => $this->account_code,
            'name' => $this->name,
            'account_type' => $this->account_type,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'institution_name' => $this->institution_name,
            'account_reference' => $this->account_reference,
            'status' => $this->status,
            'ledger_account_id' => $this->ledger_account_id,
            'ledger_account_code' => $this->whenLoaded('ledgerAccount', fn () => $this->ledgerAccount?->account_code),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
