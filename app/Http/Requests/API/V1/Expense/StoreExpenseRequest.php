<?php

namespace App\Http\Requests\API\V1\Expense;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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
                'required',
                'array',
                'min:1',
            ],
            'items.*.item_statement' => [
                'required',
                'string',
                'max:150',
            ],
            'items.*.item_amount' => [
                'required',
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

    public function getNetworkId(): int
    {
        return (int) $this->input('network_id');
    }

    public function getExpenseDate(): ?string
    {
        return $this->input('expense_date');
    }

    /**
     * @return array<int, array{item_statement: string, item_amount: float}>
     */
    public function getItems(): array
    {
        return (array) $this->input('items', []);
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function getAttachments(): array
    {
        return (array) $this->file('attachments', []);
    }
}
