<?php

namespace App\Http\Requests\API\V1\Network;

use Illuminate\Foundation\Http\FormRequest;

class FilterNetworkPosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'network_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'pos_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'all' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
