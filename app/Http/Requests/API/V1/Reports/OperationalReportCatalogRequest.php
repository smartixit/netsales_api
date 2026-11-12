<?php

namespace App\Http\Requests\API\V1\Reports;

use Illuminate\Foundation\Http\FormRequest;

class OperationalReportCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'network_id' => ['required', 'integer', 'min:1'],
            'pos_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
