<?php

namespace App\Http\Requests\API\V1\BankAccount;

use App\Enums\BankAccountType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBankAccountRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_name' => is_string($this->bank_name) ? trim($this->bank_name) : $this->bank_name,
            'account_holder_name' => is_string($this->account_holder_name) ? trim($this->account_holder_name) : $this->account_holder_name,
            'account_number' => is_string($this->account_number) ? trim($this->account_number) : $this->account_number,
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_name' => [
                'required',
                'string',
                'max:100',
            ],
            'account_holder_name' => [
                'required',
                'string',
                'max:100',
            ],
            'account_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('bank_accounts', 'account_number')->whereNull('deleted_at'),
            ],
            'account_type' => [
                'required',
                Rule::enum(BankAccountType::class),
            ],
            'account_currency' => [
                'required',
                'string',
                'max:10',
            ],
            'account_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'bank_logo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
