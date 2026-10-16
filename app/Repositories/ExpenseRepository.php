<?php

namespace App\Repositories;

use App\Interfaces\ExpenseRepositoryInterface;
use App\Models\Expense;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع المصروفات التشغيلية (Expense Repository).
 *
 * @property Expense $model
 */
class ExpenseRepository extends BaseRepository implements ExpenseRepositoryInterface
{
    public function __construct(Expense $model)
    {
        parent::__construct($model);
    }

    /**
     * تطبيق الفلاتر الخاصة بالمصروفات.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        parent::applyFilters($query, $filters);

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('expense_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('expense_date', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('expense_uuid', 'like', "%{$search}%")
                    ->orWhereHas('network', function ($nq) use ($search) {
                        $nq->where('network_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('item_statement', 'like', "%{$search}%");
                    });
            });
        }
    }

    /**
     * جلب المصروفات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'createdBy', 'items', 'attachments']): Collection|CursorPaginator
    {
        return parent::getFiltered($filters, $relations);
    }

    /**
     * جلب المصروفات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'createdBy']): Collection|CursorPaginator
    {
        return parent::getTrashedFiltered($filters, $relations);
    }

    /**
     * جلب مصروفات شبكة محددة.
     */
    public function getByNetwork(int $networkId, array $filters = [], array $relations = ['createdBy', 'items', 'attachments']): Collection|CursorPaginator
    {
        $filters['network_id'] = $networkId;

        return $this->getFiltered($filters, $relations);
    }

    /**
     * البحث عن مصروف برقم UUID الفرعي.
     */
    public function findByUuid(string $uuid): ?Expense
    {
        return $this->model->newQuery()->where('expense_uuid', $uuid)->first();
    }

    /**
     * تحديث إجمالي مبلغ المصروف.
     */
    public function updateTotalAmount(int $expenseId, float $totalAmount): bool
    {
        return (bool) $this->patch($expenseId, ['expense_total_amount' => $totalAmount]);
    }
}
