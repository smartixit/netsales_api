<?php

namespace App\Http\Requests\API\V1\Network;

use App\Enums\PosStatus;
use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateNetworkPosLinkingChallengeRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

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
            'network_id' => [
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'pos_id' => ['missing'],
            'pos_uuid' => [
                'required',
                'string',
                'max:64',
                'regex:/^POS-\d{4}-\d{5}-[A-F0-9]{4}$/',
                Rule::exists('pos', 'pos_uuid')
                    ->whereNull('deleted_at')
                    ->where('status', PosStatus::ACTIVE->value),
            ],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:5',
                'max:60',
            ],
        ];
    }

    public function getNetworkId(): int
    {
        return (int) $this->network_id;
    }

    public function getPosUuid(): string
    {
        return strtoupper(trim((string) $this->pos_uuid));
    }

    public function getDurationMinutes(): int
    {
        return $this->filled('duration_minutes') ? (int) $this->duration_minutes : 15;
    }
}
