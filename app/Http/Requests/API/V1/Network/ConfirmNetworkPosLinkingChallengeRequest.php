<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmNetworkPosLinkingChallengeRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_uuid' => [
                'required',
                'string',
                'uuid',
                Rule::exists('network_pos_linking_challenges', 'challenge_uuid'),
            ],
            'pos_id' => [
                'required_without:pos_uuid',
                'nullable',
                'integer',
                Rule::exists('pos', 'pos_id')->whereNull('deleted_at'),
            ],
            'pos_uuid' => [
                'required_without:pos_id',
                'nullable',
                'string',
                Rule::exists('pos', 'pos_uuid')->whereNull('deleted_at'),
            ],
        ];
    }

    public function getChallengeUuid(): string
    {
        return (string) $this->challenge_uuid;
    }

    public function getPosId(): ?int
    {
        return $this->filled('pos_id') ? (int) $this->pos_id : null;
    }

    public function getPosUuid(): ?string
    {
        return $this->filled('pos_uuid') ? (string) $this->pos_uuid : null;
    }
}
