<?php

namespace App\Http\Requests\API\V1\Geography;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDirectorateRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'directorate_name' => is_string($this->directorate_name) ? trim($this->directorate_name) : $this->directorate_name,
        ]);
    }

    public function rules(): array
    {
        return [
            'governorate_id' => [
                'required',
                'integer',
                Rule::exists('governorates', 'governorate_id')->whereNull('deleted_at'),
            ],
            'directorate_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('directorates', 'directorate_name')
                    ->where('governorate_id', $this->governorate_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function getGovernorateId(): int
    {
        return (int) $this->governorate_id;
    }

    public function getDirectorateName(): string
    {
        return (string) $this->directorate_name;
    }
}
