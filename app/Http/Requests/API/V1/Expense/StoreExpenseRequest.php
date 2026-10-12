<?php

namespace App\Http\Requests\API\V1\Expense;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network_id' => [
                'required',
                'integer',
                Rule::exists('networks', 'network_id')->whereNull('deleted_at'),
            ],
            'expense_date' => [
                'nullable',
                'date',
            ],
            'items' => [
                'nullable',
                'array',
            ],
            'items.*.item_amount' => [
                'required_with:items',
                'numeric',
                'min:0.01',
            ],
            'items.*.item_statement' => [
                'required_with:items',
                'string',
                'max:255',
            ],
        ];
    }
}
