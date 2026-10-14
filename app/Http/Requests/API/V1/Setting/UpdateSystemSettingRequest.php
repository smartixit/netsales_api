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
        $data = [];
        if ($this->has('setting_key') && is_string($this->setting_key)) {
            $data['setting_key'] = trim(strtolower($this->setting_key));
        }
        if ($this->has('setting_desc') && is_string($this->setting_desc)) {
            $data['setting_desc'] = trim($this->setting_desc);
        }
        if (! empty($data)) {
            $this->merge($data);
        }
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

    public function getSettingCategory(): ?SettingCategory
    {
        if (! $this->has('setting_category')) {
            return null;
        }

        return $this->setting_category instanceof SettingCategory
            ? $this->setting_category
            : SettingCategory::from((string) $this->setting_category);
    }

    public function getSettingKey(): ?string
    {
        return $this->has('setting_key') ? (string) $this->setting_key : null;
    }

    public function getSettingValueType(): ?ValueType
    {
        if (! $this->has('setting_value_type')) {
            return null;
        }

        return $this->setting_value_type instanceof ValueType
            ? $this->setting_value_type
            : ValueType::from((string) $this->setting_value_type);
    }

    public function getSettingValue(): ?string
    {
        return $this->has('setting_value') ? ($this->setting_value !== null ? (string) $this->setting_value : null) : null;
    }

    public function getSettingDesc(): ?string
    {
        return $this->has('setting_desc') ? ($this->setting_desc ? (string) $this->setting_desc : null) : null;
    }
}
