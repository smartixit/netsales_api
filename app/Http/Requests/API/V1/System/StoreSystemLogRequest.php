<?php

namespace App\Http\Requests\API\V1\System;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemLogRequest extends FormRequest
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
                'nullable',
                'integer',
                Rule::exists('users', 'user_id')->whereNull('deleted_at'),
            ],
            'module' => [
                'required',
                'string',
                'max:50',
            ],
            'action' => [
                'required',
                'string',
                'max:50',
            ],
            'entity_type' => [
                'nullable',
                'string',
                'max:50',
            ],
            'entity_id' => [
                'nullable',
                'string',
                'max:50',
            ],
            'old_values' => [
                'nullable',
                'array',
            ],
            'new_values' => [
                'nullable',
                'array',
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
        ];
    }
}
