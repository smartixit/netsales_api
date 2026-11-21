<?php

namespace App\Http\Requests\API\V1\Expense;

use App\Http\Requests\Traits\LoadsCustomValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use App\Models\Expense;
use App\Services\TenantPermissionService;

class UpdateExpenseRequest extends FormRequest
{
    use LoadsCustomValidationMessages;

    public function authorize(): bool
    {
        $user = $this->user();
        $expenseId = (int) ($this->route('id') ?? 0);
        $expense = $expenseId > 0 ? Expense::query()->find($expenseId) : null;

        return $user !== null
            && $expense !== null
            && app(TenantPermissionService::class)->allowsNetwork(
                $user,
                (int) $expense->network_id,
                'expenses.edit'
            );
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
            'deleted_attachment_ids' => [
                'nullable',
                'array',
            ],
            'deleted_attachment_ids.*' => [
                'integer',
                Rule::exists('expense_attachments', 'attachment_id'),
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

    /**
     * @return array<int, int>
     */
    public function getDeletedAttachmentIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) $this->input('deleted_attachment_ids', []))));
    }

    public function hasDeletedAttachmentIds(): bool
    {
        return $this->has('deleted_attachment_ids') && ! empty($this->getDeletedAttachmentIds());
    }
}
