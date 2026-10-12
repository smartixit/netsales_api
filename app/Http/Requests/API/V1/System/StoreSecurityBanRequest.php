<?php

namespace App\Http\Requests\API\V1\System;

use App\Enums\BanType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecurityBanRequest extends FormRequest
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
                'required_without:device_id',
                Rule::exists('users', 'user_id')->whereNull('deleted_at'),
            ],
            'device_id' => [
                'nullable',
                'integer',
                'required_without:user_id',
                Rule::exists('user_devices', 'device_id')->whereNull('deleted_at'),
            ],
            'ban_type' => [
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
                'after_or_equal:start_at',
            ],
        ];
    }
}
