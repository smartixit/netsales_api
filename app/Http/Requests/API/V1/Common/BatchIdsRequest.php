<?php

namespace App\Http\Requests\API\V1\Common;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب التحقق للعمليات المجمعة (Batch Operations Request).
 */
class BatchIdsRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ids.*' => [
                'required',
                'integer',
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getIds(): array
    {
        return array_map('intval', (array) $this->input('ids', []));
    }
}
