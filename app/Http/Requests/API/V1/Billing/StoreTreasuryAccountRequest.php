<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreTreasuryAccountRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'account_type' => ['required', 'in:BANK,CASH,E_WALLET,OTHER'],
            'currency_id' => ['required', 'integer', 'exists:currencies,currency_id'],
            'institution_name' => ['nullable', 'string', 'max:120'],
            'account_reference' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
