<?php

namespace App\Http\Requests\API\V1\Expense;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

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
            'item_statement' => [
                'required',
                'string',
                'max:150',
            ],
            'item_amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    public function getItemStatement(): string
    {
        return (string) $this->input('item_statement');
    }

    public function getItemAmount(): float
    {
        return (float) $this->input('item_amount');
    }
}
