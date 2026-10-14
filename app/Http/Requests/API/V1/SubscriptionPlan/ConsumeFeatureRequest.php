<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class ConsumeFeatureRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'feature_name' => [
                'required',
                'string',
                'max:50',
            ],
            'amount' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    public function getFeatureName(): string
    {
        return (string) $this->input('feature_name');
    }

    public function getAmount(): int
    {
        return (int) $this->input('amount', 1);
    }
}
