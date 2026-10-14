<?php

namespace App\Repositories;

use App\Interfaces\ExpenseAttachmentRepositoryInterface;
use App\Models\ExpenseAttachment;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع مرفقات المصروفات (Expense Attachment Repository).
 *
 * @property ExpenseAttachment $model
 */
class ExpenseAttachmentRepository extends BaseRepository implements ExpenseAttachmentRepositoryInterface
{
    public function __construct(ExpenseAttachment $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب كافة مرفقات مصروف محدد.
     */
    public function getByExpenseId(int $expenseId): Collection
    {
        return $this->model->newQuery()->where('expense_id', $expenseId)->get();
    }

    /**
     * إضافة مرفق جديد لمصروف محدد.
     */
    public function createForExpense(int $expenseId, array $data): ExpenseAttachment
    {
        return $this->create([
            'expense_id' => $expenseId,
            'attachment_path' => $data['attachment_path'],
            'attachment_name' => $data['attachment_name'],
            'attachment_mime' => $data['attachment_mime'],
            'attachment_size' => (int) $data['attachment_size'],
        ]);
    }

    /**
     * حذف كافة مرفقات مصروف محدد.
     */
    public function deleteByExpenseId(int $expenseId): int
    {
        return $this->model->newQuery()->where('expense_id', $expenseId)->delete();
    }
}
