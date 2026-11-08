<?php

namespace App\Http\Requests\API\V1\Dashboard;

use App\Enums\RoleKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role?->role_key, [
            RoleKey::SUPER_ADMIN->value,
            RoleKey::SUPPORT_AGENT->value,
            RoleKey::ACCOUNTANT->value,
        ], true);
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['7d', '30d', '90d', '12m'])],
        ];
    }

    public function period(): string
    {
        return (string) $this->validated('period', '30d');
    }
}
