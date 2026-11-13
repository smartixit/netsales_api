<?php

namespace App\Http\Requests\API\V1\Documents;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOperationalDocumentArtifactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'artifact' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }
}
