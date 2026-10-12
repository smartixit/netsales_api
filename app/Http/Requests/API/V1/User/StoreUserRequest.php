<?php

namespace App\Http\Requests\API\V1\User;

use App\Enums\UserStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_full_name' => is_string($this->user_full_name) ? trim($this->user_full_name) : $this->user_full_name,
            'user_phone_number' => is_string($this->user_phone_number) ? trim($this->user_phone_number) : $this->user_phone_number,
            'user_email' => is_string($this->user_email) ? trim(strtolower($this->user_email)) : $this->user_email,
        ]);
    }

    public function rules(): array
    {
        return [
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'role_id')->whereNull('deleted_at'),
            ],
            'user_full_name' => [
                'required',
                'string',
                'max:100',
            ],
            'user_phone_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
                Rule::unique('users', 'user_phone_number')->whereNull('deleted_at'),
            ],
            'user_password' => [
                'required',
                'string',
                Password::min(8),
            ],
            'user_email' => [
                'nullable',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'user_email')->whereNull('deleted_at'),
            ],
            'user_profile_logo' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048',
            ],
            'user_2fa_enabled' => [
                'nullable',
                'boolean',
            ],
            'status' => [
                'nullable',
                Rule::enum(UserStatus::class),
            ],
        ];
    }
}
