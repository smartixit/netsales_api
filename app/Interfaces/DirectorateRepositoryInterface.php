<?php

namespace App\Interfaces;

use App\Models\Directorate;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع المديريات (Directorate Repository Interface).
 */
interface DirectorateRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب المديريات المفلترة مع الترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['governorate']): Collection|CursorPaginator;

    /**
     * جلب المديريات المحذوفة ناعماً مع الفلترة والترقيم بالمؤشر.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['governorate']): Collection|CursorPaginator;

    /**
     * جلب كافة مديريات محافظة معينة.
     */
    public function getByGovernorate(int $governorateId): Collection;

    /**
     * البحث عن مديرية باسمها ضمن محافظة محددة.
     */
    public function findByNameAndGovernorate(string $name, int $governorateId): ?Directorate;
}
