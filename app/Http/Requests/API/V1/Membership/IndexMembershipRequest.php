<?php

namespace App\Http\Requests\API\V1\Membership;

use Illuminate\Foundation\Http\FormRequest;

class IndexMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'in:ACTIVE,SUSPENDED,INVITED'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
