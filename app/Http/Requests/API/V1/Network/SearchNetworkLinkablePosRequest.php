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
            'search' => ['required', 'string', 'min:2', 'max:120'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
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
        return (int) $this->input('per_page', 10);
    }

    public function getCursor(): ?string
    {
        return $this->filled('cursor') ? (string) $this->input('cursor') : null;
    }
}
