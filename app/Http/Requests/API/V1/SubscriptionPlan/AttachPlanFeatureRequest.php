<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class AttachPlanFeatureRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feature_id' => [
                'required',
                'integer',
                'exists:features,feature_id',
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
        ];
    }

    public function getFeatureId(): int
    {
        return (int) $this->input('feature_id');
    }

    public function getPlanFeatureValue(): string
    {
        return (string) $this->input('plan_feature_value');
    }

    public function getStatus(): ?bool
    {
        return $this->has('status') ? filter_var($this->input('status'), FILTER_VALIDATE_BOOLEAN) : null;
    }
}
