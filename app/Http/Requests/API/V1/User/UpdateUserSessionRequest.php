<?php

namespace App\Http\Requests\API\V1\User;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserSessionRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expires_at' => [
                'nullable',
                'date',
            ],
            'revoked_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
