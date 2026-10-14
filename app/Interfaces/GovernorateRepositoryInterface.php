<?php

namespace App\Interfaces;

use App\Models\Governorate;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع المحافظات (Governorate Repository Interface).
 */
interface GovernorateRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب المحافظات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['directorates']): Collection|CursorPaginator;

    /**
     * جلب المحافظات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['directorates']): Collection|CursorPaginator;

    /**
     * البحث عن محافظة بالاسم.
     */
    public function findByName(string $name): ?Governorate;

    /**
     * جلب كافة المحافظات مع أعداد المديريات ونقاط البيع والشبكات.
     */
    public function getWithCounts(): Collection;
}
