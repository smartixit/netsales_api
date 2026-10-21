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
            'app_hash' => ['nullable', 'string', 'max:20'],
        ];
    }
}
