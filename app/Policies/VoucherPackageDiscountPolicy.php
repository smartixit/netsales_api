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
    ) {}

    public function viewAny(User $user): bool
    {
        $networkIds = $this->tenantAuth->getUserAuthorizedNetworkIds($user, 'voucher_package_discounts.view');
        $posIds = $this->tenantAuth->getUserAuthorizedPosIds($user, 'voucher_package_discounts.view');

        return $networkIds !== [] || $posIds !== [];
    }

    public function view(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canViewDiscount($user, $discount);
    }

    public function create(User $user, ?int $networkId = null): bool
    {
        if ($networkId !== null) {
            return $this->tenantAuth->canManageNetworkPackages($user, $networkId, 'voucher_package_discounts.create');
        }

        $networkIds = $this->tenantAuth->getUserAuthorizedNetworkIds($user, 'voucher_package_discounts.create');

        return $networkIds !== [];
    }

    public function update(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canManageDiscount($user, $discount, 'voucher_package_discounts.edit');
    }

    public function delete(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canManageDiscount($user, $discount, 'voucher_package_discounts.delete');
    }

    public function restore(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canManageDiscount($user, $discount, 'voucher_package_discounts.restore');
    }

    public function forceDelete(User $user, VoucherPackageDiscount $discount): bool
    {
        return $this->tenantAuth->canManageDiscount($user, $discount, 'voucher_package_discounts.force_delete');
    }
}
