<?php

namespace App\Http\Requests\API\V1\Backup;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBackupConfigRequest extends FormRequest
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
            'enabled' => ['sometimes', 'boolean'],
            'schedule_time' => ['sometimes', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'retention_count' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'include_storage' => ['sometimes', 'boolean'],
            'failure_sms' => ['sometimes', 'boolean'],
            'client_id' => ['sometimes', 'nullable', 'string'],
            'client_secret' => ['sometimes', 'nullable', 'string'],
            'folder_name' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function getConfigData(): array
    {
        return $this->only([
            'enabled',
            'schedule_time',
            'retention_count',
            'include_storage',
            'failure_sms',
            'client_id',
            'client_secret',
            'folder_name',
        ]);
    }
}
