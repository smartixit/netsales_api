<?php

namespace App\Http\Requests\API\V1\Reporting;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRunRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'report_key' => ['required', 'string', 'max:120'],
            'filters' => ['sometimes', 'array'],
        ];
    }
}
