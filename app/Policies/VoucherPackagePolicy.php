<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VoucherPackage;
use App\Services\TenantAuthorizationService;
use Illuminate\Auth\Access\HandlesAuthorization;

class VoucherPackagePolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected TenantAuthorizationService $tenantAuth
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        $networkIds = $this->tenantAuth->getViewableNetworkIdsForPackages($user);

        return $networkIds === null || ! empty($networkIds);
    }

    public function view(User $user, VoucherPackage $package): bool
    {
        return $this->tenantAuth->canViewPackage($user, $package);
    }

    public function create(User $user, ?int $networkId = null): bool
    {
        if ($networkId !== null) {
            return $this->tenantAuth->canManageNetworkPackages($user, $networkId, 'voucher_packages.create');
        }

        $networkIds = $this->tenantAuth->getUserAuthorizedNetworkIds($user, 'voucher_packages.create');

        return $networkIds === null || ! empty($networkIds);
    }

    public function update(User $user, VoucherPackage $package): bool
    {
        return $this->tenantAuth->canManagePackage($user, $package, 'voucher_packages.edit');
    }

    public function delete(User $user, VoucherPackage $package): bool
    {
        return $this->tenantAuth->canManagePackage($user, $package, 'voucher_packages.delete');
    }

    public function restore(User $user, VoucherPackage $package): bool
    {
        return $this->tenantAuth->canManagePackage($user, $package, 'voucher_packages.delete');
    }

    public function forceDelete(User $user, VoucherPackage $package): bool
    {
        return $this->tenantAuth->canManagePackage($user, $package, 'voucher_packages.delete');
    }
}
