<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

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
        $this->merge([
            'feature_name' => is_string($this->feature_name) ? trim($this->feature_name) : $this->feature_name,
            'feature_value' => is_string($this->feature_value) ? trim($this->feature_value) : $this->feature_value,
            'feature_desc' => is_string($this->feature_desc) ? trim($this->feature_desc) : $this->feature_desc,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $featureId = $this->route('feature')?->feature_id ?? $this->route('feature') ?? $this->route('id');

        return [
            'feature_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('features', 'feature_name')
                    ->ignore($featureId, 'feature_id')
                    ->whereNull('deleted_at'),
            ],
            'feature_value_type' => [
                'sometimes',
                'required',
                Rule::enum(ValueType::class),
            ],
            'feature_value' => [
                'nullable',
                'string',
                'max:255',
            ],
            'feature_desc' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }
}
