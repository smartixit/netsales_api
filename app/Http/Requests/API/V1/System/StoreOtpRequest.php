<?php

namespace App\Http\Requests\API\V1\System;

use App\Enums\OtpType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOtpRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'user_id')->whereNull('deleted_at'),
            ],
            'otp_code' => [
                'nullable',
                'string',
                'max:10',
            ],
            'otp_type' => [
                'required',
                Rule::enum(OtpType::class),
            ],
            'expires_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
