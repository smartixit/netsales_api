<?php

namespace App\Interfaces;

use App\Models\Network;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إدارة مزودي الشبكات (Network Repository Interface).
 */
interface NetworkRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة مزودي الشبكات مع تطبيق الفلاتر والبحث والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, Network>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * تحديث رقم الهاتف لجميع الشبكات التي يمتلكها المستخدم (owner_id).
     * @param int $ownerId معرف المستخدم.
     * @param string $phone رقم الهاتف الجديد.
     * @return int عدد السجلات المعدلة.
     */
    public function syncPhoneForUser(int $ownerId, string $phone): int;

    /**
     * البحث عن شبكة عبر رقم هاتفها.
     */
    public function findByPhone(string $phone): ?Network;

    /**
     * البحث عن شبكة عبر معرف UUID.
     */
    public function findByUuid(string $uuid): ?Network;

    /**
     * التحقق من تفرد اسم الشبكة.
     */
    public function isNameUnique(string $name, ?int $ignoreId = null): bool;

    /**
     * جلب الشبكة مع مالكها ونقاط البيع التابعة لها.
     */
    public function findWithStakeholders(int $networkId): ?Network;
}
