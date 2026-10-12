<?php

namespace App\Http\Requests\API\V1\RBAC;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRolePermissionRequest extends FormRequest
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
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'role_id')->whereNull('deleted_at'),
            ],
            'permission_id' => [
                'required',
                'integer',
                Rule::exists('permissions', 'permission_id')->whereNull('deleted_at'),
                Rule::unique('role_permissions', 'permission_id')
                    ->where('role_id', $this->role_id),
            ],
        ];
    }
}
