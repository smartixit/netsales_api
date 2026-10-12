<?php

namespace App\Http\Requests\API\V1\Expense;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseItemRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_amount' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01',
            ],
            'item_statement' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
