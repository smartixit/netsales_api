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
            'account_currency' => is_string($this->account_currency) ? strtoupper(trim($this->account_currency)) : $this->account_currency,
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_name' => [
                'required',
                'string',
                'max:50',
            ],
            'account_holder_name' => [
                'required',
                'string',
                'max:50',
            ],
            'account_number' => [
                'required',
                'string',
                'max:50',
            ],
            'account_type' => [
                'required',
                Rule::enum(BankAccountType::class),
            ],
            'account_currency' => [
                'required',
                'string',
                'max:4',
            ],
            'account_balance' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'bank_logo' => [
                'nullable',
                'file',
                'mimes:jpeg,png,jpg,webp,svg',
                'max:4096',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function getBankName(): string
    {
        return (string) $this->input('bank_name');
    }

    public function getAccountHolderName(): string
    {
        return (string) $this->input('account_holder_name');
    }

    public function getAccountNumber(): string
    {
        return (string) $this->input('account_number');
    }

    public function getAccountType(): string
    {
        return (string) $this->input('account_type', BankAccountType::SAVING->value);
    }

    public function getAccountCurrency(): string
    {
        return (string) $this->input('account_currency', systemSetting('base_currency', 'YER'));
    }

    public function getAccountBalance(): ?float
    {
        return $this->filled('account_balance') ? (float) $this->input('account_balance') : null;
    }

    public function getStatus(): ?bool
    {
        return $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN) : null;
    }
}
