<?php

namespace App\Http\Requests\API\V1\Pos;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosWalletRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network_id' => [
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'pos_id' => [
                'required',
                'integer',
                Rule::exists('pos', 'pos_id')->whereNull('deleted_at'),
                Rule::unique('pos_wallets', 'pos_id')
                    ->where('network_id', $this->network_id)
                    ->whereNull('deleted_at'),
            ],
            'wallet_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
