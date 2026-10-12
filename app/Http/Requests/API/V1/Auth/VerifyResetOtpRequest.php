<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class VerifyResetOtpRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
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
            'otp' => ['required', 'string', 'digits:6'],
        ];
    }
}
