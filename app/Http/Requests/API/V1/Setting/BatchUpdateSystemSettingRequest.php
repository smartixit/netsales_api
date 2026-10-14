<?php

namespace App\Http\Requests\API\V1\Setting;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب التحديث المجمع لإعدادات النظام (Batch Update System Settings Request).
 */
class BatchUpdateSystemSettingRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'settings' => [
                'required',
                'array',
                'min:1',
            ],
            'settings.*.key' => [
                'required',
                'string',
                'exists:system_settings,setting_key',
            ],
            'settings.*.value' => [
                'nullable',
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, value: mixed}>
     */
    public function getSettings(): array
    {
        return (array) $this->input('settings', []);
    }
}
