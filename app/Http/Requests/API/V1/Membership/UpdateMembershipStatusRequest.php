<?php

namespace App\Http\Requests\API\V1\Membership;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMembershipStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:ACTIVE,SUSPENDED,INVITED'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
