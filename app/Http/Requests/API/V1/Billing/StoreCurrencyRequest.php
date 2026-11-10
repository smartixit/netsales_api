<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'numeric_code' => ['nullable', 'digits:3'],
            'name' => ['required', 'string', 'max:80'],
            'native_name' => ['nullable', 'string', 'max:80'],
            'symbol' => ['nullable', 'string', 'max:12'],
            'decimal_places' => ['required', 'integer', 'between:0,4'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
