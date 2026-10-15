<?php

namespace App\Http\Resources\API\V1\BankAccount;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'account_id' => $this->account_id,
            'bank_name' => $this->bank_name,
            'bank_logo' => $this->bank_logo,
            'account_holder_name' => $this->account_holder_name,
            'account_number' => $this->account_number,
            'account_type' => $this->account_type instanceof BankAccountType ? $this->account_type->value : $this->account_type,
            'account_type_label' => $this->account_type instanceof BankAccountType
                ? $this->account_type->label()
                : (BankAccountType::tryFrom((string) $this->account_type)?->label()),
            'account_currency' => $this->account_currency,
            'account_balance' => (float) $this->account_balance,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->toDateTimeString() : null,
            
        ];
    }
}
