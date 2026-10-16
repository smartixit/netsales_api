<?php

namespace App\Interfaces;

use App\Models\Pos;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إدارة نقاط البيع (POS Repository Interface).
 */
interface PosRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة نقاط البيع مع تطبيق الفلاتر والبحث والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, Pos>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * البحث عن نقطة بيع عبر رقم هاتفها.
     */
    public function findByPhone(string $phone): ?Pos;

    /**
     * البحث عن نقطة بيع عبر معرف UUID.
     */
    public function findByUuid(string $uuid): ?Pos;

    /**
     * التحقق من تفرد اسم نقطة البيع.
     */
    public function isNameUnique(string $name, ?int $ignoreId = null): bool;
    /**
     * تحديث رقم الهاتف لنقطة البيع التي يمتلكها المستخدم (owner_id).
     * @param int $ownerId معرف المستخدم.
     * @param string $phone رقم الهاتف الجديد.
     * @return int عدد السجلات المعدلة.
     */
    public function syncPhoneForUser(int $ownerId, string $phone): int;

}
