<?php

namespace App\Http\Requests\API\V1\Network;

use App\Enums\WalletOperationType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNetworkWalletLogRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wallet_id' => [
                'required',
                'integer',
                Rule::exists('network_wallets', 'wallet_id')->whereNull('deleted_at'),
            ],
            'log_operation' => [
                'required',
                Rule::enum(WalletOperationType::class),
            ],
            'log_amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'log_statement' => [
                'required',
                'string',
                'max:255',
            ],
            'log_date' => [
                'nullable',
                'date',
            ],
        ];
    }
}
