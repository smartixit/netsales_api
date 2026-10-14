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

    public function getSettingCategory(): SettingCategory
    {
        return $this->setting_category instanceof SettingCategory
            ? $this->setting_category
            : SettingCategory::from((string) $this->setting_category);
    }

    public function getSettingKey(): string
    {
        return (string) $this->setting_key;
    }

    public function getSettingValueType(): ValueType
    {
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
        return $this->setting_desc ? (string) $this->setting_desc : null;
    }
}
