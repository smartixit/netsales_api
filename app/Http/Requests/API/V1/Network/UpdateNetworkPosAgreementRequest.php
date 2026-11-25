<?php

namespace App\Http\Requests\API\V1\Network;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNetworkPosAgreementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'terms' => ['sometimes', 'required', 'string', 'max:50000'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
