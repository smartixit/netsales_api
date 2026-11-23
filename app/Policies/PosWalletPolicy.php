<?php

namespace App\Policies;

use App\Models\PosWallet;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosWalletPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly TenantPermissionService $tenantPermissions
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
        return $this->tenantPermissions->allowsAnyNetwork($user, 'pos_wallets.view')
            || $this->tenantPermissions->allowsAnyPos($user, 'pos_wallets.view');
    }

    public function view(User $user, PosWallet $wallet): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.view'
        ) || $this->tenantPermissions->allowsPos(
            $user,
            (int) $wallet->pos_id,
            'pos_wallets.view'
        );
    }

    public function createForNetwork(User $user, int $networkId, int $posId): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            $networkId,
            'pos_wallets.create_wallet'
        );
    }

    public function deposit(User $user, PosWallet $wallet): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.deposit'
        );
    }

    public function withdraw(User $user, PosWallet $wallet): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.withdraw'
        );
    }

    public function transferFromNetwork(User $user, int $networkId, int $posId): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            $networkId,
            'pos_wallets.transfer_from_network'
        );
    }

    public function transferToNetwork(User $user, int $networkId, int $posId): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            $networkId,
            'pos_wallets.transfer_to_network'
        );
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PosWallet $wallet): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.update_wallet_status'
        );
    }

    /**
     * Lifecycle operations (delete/restore/force-delete) stay platform-only.
     */
    public function manageLifecycle(User $user): bool
    {
        return false;
    }

    public function delete(User $user, PosWallet $wallet): bool
    {
        return false;
    }

    public function manageCreditLimit(User $user, PosWallet $wallet): bool
    {
        // The POS owner or member must never modify its own credit facility.
        if ($this->tenantPermissions->allowsPos(
            $user,
            (int) $wallet->pos_id,
            'pos_wallets.view'
        )) {
            return false;
        }

        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.manage_credit_limit'
        );
    }
}
