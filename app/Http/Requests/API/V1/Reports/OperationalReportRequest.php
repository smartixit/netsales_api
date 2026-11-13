<?php

namespace App\Http\Requests\API\V1\Reports;

use App\Enums\OperationalReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', Rule::enum(OperationalReportType::class)],
            'network_id' => ['required', 'integer', 'min:1'],
            'pos_id' => ['nullable', 'integer', 'min:1'],
            'filters' => ['sometimes', 'array'],
            'filters.*' => ['nullable'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
