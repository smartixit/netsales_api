<?php

namespace App\Http\Requests\API\V1\RBAC;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permission_key' => is_string($this->permission_key) ? trim(strtolower($this->permission_key)) : $this->permission_key,
            'permission_name' => is_string($this->permission_name) ? trim($this->permission_name) : $this->permission_name,
            'permission_desc' => is_string($this->permission_desc) ? trim($this->permission_desc) : $this->permission_desc,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'module_id' => [
                'required',
                'integer',
                Rule::exists('modules', 'module_id')->whereNull('deleted_at'),
            ],
            'action_type_id' => [
                'required',
                'integer',
                Rule::exists('action_types', 'action_type_id')->whereNull('deleted_at'),
            ],
            'permission_key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_.-]+$/',
                Rule::unique('permissions', 'permission_key')->whereNull('deleted_at'),
            ],
            'permission_name' => [
                'required',
                'string',
                'max:100',
            ],
            'permission_desc' => [
                'nullable',
                'string',
                'max:500',
            ],
            'status' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
