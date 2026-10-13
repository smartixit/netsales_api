<?php

namespace App\Http\Requests\API\V1\Common;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب التحقق لعمليات الرفض مع بيان السبب (Entity Rejection Request).
 */
class RejectRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],
        ];
    }

    public function getReason(): string
    {
        return trim((string) $this->input('reason'));
    }
}
