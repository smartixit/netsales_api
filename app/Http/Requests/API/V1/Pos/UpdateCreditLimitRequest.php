<?php

namespace App\Http\Requests\API\V1\Pos;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditLimitRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }

    public function getCreditLimit(): ?float
    {
        $value = $this->input('credit_limit');

        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
