<?php

namespace App\Http\Requests\API\V1\Debt;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class ReverseRepaymentRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reversal_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function getReversalReason(): string
    {
        return (string) $this->input('reversal_reason');
    }
}

