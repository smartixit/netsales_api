<?php

namespace App\Http\Requests\API\V1\Billing;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditNoteRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:128'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.invoice_line_id' => ['required', 'integer', 'distinct', 'exists:billing_invoice_lines,invoice_line_id'],
            'lines.*.amount_minor' => ['required', 'integer', 'min:1'],
        ];
    }
}
