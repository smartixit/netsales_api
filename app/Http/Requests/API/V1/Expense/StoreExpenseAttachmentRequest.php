<?php

namespace App\Http\Requests\API\V1\Expense;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseAttachmentRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_id' => [
                'required',
                'integer',
                Rule::exists('expenses', 'expense_id')->whereNull('deleted_at'),
            ],
            'attachment' => [
                'required',
                'file',
                'max:10240',
            ],
            'attachment_name' => [
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }
}
