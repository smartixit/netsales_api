<?php

namespace App\Http\Requests\API\V1\SubscriptionRequest;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class RejectSubscriptionPlanRequestRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => [
                'required',
                'string',
                'max:150',
            ],
            'request_note' => [
                'nullable',
                'string',
                'max:150',
            ],
        ];
    }

    public function getReason(): string
    {
        return (string) $this->input('rejection_reason');
    }

    public function getNote(): ?string
    {
        return $this->filled('request_note') ? (string) $this->input('request_note') : null;
    }
}
