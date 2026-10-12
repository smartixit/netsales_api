<?php

namespace App\Http\Requests\API\V1\System;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemLogRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_values' => [
                'nullable',
                'array',
            ],
        ];
    }
}
