<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Enums\ProviderSubscriptionStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderSubscriptionRequest extends FormRequest
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
            'network_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'plan_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('subscription_plans', 'plan_id')->whereNull('deleted_at'),
            ],
            'subscription_start_date' => [
                'sometimes',
                'required',
                'date',
            ],
            'subscription_end_date' => [
                'sometimes',
                'required',
                'date',
                'after_or_equal:subscription_start_date',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::enum(ProviderSubscriptionStatus::class),
            ],
        ];
    }
}
