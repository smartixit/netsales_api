<?php

namespace App\Http\Requests\API\V1\Auth;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterNetworkRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'network_name' => is_string($this->network_name) ? trim($this->network_name) : $this->network_name,
            'user_phone_number' => is_string($this->user_phone_number) ? trim($this->user_phone_number) : $this->user_phone_number,
            'user_full_name' => is_string($this->user_full_name) ? trim($this->user_full_name) : $this->user_full_name,
        ]);
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

            // User (Network Owner) info
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

            // Device info
            'device_uuid' => [
                'required',
                'string',
                'max:255',
            ],
            'fcm_token' => [
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
