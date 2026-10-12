<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->plan_name)) {
            $this->merge(['plan_name' => trim($this->plan_name)]);
        }
    }

    public function rules(): array
    {
        return [
            'plan_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'plan_price' => [
                'sometimes',
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
                'sometimes',
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
                'sometimes',
                'boolean',
            ],
        ];
    }
}
