<?php

namespace App\Repositories;

use App\Interfaces\ExpenseRepositoryInterface;
use App\Models\Expense;
use Illuminate\Contracts\Pagination\CursorPaginator;
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
     * جلب المصروفات مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'createdBy', 'items', 'attachments']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

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

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('expense_id', 'desc')->get();
        }

        return $query->orderBy('expense_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب المصروفات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'createdBy']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery()->onlyTrashed()->with($relations);

        if (! empty($filters['network_id'])) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('expense_uuid', 'like', "%{$search}%")
                    ->orWhereHas('network', function ($nq) use ($search) {
                        $nq->where('network_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('expense_id', 'desc')->get();
        }

        return $query->orderBy('expense_id', 'desc')->cursorPaginate($perPage);
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
