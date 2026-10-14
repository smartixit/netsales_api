<?php

namespace App\Interfaces;

use App\Models\VoucherPackage;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إدارة باقات وكروت الإنترنت (Voucher Package Repository Interface).
 */
interface VoucherPackageRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة الباقات مع الفلترة والبحث والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, VoucherPackage>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * البحث عن باقة عبر معرف الشبكة واسم الباقة.
     */
    public function findByNetworkAndName(int $networkId, string $packageName): ?VoucherPackage;

    /**
     * جلب باقات شبكة معينة.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, VoucherPackage>|CursorPaginator
     */
    public function getForNetwork(int $networkId, array $filters = []): Collection|CursorPaginator;
}
