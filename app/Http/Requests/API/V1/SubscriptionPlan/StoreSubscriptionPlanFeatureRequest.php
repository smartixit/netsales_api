<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanFeatureRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan_feature_value' => is_string($this->plan_feature_value) ? trim($this->plan_feature_value) : $this->plan_feature_value,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'plan_id')->whereNull('deleted_at'),
            ],
            'feature_id' => [
                'required',
                'integer',
                Rule::exists('features', 'feature_id')->whereNull('deleted_at'),
                Rule::unique('subscription_plan_features', 'feature_id')
                    ->where('plan_id', $this->plan_id)
                    ->whereNull('deleted_at'),
            ],
            'plan_feature_value' => [
                'required',
                'string',
                'max:50',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
            'created_by' => [
                'nullable',
                'integer',
                Rule::exists('users', 'user_id')->whereNull('deleted_at'),
            ],
        ];
    }
}
