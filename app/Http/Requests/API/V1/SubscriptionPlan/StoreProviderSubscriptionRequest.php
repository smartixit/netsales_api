<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Enums\ProviderSubscriptionStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderSubscriptionRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'plan_id')->whereNull('deleted_at'),
            ],
            'subscription_start_date' => [
                'required',
                'date',
            ],
            'subscription_end_date' => [
                'required',
                'date',
                'after_or_equal:subscription_start_date',
            ],
            'status' => [
                'nullable',
                Rule::enum(ProviderSubscriptionStatus::class),
            ],
            'created_by' => [
                'nullable',
                'integer',
                Rule::exists('users', 'user_id')->whereNull('deleted_at'),
            ],
        ];
    }
}
