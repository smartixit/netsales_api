<?php

namespace App\Interfaces;

use App\Models\ExpenseAttachment;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع مرفقات المصروفات (Expense Attachment Repository Interface).
 */
interface ExpenseAttachmentRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب كافة مرفقات مصروف محدد.
     *
     * @return Collection<int, ExpenseAttachment>
     */
    public function getByExpenseId(int $expenseId): Collection;

    /**
     * إضافة مرفق جديد لمصروف محدد.
     *
     * @param  array{attachment_path: string, attachment_name: string, attachment_mime: string, attachment_size: int}  $data
     */
    public function createForExpense(int $expenseId, array $data): ExpenseAttachment;

    /**
     * حذف كافة مرفقات مصروف محدد.
     */
    public function deleteByExpenseId(int $expenseId): int;
}
