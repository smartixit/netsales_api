<?php

namespace App\Http\Resources\API\V1\Billing;

use App\Services\Billing\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerJournalLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currency = $this->currency;
        $places = (int) ($currency?->decimal_places ?? 2);
        $code = (string) ($currency?->code ?? 'XXX');

        return [
            'journal_line_id' => $this->journal_line_id,
            'ledger_account_id' => $this->ledger_account_id,
            'account_code' => $this->whenLoaded('account', fn () => $this->account?->account_code),
            'account_name' => $this->whenLoaded('account', fn () => $this->account?->name),
            'currency' => new CurrencyResource($currency),
            'debit_minor' => (int) $this->debit_minor,
            'debit' => (new Money((int) $this->debit_minor, $code))->toDecimalString($places),
            'credit_minor' => (int) $this->credit_minor,
            'credit' => (new Money((int) $this->credit_minor, $code))->toDecimalString($places),
            'description' => $this->description,
        ];
    }
}
