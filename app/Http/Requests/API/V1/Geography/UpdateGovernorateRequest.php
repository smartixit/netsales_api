<?php

namespace App\Http\Requests\API\V1\Geography;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGovernorateRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->governorate_name)) {
            $this->merge(['governorate_name' => trim($this->governorate_name)]);
        }
    }

    public function rules(): array
    {
        $governorateId = $this->route('governorate')?->governorate_id ?? $this->route('governorate');

        return [
            'governorate_name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('governorates', 'governorate_name')
                    ->ignore($governorateId, 'governorate_id')
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
