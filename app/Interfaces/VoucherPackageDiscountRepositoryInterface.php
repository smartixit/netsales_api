<?php

namespace App\Interfaces;

use App\Models\VoucherPackageDiscount;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع إدارة خصومات باقات الكروت (Voucher Package Discount Repository Interface).
 */
interface VoucherPackageDiscountRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة الخصومات مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, VoucherPackageDiscount>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator;

    /**
     * البحث عن خصم مخصص لنقطة بيع على باقة معينة لشبكة محددة.
     */
    public function findByNetworkPosAndPackage(int $networkId, int $posId, int $packageId): ?VoucherPackageDiscount;

    /**
     * البحث عن الخصم الفعلي الساري حالياً لنقطة بيع على باقة معينة لشبكة محددة وفق قيود الصلاحية الزمنية.
     */
    public function findEffectiveDiscount(int $networkId, int $posId, int $packageId): ?VoucherPackageDiscount;

    /**
     * جلب كافة خصومات نقطة بيع محددة.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, VoucherPackageDiscount>|CursorPaginator
     */
    public function getDiscountsForPos(int $posId, array $filters = []): Collection|CursorPaginator;

    /**
     * جلب كافة الخصومات المحددة على باقة كروت معينة.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, VoucherPackageDiscount>|CursorPaginator
     */
    public function getDiscountsForPackage(int $packageId, array $filters = []): Collection|CursorPaginator;

    /**
     * جلب قائمة الخصومات المحذوفة مؤقتاً مع الفلترة والترقيم بالمؤشر.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getTrashed(int $perPage = 15, array $filters = []): CursorPaginator;
}
