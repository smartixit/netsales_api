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
            'note' => [
                'nullable',
                'string',
                'max:150',
            ],
        ];
    }

    public function getNote(): ?string
    {
        return $this->filled('note') ? (string) $this->input('note') : null;
    }
}
