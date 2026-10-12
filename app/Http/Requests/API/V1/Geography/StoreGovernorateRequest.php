<?php

namespace App\Http\Requests\API\V1\Geography;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGovernorateRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'governorate_name' => is_string($this->governorate_name) ? trim($this->governorate_name) : $this->governorate_name,
        ]);
    }

    public function rules(): array
    {
        return [
            'governorate_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('governorates', 'governorate_name')->whereNull('deleted_at'),
            ],
        ];
    }
}
