<?php

namespace App\Http\Requests\API\V1\Auth;

use App\Enums\DeviceType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
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
            'user_password' => ['required', 'string', 'min:6'],
            'device_uuid' => ['required', 'string', 'max:255'],
            'fcm_token' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:150'],
            'device_type' => ['nullable', 'string', Rule::enum(DeviceType::class)],
            'app_hash' => ['nullable', 'string', 'max:20'],
        ];
    }
}
