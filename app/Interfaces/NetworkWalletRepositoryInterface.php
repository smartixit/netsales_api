<?php

namespace App\Interfaces;

use App\Models\NetworkWallet;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * واجهة مستودع محافظ مزودي الشبكات (Network Wallet Repository Interface).
 */
interface NetworkWalletRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب قائمة محافظ الشبكات مع تطبيق الفلاتر والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, NetworkWallet>|CursorPaginator
     */
    public function getFiltered(array $filters = [], array $relations = ['network']): Collection|CursorPaginator;

    /**
     * جلب المحافظ المحذوفة مع الفلترة والترقيم.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $relations
     * @return Collection<int, NetworkWallet>|CursorPaginator
     */
    public function getTrashedFiltered(array $filters = [], array $relations = ['network']): Collection|CursorPaginator;

    /**
     * البحث عن محفظة شبكة عبر معرف الشبكة مع خيار القفل المتشائم.
     */
    public function findByNetworkId(int $networkId, bool $lock = false): ?NetworkWallet;

    /**
     * البحث عن محفظة شبكة عبر المعرف الفريد UUID مع خيار القفل المتشائم.
     */
    public function findByUuid(string $uuid, bool $lock = false): ?NetworkWallet;

    /**
     * زيادة رصيد المحفظة بمقدار محدد.
     */
    public function incrementBalance(int $walletId, float $amount): bool;

    /**
     * خصم من رصيد المحفظة بمقدار محدد.
     */
    public function decrementBalance(int $walletId, float $amount): bool;

    /**
     * تحديث حالة المحفظة (تفعيل/تعطيل).
     */
    public function updateStatus(int $walletId, bool $status): bool;
}
