<?php

namespace App\Http\Requests\API\V1\System;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOtpRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_used' => [
                'sometimes',
                'boolean',
            ],
            'otp_failure_attempts' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ];
    }
}
