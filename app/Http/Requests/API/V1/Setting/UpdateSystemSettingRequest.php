<?php

namespace App\Http\Requests\API\V1\Setting;

use App\Enums\SettingCategory;
use App\Enums\ValueType;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingRequest extends FormRequest
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
        $settingId = $this->route('system_setting')?->setting_id
            ?? $this->route('setting')?->setting_id
            ?? $this->route('system_setting')
            ?? $this->route('setting')
            ?? $this->route('id');

        return [
            'setting_category' => [
                'sometimes',
                'required',
                Rule::enum(SettingCategory::class),
            ],
            'setting_key' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('system_settings', 'setting_key')
                    ->ignore($settingId, 'setting_id')
                    ->whereNull('deleted_at'),
            ],
            'setting_value_type' => [
                'sometimes',
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
