<?php

namespace App\Http\Requests\API\V1\User;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserSessionRequest extends FormRequest
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
            'ip_address' => [
                'nullable',
                'ip',
            ],
            'user_agent' => [
                'nullable',
                'string',
                'max:255',
            ],
            'expires_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
