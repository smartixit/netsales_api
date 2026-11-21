<?php

namespace App\Http\Requests\API\V1\Network;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewNetworkPosLinkingChallengeRequest extends FormRequest
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
            'payload' => [
                'required',
                'string',
            ],
            'pos_id' => ['missing'],
            'pos_uuid' => [
                'required',
                'string',
                'max:64',
                'regex:/^POS-\d{4}-\d{5}-[A-F0-9]{4}$/',
                Rule::exists('pos', 'pos_uuid')->whereNull('deleted_at'),
            ],
        ];
    }

    public function getChallengePayload(): string
    {
        return (string) $this->payload;
    }

    public function getPosUuid(): string
    {
        return strtoupper(trim((string) $this->pos_uuid));
    }
}
