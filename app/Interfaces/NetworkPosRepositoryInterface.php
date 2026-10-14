<?php

namespace App\Interfaces;

use App\Models\NetworkPos;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إدارة ارتباط نقاط البيع بمزودي الشبكات (Network POS Repository Interface).
 */
interface NetworkPosRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة الارتباطات مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, NetworkPos>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * البحث عن ارتباط محدد بين شبكة ونقطة بيع.
     */
    public function findByNetworkAndPos(int $networkId, int $posId): ?NetworkPos;

    /**
     * جلب نقاط البيع المرتبطة بشبكة محددة.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, NetworkPos>|CursorPaginator
     */
    public function getPosForNetwork(int $networkId, array $filters = []): Collection|CursorPaginator;

    /**
     * جلب الشبكات المرتبطة بنقطة بيع محددة.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, NetworkPos>|CursorPaginator
     */
    public function getNetworksForPos(int $posId, array $filters = []): Collection|CursorPaginator;

    /**
     * تغيير حالة مجموعة ارتباطات مجمعة (تفعيل أو تعطيل).
     *
     * @param  array<int, int>  $ids
     */
    public function bulkToggleStatus(array $ids, bool $status): int;
}
