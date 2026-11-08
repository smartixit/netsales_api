<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoucherPackageDiscount;
use App\Services\TenantAuthorizationService;
use Illuminate\Auth\Access\HandlesAuthorization;

class VoucherPackageDiscountPolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected TenantAuthorizationService $tenantAuth
    ) {
    }

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        $networkIds = $this->tenantAuth->getUserAuthorizedNetworkIds($user, 'voucher_packages.view');
        $posIds = $this->tenantAuth->getUserAuthorizedPosIds($user, 'voucher_packages.view');

        return $networkIds === null || !empty($networkIds) || !empty($posIds);
    }

    public function view(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canViewDiscount($user, $discount);
    }

    public function create(User $user, ?int $networkId = null): bool
    {
        if ($networkId !== null) {
            return $this->tenantAuth->canManageNetworkPackages($user, $networkId, 'voucher_packages.create');
        }

        $networkIds = $this->tenantAuth->getUserAuthorizedNetworkIds($user, 'voucher_packages.create');

        return $networkIds === null || !empty($networkIds);
    }

    public function update(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canManageDiscount($user, $discount, 'voucher_packages.edit');
    }

    public function delete(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canManageDiscount($user, $discount, 'voucher_packages.delete');
    }

    public function restore(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canManageDiscount($user, $discount, 'voucher_packages.delete');
    }

    public function forceDelete(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canManageDiscount($user, $discount, 'voucher_packages.delete');
    }
}

