<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyConversionRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exchange_rate_id' => ['required', 'integer', 'exists:currency_exchange_rates,exchange_rate_id'],
            'source_amount_minor' => ['required', 'integer', 'min:1'],
            'fee_amount_minor' => ['sometimes', 'integer', 'min:0'],
            'source_type' => ['required', 'string', 'max:80'],
            'source_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
