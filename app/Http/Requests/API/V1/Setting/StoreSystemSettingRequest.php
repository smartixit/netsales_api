<?php

namespace App\Http\Requests\API\V1\Setting;

use App\Enums\SettingCategory;
use App\Enums\ValueType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemSettingRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'setting_key' => is_string($this->setting_key) ? trim(strtolower($this->setting_key)) : $this->setting_key,
            'setting_desc' => is_string($this->setting_desc) ? trim($this->setting_desc) : $this->setting_desc,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'setting_category' => [
                'required',
                Rule::enum(SettingCategory::class),
            ],
            'setting_key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('system_settings', 'setting_key')->whereNull('deleted_at'),
            ],
            'setting_value_type' => [
                'required',
                Rule::enum(ValueType::class),
            ],
            'setting_value' => [
                'nullable',
                'string',
            ],
            'setting_desc' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}
