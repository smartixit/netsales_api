<?php

namespace App\Http\Requests\API\V1\SubscriptionPlan;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class ExtendSubscriptionRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'additional_days' => [
                'required',
                'integer',
                'min:1',
                'max:3650',
            ],
        ];
    }

    public function getAdditionalDays(): int
    {
        return (int) $this->input('additional_days');
    }
}
