<?php

namespace App\Http\Requests\API\V1\User;

use App\Enums\UserStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    UserStatus::ACTIVE->value,
                    UserStatus::INACTIVE->value,
                    UserStatus::PENDING->value,
                ]),
            ],
        ];
    }
}
