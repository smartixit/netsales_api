<?php

namespace App\Http\Requests\API\V1\Membership;

use App\Enums\MembershipStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $networkId = $this->route('networkId');
        if ($networkId !== null) {
            return $user->hasPermission('network_members.view', (int) $networkId);
        }

        $posId = $this->route('posId');
        if ($posId !== null) {
            return $user->hasPermission('pos_members.view', (int) $posId);
        }

        return false;
    }

    /**
     * @return array<string, array<int, string>>
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
