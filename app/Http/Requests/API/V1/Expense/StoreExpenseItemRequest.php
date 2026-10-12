<?php

namespace App\Http\Requests\API\V1\Expense;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseItemRequest extends FormRequest
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
            'item_amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'item_statement' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
