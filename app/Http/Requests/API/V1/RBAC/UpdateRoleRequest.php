<?php

namespace App\Http\Requests\API\V1\RBAC;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'role_key' => is_string($this->role_key) ? trim(strtolower($this->role_key)) : $this->role_key,
            'role_name' => is_string($this->role_name) ? trim($this->role_name) : $this->role_name,
            'role_desc' => is_string($this->role_desc) ? trim($this->role_desc) : $this->role_desc,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $roleId = $this->route('role')?->role_id ?? $this->route('role') ?? $this->route('id');

        return [
            'role_key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'role_key')
                    ->ignore($roleId, 'role_id')
                    ->whereNull('deleted_at'),
            ],
            'role_name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'role_desc' => [
                'nullable',
                'string',
                'max:500',
            ],
            'is_system' => [
                'sometimes',
                'required',
                'boolean',
            ],
            'status' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }
}
