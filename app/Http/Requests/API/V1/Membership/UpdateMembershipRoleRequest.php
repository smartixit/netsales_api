<?php

namespace App\Http\Requests\API\V1\Membership;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMembershipRoleRequest extends FormRequest
{
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
            'role_id' => ['required', 'integer', 'min:1'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
