<?php

namespace App\Repositories;

use App\Interfaces\ExpenseItemRepositoryInterface;
use App\Models\ExpenseItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع بنود المصروفات (Expense Item Repository).
 *
 * @property ExpenseItem $model
 */
class ExpenseItemRepository extends BaseRepository implements ExpenseItemRepositoryInterface
{
    public function __construct(ExpenseItem $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب كافة بنود مصروف محدد.
     */
    public function getByExpenseId(int $expenseId): Collection
    {
        return $this->model->newQuery()->where('expense_id', $expenseId)->get();
    }

    /**
     * إنشاء بنود متعددة لمصروف محدد دفعة واحدة.
     */
    public function createManyForExpense(int $expenseId, array $items): bool
    {
        $timestamp = now('Asia/Aden');
        $records = [];

        foreach ($items as $item) {
            $records[] = [
                'expense_id' => $expenseId,
                'item_statement' => (string) $item['item_statement'],
                'item_amount' => (float) $item['item_amount'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        return $this->model->newQuery()->insert($records);
    }

    /**
     * حذف جميع بنود مصروف محدد.
     */
    public function deleteByExpenseId(int $expenseId): int
    {
        return $this->model->newQuery()->where('expense_id', $expenseId)->delete();
    }
}
