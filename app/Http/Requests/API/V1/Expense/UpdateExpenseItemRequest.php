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
            'item_statement' => [
                'nullable',
                'string',
                'max:150',
            ],
            'item_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    public function getItemStatement(): ?string
    {
        return $this->input('item_statement');
    }

    public function hasItemStatement(): bool
    {
        return $this->has('item_statement');
    }

    public function getItemAmount(): ?float
    {
        return $this->has('item_amount') ? (float) $this->input('item_amount') : null;
    }

    public function hasItemAmount(): bool
    {
        return $this->has('item_amount');
    }
}
