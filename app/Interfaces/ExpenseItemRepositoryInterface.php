<?php

namespace App\Interfaces;

use App\Models\ExpenseItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع بنود المصروفات (Expense Item Repository Interface).
 */
interface ExpenseItemRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب كافة بنود مصروف محدد.
     *
     * @return Collection<int, ExpenseItem>
     */
    public function getByExpenseId(int $expenseId): Collection;

    /**
     * إنشاء بنود متعددة لمصروف محدد دفعة واحدة.
     *
     * @param  array<int, array{item_statement: string, item_amount: float}>  $items
     */
    public function createManyForExpense(int $expenseId, array $items): bool;

    /**
     * حذف جميع بنود مصروف محدد.
     */
    public function deleteByExpenseId(int $expenseId): int;
}
