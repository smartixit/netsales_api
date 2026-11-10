<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'native_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'symbol' => ['sometimes', 'nullable', 'string', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
