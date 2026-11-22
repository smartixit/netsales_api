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
     * @return array<string, array<int, mixed>>
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
            'search' => ['required', 'string', 'min:2', 'max:120'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:30'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    public function getNetworkId(): int
    {
        return (int) $this->input('network_id');
    }

    public function getSearch(): string
    {
        return trim((string) $this->input('search'));
    }

    public function getPerPage(): int
    {
        return min(max(1, (int) $this->input('per_page', 15)), 30);
    }

    public function getCursor(): ?string
    {
        $cursor = $this->input('cursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }
}
