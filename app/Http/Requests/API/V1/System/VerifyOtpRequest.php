<?php

namespace App\Http\Requests\API\V1\System;

use App\Enums\OtpType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_phone_number' => [
                'required',
                'string',
                'min:9',
                'max:9',
                'regex:/^7[0-9]{8}$/',
            ],
            'otp' => [
                'required',
                'string',
                'min:4',
                'max:10',
            ],
            'type' => [
                'nullable',
                Rule::enum(OtpType::class),
            ],
        ];
    }
}
