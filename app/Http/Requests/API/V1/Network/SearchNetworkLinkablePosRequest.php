<?php

namespace App\Http\Requests\API\V1\Network;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchNetworkLinkablePosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Resolve one exact POS business identifier only; this endpoint is not a directory.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'network_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'pos_uuid' => [
                'required',
                'string',
                'max:64',
                'regex:/^POS-\d{4}-\d{5}-[A-F0-9]{4}$/',
                Rule::exists('pos', 'pos_uuid')->whereNull('deleted_at'),
            ],
        ];
    }

    public function getNetworkId(): int
    {
        return (int) $this->input('network_id');
    }

    public function getPosBusinessCode(): string
    {
        return strtoupper(trim((string) $this->input('pos_uuid')));
    }
}
