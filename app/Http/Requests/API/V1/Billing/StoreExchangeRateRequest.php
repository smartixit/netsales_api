<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeRateRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base_currency_id' => ['required', 'integer', 'exists:currencies,currency_id'],
            'quote_currency_id' => ['required', 'integer', 'different:base_currency_id', 'exists:currencies,currency_id'],
            'rate_numerator' => ['required', 'integer', 'min:1'],
            'rate_denominator' => ['required', 'integer', 'min:1'],
            'source' => ['required', 'string', 'max:80'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
