<?php

namespace App\Http\Requests\API\V1\Auth;

use App\Enums\UserSessionStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetUserSessionsRequest extends FormRequest
{
    use LoadsCustomValidationMessages;
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::enum(UserSessionStatus::class)],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function selectedStatus(): ?UserSessionStatus
    {
        $raw = $this->validated('status');

        return is_string($raw) && $raw !== ''
            ? UserSessionStatus::tryFrom($raw)
            : null;
    }

    public function searchTerm(): ?string
    {
        $raw = $this->validated('search');

        return is_string($raw) && trim($raw) !== ''
            ? trim($raw)
            : null;
    }
}
