<?php

namespace App\Http\Requests\API\V1\Auth;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class ToggleTwoFactorRequest extends FormRequest
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
            'enable' => ['required', 'boolean'],
            'otp' => ['nullable', 'string', 'digits:6'],
            'current_password' => ['nullable', 'string', 'min:6'],
            'device_uuid' => ['nullable', 'string', 'max:255'],
            'app_hash' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $enable = filter_var($this->input('enable'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enable === true && empty($this->input('otp'))) {
                $validator->errors()->add('otp', 'رمز التحقق (OTP) مطلوب لتفعيل المصادقة الثنائية.');
            }
        });
    }
}
