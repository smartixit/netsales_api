<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanFeatureConsumptionRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subscription_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('provider_subscriptions', 'subscription_id')->whereNull('deleted_at'),
            ],
            'feature_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('subscription_plan_features', 'subscription_plan_feature_id')->whereNull('deleted_at'),
            ],
            'consumption_value' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
            'last_consumption_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
