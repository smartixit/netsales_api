<?php

namespace App\Http\Requests\API\V1\User;

use App\Enums\UserStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if (is_string($this->user_full_name)) {
            $data['user_full_name'] = trim($this->user_full_name);
        }
        if (is_string($this->user_phone_number)) {
            $data['user_phone_number'] = trim($this->user_phone_number);
        }
        if (is_string($this->user_email)) {
            $data['user_email'] = trim(strtolower($this->user_email));
        }
        if (! empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->user_id ?? $this->route('user');

        return [
            'role_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('roles', 'role_id')->whereNull('deleted_at'),
            ],
            'user_full_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'user_phone_number' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
                Rule::unique('users', 'user_phone_number')
                    ->ignore($userId, 'user_id')
                    ->whereNull('deleted_at'),
            ],
            'user_password' => [
                'nullable',
                'string',
                Password::min(8),
            ],
            'user_email' => [
                'nullable',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'user_email')
                    ->ignore($userId, 'user_id')
                    ->whereNull('deleted_at'),
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
                'sometimes',
                Rule::enum(UserStatus::class),
            ],
        ];
    }
}
