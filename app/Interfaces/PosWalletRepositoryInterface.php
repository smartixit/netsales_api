<?php

namespace App\Interfaces;

use App\Models\PosWallet;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع محافظ نقاط البيع (POS Wallet Repository Interface).
 */
interface PosWalletRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة محافظ نقاط البيع مع تطبيق الفلاتر والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, PosWallet>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator;

    /**
     * جلب المحافظ المحذوفة لنقاط البيع مع الفلترة والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, PosWallet>|CursorPaginator
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network', 'pos']): Collection|CursorPaginator;

    /**
     * البحث عن محفظة نقطة بيع لشبكة محددة مع خيار القفل المتشائم.
     */
    public function findByNetworkAndPos(int $networkId, int $posId, bool $lock = false): ?PosWallet;

    /**
     * البحث عن محفظة نقطة بيع عبر المعرف الفريد UUID مع خيار القفل المتشائم.
     */
    public function findByUuid(string $uuid, bool $lock = false): ?PosWallet;

    /**
     * زيادة رصيد محفظة نقطة البيع بمقدار محدد.
     */
    public function incrementBalance(int $walletId, float $amount): bool;

    /**
     * خصم من رصيد محفظة نقطة البيع بمقدار محدد.
     */
    public function decrementBalance(int $walletId, float $amount): bool;

    /**
     * تحديث حالة المحفظة (تفعيل/تعطيل).
     */
    public function updateStatus(int $walletId, bool $status): bool;
}
