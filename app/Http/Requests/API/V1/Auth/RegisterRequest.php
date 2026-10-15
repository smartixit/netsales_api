<?php

namespace App\Http\Requests\API\V1\Auth;

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
            // Network info
            'network_name' => [
                'required',
                'string',
                'max:50',
                'unique:networks,network_name',
            ],
            'network_holder_name' => [
                'required',
                'string',
                'max:50',
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
            'network_phone_number' => [
                'required',
                'string',
                'min:9',
                'max:9',
                'regex:/^7[0-9]{8}$/',
            ],
            'network_email' => [
                'nullable',
                'email',
                'max:50',
            ],
            'network_address' => [
                'nullable',
                'string',
                'max:150',
            ],
            'network_profile_logo' => [
                'nullable',
                'image',
                'max:2048',
            ],

            // User (Network Admin) info
            'user_full_name' => [
                'required',
                'string',
                'max:50',
            ],
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
            'user_profile_logo' => [
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
