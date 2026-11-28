<?php

namespace App\Http\Requests\API\V1\Auth;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the limited, self-service profile update contract.
 */
class UpdateOwnProfileRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return $this->user() !== null;
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
            $data['user_email'] = mb_strtolower(trim($this->user_email));
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        $userId = (int) $this->user()->user_id;

        return [
            'user_full_name' => ['sometimes', 'required', 'string', 'max:100'],
            'user_phone_number' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[0-9+\\-\\s()]+$/',
                Rule::unique('users', 'user_phone_number')->ignore($userId, 'user_id')->whereNull('deleted_at'),
            ],
            'user_email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'user_email')->ignore($userId, 'user_id')->whereNull('deleted_at'),
            ],
        ];
    }
}
