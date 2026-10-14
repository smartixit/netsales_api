<?php

namespace App\Http\Requests\API\V1\Feature;

use App\Enums\ValueType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeatureRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'feature_name' => is_string($this->feature_name) ? trim($this->feature_name) : $this->feature_name,
            'feature_value' => is_string($this->feature_value) ? trim($this->feature_value) : $this->feature_value,
            'feature_desc' => is_string($this->feature_desc) ? trim($this->feature_desc) : $this->feature_desc,
        ]);
    }

    public function rules(): array
    {
        return [
            'feature_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('features', 'feature_name')->whereNull('deleted_at'),
            ],
            'feature_value_type' => [
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
                'nullable',
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
