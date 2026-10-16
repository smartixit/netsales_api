<?php

namespace App\Http\Requests\API\V1\SubscriptionRequest;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class ApproveSubscriptionPlanRequestRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_note' => [
                'nullable',
                'string',
                'max:150',
            ],
        ];
    }

    public function getNote(): ?string
    {
        return $this->filled('request_note') ? (string) $this->input('request_note') : null;
    }
}
