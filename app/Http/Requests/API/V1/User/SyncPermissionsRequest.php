<?php

namespace App\Http\Requests\API\V1\User;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * طلب التحقق لمزامنة الصلاحيات المباشرة للمستخدم (Sync User Permissions Request).
 */
class SyncPermissionsRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => [
                'present',
                'array',
            ],
            'permissions.*' => [
                'integer',
                Rule::exists('permissions', 'permission_id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getPermissionIds(): array
    {
        return array_map('intval', (array) $this->input('permissions', []));
    }
}
