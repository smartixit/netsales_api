<?php

namespace App\Http\Requests\API\V1\BankAccount;

use App\Enums\BankAccountType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankAccountRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if (is_string($this->bank_name)) {
            $data['bank_name'] = trim($this->bank_name);
        }
        if (is_string($this->account_holder_name)) {
            $data['account_holder_name'] = trim($this->account_holder_name);
        }
        if (is_string($this->account_number)) {
            $data['account_number'] = trim($this->account_number);
        }
        if (! empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $accountId = $this->route('bank_account')?->account_id ?? $this->route('bank_account');

        return [
            'bank_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'account_holder_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'account_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('bank_accounts', 'account_number')
                    ->ignore($accountId, 'account_id')
                    ->whereNull('deleted_at'),
            ],
            'account_type' => [
                'sometimes',
                'required',
                Rule::enum(BankAccountType::class),
            ],
            'account_currency' => [
                'sometimes',
                'required',
                'string',
                'max:10',
            ],
            'bank_logo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
