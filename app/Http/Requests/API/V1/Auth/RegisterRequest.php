<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            // Center info
            'center_name' => [
                'required',
                'string',
                'max:50',
                'unique:centers,center_name',
            ],
            'governorate_id' => [
                'required',
                'integer',
                'exists:governorates,governorate_id',
            ],
            'directorate_id' => [
                'required',
                'integer',
                Rule::exists('directorates', 'directorate_id')->where(function ($query) {
                    if ($this->filled('governorate_id')) {
                        $query->where('governorate_id', $this->input('governorate_id'));
                    }
                }),
            ],
            'center_phone_number' => [
                'nullable',
                'string',
                'min:9',
                'max:9',
                'regex:/^7[0-9]{8}$/',
            ],
            'center_address' => [
                'nullable',
                'string',
                'max:50',
            ],
            'center_description' => [
                'nullable',
                'string',
                'max:50',
            ],
            'center_profile_picture' => [
                'nullable',
                'image',
                'max:2048',
            ],

            // User info
            'user_phone_number' => [
                'required',
                'string',
                'min:9',
                'max:9',
                'regex:/^7[0-9]{8}$/',
                'unique:users,user_phone_number',
            ],
            'user_password' => [
                'required',
                'string',
                'min:8',
                'max:20',
            ],
            'user_email' => [
                'nullable',
                'email',
                'max:50',
            ],
            'user_profile_picture' => [
                'nullable',
                'image',
                'max:2048',
            ],
            'profile_picture' => [
                'nullable',
                'image',
                'max:2048',
            ],

            // Device info
            'device_uuid' => [
                'required',
                'string',
                'max:255',
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:150',
            ],
            'device_type' => [
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }
}
