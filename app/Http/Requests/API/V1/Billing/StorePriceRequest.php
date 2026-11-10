<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Enums\BillingInterval;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_id' => ['required', 'integer', 'exists:currencies,currency_id'],
            'billing_interval' => ['required', Rule::enum(BillingInterval::class)],
            'interval_count' => ['sometimes', 'integer', 'between:1,120'],
            'unit_amount' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'setup_fee' => ['sometimes', 'string', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'tax_behavior' => ['sometimes', 'in:INCLUSIVE,EXCLUSIVE'],
        ];
    }
}
