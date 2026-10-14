<?php

namespace App\Http\Requests\API\V1\Common;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب التحقق لتغيير الحالة المجمعة (Batch Status Request).
 */
class BatchStatusRequest extends FormRequest
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
            'status' => [
                'required',
                'boolean',
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

    public function getStatus(): bool
    {
        return (bool) $this->input('status');
    }
}
