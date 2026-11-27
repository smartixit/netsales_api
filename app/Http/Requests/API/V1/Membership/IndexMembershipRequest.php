<?php

namespace App\Http\Requests\API\V1\Membership;

use App\Enums\MembershipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware and membership services own tenant authorization.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'nullable', Rule::enum(MembershipStatus::class)],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
