<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPlanRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan_name' => is_string($this->plan_name) ? trim($this->plan_name) : $this->plan_name,
        ]);
    }

    public function rules(): array
    {
        return [
            'plan_name' => [
                'required',
                'string',
                'max:50',
            ],
            'plan_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'plan_discount' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],
            'plan_validity' => [
                'required',
                'integer',
                'min:1',
            ],
            'plan_desc' => [
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
            'features' => [
                'nullable',
                'array',
            ],
            'features.*.feature_id' => [
                'required_with:features',
                'integer',
                'exists:features,feature_id',
            ],
            'features.*.plan_feature_value' => [
                'required_with:features',
                'string',
                'max:50',
            ],
            'features.*.status' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function getPlanName(): string
    {
        return (string) $this->input('plan_name');
    }

    public function getPlanPrice(): float
    {
        return (float) $this->input('plan_price');
    }

    public function getPlanDiscount(): ?float
    {
        return $this->filled('plan_discount') ? (float) $this->input('plan_discount') : null;
    }

    public function getPlanValidity(): int
    {
        return (int) $this->input('plan_validity', 30);
    }

    public function getPlanDesc(): ?string
    {
        return $this->filled('plan_desc') ? (string) $this->input('plan_desc') : null;
    }

    public function getStatus(): ?bool
    {
        return $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN) : null;
    }

    public function getFeatures(): ?array
    {
        return $this->input('features');
    }
}
