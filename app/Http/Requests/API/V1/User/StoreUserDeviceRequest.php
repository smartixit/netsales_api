<?php

namespace App\Http\Requests\API\V1\User;

use App\Enums\DeviceType;
use App\Enums\UserDeviceStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserDeviceRequest extends FormRequest
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
            'device_uuid' => [
                'required',
                'string',
                'max:255',
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:100',
            ],
            'device_type' => [
                'required',
                Rule::enum(DeviceType::class),
            ],
            'is_primary' => [
                'nullable',
                'boolean',
            ],
            'status' => [
                'nullable',
                Rule::enum(UserDeviceStatus::class),
            ],
        ];
    }
}
