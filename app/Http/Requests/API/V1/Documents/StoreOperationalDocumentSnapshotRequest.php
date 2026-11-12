<?php

namespace App\Http\Requests\API\V1\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOperationalDocumentSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['sale_invoice', 'pos_repayment', 'expense'])],
            'document_id' => ['required', 'integer', 'min:1'],
            'network_id' => ['required', 'integer', 'min:1'],
            'pos_id' => ['nullable', 'integer', 'min:1'],
            'template_id' => ['required', 'string', 'max:120'],
            'template_version' => ['required', 'string', 'max:40'],
        ];
    }
}
