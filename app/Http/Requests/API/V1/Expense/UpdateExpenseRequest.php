<?php

namespace App\Http\Requests\API\V1\Expense;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UpdateExpenseRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_date' => [
                'nullable',
                'date',
            ],
            'items' => [
                'nullable',
                'array',
            ],
            'items.*.item_statement' => [
                'required_with:items',
                'string',
                'max:150',
            ],
            'items.*.item_amount' => [
                'required_with:items',
                'numeric',
                'min:0.01',
            ],
            'attachments' => [
                'nullable',
                'array',
            ],
            'attachments.*' => [
                'file',
                'mimes:jpeg,png,jpg,webp,pdf,doc,docx',
                'max:10240',
            ],
        ];
    }

    public function getExpenseDate(): ?string
    {
        return $this->input('expense_date');
    }

    public function hasExpenseDate(): bool
    {
        return $this->has('expense_date');
    }

    /**
     * @return array<int, array{item_statement: string, item_amount: float}>|null
     */
    public function getItems(): ?array
    {
        return $this->has('items') ? (array) $this->input('items') : null;
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function getAttachments(): array
    {
        return (array) $this->file('attachments', []);
    }
}
