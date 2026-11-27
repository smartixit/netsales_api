<?php

namespace App\Http\Requests\API\V1\Membership;

use Illuminate\Foundation\Http\FormRequest;

class RemoveMembershipRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
