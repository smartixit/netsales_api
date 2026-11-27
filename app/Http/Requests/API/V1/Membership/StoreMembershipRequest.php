<?php

namespace App\Http\Requests\API\V1\Membership;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware and the membership service enforce tenant permissions.
        return true;
    }

    public function rules(): array
    {
        return [
            'user_phone_number' => ['required', 'string', 'max:20'],
            'role_id' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'string', 'in:ACTIVE,SUSPENDED,INVITED'],
        ];
    }
}
