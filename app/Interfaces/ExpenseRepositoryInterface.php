<?php

namespace App\Interfaces;

use App\Models\Expense;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع المصروفات التشغيلية (Expense Repository Interface).
 */
interface ExpenseRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب المصروفات مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, Expense>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'createdBy', 'items', 'attachments']): Collection|CursorPaginator;

    /**
     * جلب المصروفات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, Expense>|CursorPaginator
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'createdBy']): Collection|CursorPaginator;

    /**
     * جلب مصروفات شبكة محددة.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, Expense>|CursorPaginator
     */
    public function getByNetwork(int $networkId, array $filters = [], array $relations = ['createdBy', 'items', 'attachments']): Collection|CursorPaginator;

    /**
     * البحث عن مصروف برقم UUID الفرعي.
     */
    public function findByUuid(string $uuid): ?Expense;

    /**
     * تحديث إجمالي مبلغ المصروف.
     */
    public function updateTotalAmount(int $expenseId, float $totalAmount): bool;
}
