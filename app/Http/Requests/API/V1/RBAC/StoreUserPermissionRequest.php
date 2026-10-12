<?php

namespace App\Http\Requests\API\V1\RBAC;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserPermissionRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'user_id')->whereNull('deleted_at'),
            ],
            'permission_id' => [
                'required',
                'integer',
                Rule::exists('permissions', 'permission_id')->whereNull('deleted_at'),
                Rule::unique('user_permissions', 'permission_id')
                    ->where('user_id', $this->user_id),
            ],
        ];
    }
}
