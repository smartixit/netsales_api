<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanFeatureRequest extends FormRequest
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
        $featureId = $this->route('subscription_plan_feature')?->subscription_plan_feature_id
            ?? $this->route('subscription_plan_feature')
            ?? $this->route('id');

        return [
            'plan_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('subscription_plans', 'plan_id')->whereNull('deleted_at'),
            ],
            'feature_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('features', 'feature_id')->whereNull('deleted_at'),
                Rule::unique('subscription_plan_features', 'feature_id')
                    ->where('plan_id', $this->plan_id ?? $this->route('subscription_plan_feature')?->plan_id)
                    ->ignore($featureId, 'subscription_plan_feature_id')
                    ->whereNull('deleted_at'),
            ],
            'plan_feature_value' => [
                'sometimes',
                'required',
                'string',
                'max:50',
            ],
            'status' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }
}
