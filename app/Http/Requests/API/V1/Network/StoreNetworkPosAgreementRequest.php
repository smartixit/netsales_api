<?php

namespace App\Http\Requests\API\V1\Network;

use Illuminate\Foundation\Http\FormRequest;

class StoreNetworkPosAgreementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:100'],
            'terms' => ['required', 'string', 'max:50000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
