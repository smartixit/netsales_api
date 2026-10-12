<?php

namespace App\Http\Requests\API\V1\Module;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'module_key' => is_string($this->module_key) ? trim(strtolower($this->module_key)) : $this->module_key,
            'module_name' => is_string($this->module_name) ? trim($this->module_name) : $this->module_name,
            'module_icon' => is_string($this->module_icon) ? trim($this->module_icon) : $this->module_icon,
            'module_desc' => is_string($this->module_desc) ? trim($this->module_desc) : $this->module_desc,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $moduleId = $this->route('module')?->module_id ?? $this->route('module') ?? $this->route('id');

        return [
            'module_key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('modules', 'module_key')
                    ->ignore($moduleId, 'module_id')
                    ->whereNull('deleted_at'),
            ],
            'module_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'module_icon' => [
                'nullable',
                'string',
                'max:50',
            ],
            'module_desc' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }
}
