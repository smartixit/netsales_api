<?php

namespace App\Http\Requests\API\V1\Feature;

use App\Enums\ValueType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->feature_name)) {
            $this->merge(['feature_name' => trim($this->feature_name)]);
        }
        if (is_string($this->feature_value)) {
            $this->merge(['feature_value' => trim($this->feature_value)]);
        }
        if (is_string($this->feature_desc)) {
            $this->merge(['feature_desc' => trim($this->feature_desc)]);
        }
    }

    public function rules(): array
    {
        return [
            'feature_name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],
            'feature_value_type' => [
                'sometimes',
                'required',
                Rule::enum(ValueType::class),
            ],
            'feature_value' => [
                'nullable',
                'string',
                'max:50',
            ],
            'feature_desc' => [
                'nullable',
                'string',
                'max:150',
            ],
            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function getFeatureName(): string
    {
        return (string) $this->input('feature_name');
    }

    public function getFeatureValueType(): string
    {
        return (string) $this->input('feature_value_type');
    }

    public function getFeatureValue(): ?string
    {
        return $this->filled('feature_value') ? (string) $this->input('feature_value') : null;
    }

    public function getFeatureDesc(): ?string
    {
        return $this->filled('feature_desc') ? (string) $this->input('feature_desc') : null;
    }

    public function getStatus(): ?bool
    {
        return $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN) : null;
    }
}
