<?php

namespace App\Interfaces;

use App\Enums\RequestStatus;
use App\Models\Request as RequestModel;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إدارة طلبات الكروت (Voucher Request Repository Interface).
 */
interface RequestRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة الطلبات مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, RequestModel>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos', 'createdBy', 'reviewedBy', 'items.voucherPackage']): Collection|CursorPaginator;

    /**
     * جلب الطلبات المحذوفة مؤقتاً مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, RequestModel>|CursorPaginator
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator;

    /**
     * البحث عن طلب عبر معرف الـ UUID.
     */
    public function findByUuid(string $uuid, bool $lock = false): ?RequestModel;

    /**
     * تحديث حالة الطلب مع توثيق المراجع والتاريخ والملاحظة.
     */
    public function updateStatus(int $requestId, RequestStatus $status, ?int $reviewedBy = null, ?string $note = null): bool;
}
