<?php

namespace App\Http\Requests\API\V1\System;

use App\Enums\BanType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSecurityBanRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ban_type' => [
                'sometimes',
                'required',
                Rule::enum(BanType::class),
            ],
            'ban_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
            'start_at' => [
                'nullable',
                'date',
            ],
            'end_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}
