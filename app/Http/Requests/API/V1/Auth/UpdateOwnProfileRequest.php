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

    public function rules(): array
    {
        $userId = (int) $this->user()->user_id;

        return [
            'user_full_name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'user_phone_number' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('users', 'user_phone_number')->ignore($userId, 'user_id'),
            ],
            'user_email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'user_email')->ignore($userId, 'user_id'),
            ],
        ];
    }
}
