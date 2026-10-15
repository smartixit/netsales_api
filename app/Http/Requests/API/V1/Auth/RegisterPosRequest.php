<?php

namespace App\Http\Requests\API\V1\Auth;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب تسجيل وكيل / نقطة بيع جديدة (POS Registration Request).
 */
class RegisterPosRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pos_name' => is_string($this->pos_name) ? trim($this->pos_name) : $this->pos_name,
            'pos_holder_name' => is_string($this->pos_holder_name) ? trim($this->pos_holder_name) : $this->pos_holder_name,
            'pos_phone_number' => is_string($this->pos_phone_number) ? trim($this->pos_phone_number) : $this->pos_phone_number,
            'user_phone_number' => is_string($this->user_phone_number) ? trim($this->user_phone_number) : ($this->pos_phone_number ? trim($this->pos_phone_number) : null),
            'user_full_name' => is_string($this->user_full_name) ? trim($this->user_full_name) : ($this->pos_holder_name ? trim($this->pos_holder_name) : null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // بيانات نقطة البيع
            'pos_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('pos', 'pos_name')->whereNull('deleted_at'),
            ],
            'pos_holder_name' => [
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
            'pos_phone_number' => [
                'required',
                'string',
                'min:9',
                'max:14',
                'regex:/^[0-9+\-\s()]+$/',
            ],
            'pos_email' => [
                'nullable',
                'email',
                'max:50',
            ],
            'pos_address' => [
                'nullable',
                'string',
                'max:150',
            ],
            'pos_profile_logo' => [
                'nullable',
                'image',
                'max:2048',
            ],
            'network_id' => [
                'nullable',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],

            // بيانات المستخدم (مسؤول نقطة البيع)
            'user_full_name' => [
                'required',
                'string',
                'max:50',
            ],
            'user_phone_number' => [
                'required',
                'string',
                'min:9',
                'max:14',
                'regex:/^[0-9+\-\s()]+$/',
                Rule::unique('users', 'user_phone_number')->whereNull('deleted_at'),
            ],
            'user_password' => [
                'required',
                'string',
                'min:8',
                'max:30',
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

            // بيانات الجهاز
            'device_uuid' => [
                'required',
                'string',
                'max:150',
            ],
            'device_name' => [
                'nullable',
                'string',
                'max:50',
            ],
            'device_type' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }
}
