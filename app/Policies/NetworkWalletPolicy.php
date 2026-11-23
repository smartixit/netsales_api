<?php

namespace App\Policies;

use App\Models\NetworkWallet;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkWalletPolicy
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
        return $this->tenantPermissions->allowsAnyNetwork($user, 'network_wallets.view');
    }

    public function view(User $user, NetworkWallet $wallet): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'network_wallets.view'
        );
    }

    public function createForNetwork(User $user, int $networkId): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            $networkId,
            'network_wallets.create_wallet'
        );
    }

    public function deposit(User $user, NetworkWallet $wallet): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'network_wallets.deposit'
        );
    }

    public function withdraw(User $user, NetworkWallet $wallet): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'network_wallets.withdraw'
        );
    }

    public function update(User $user, NetworkWallet $wallet): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'network_wallets.update_wallet_status'
        );
    }

    /**
     * Wallet deletion, restoration and force deletion remain platform-only.
     * The before() hook grants this lifecycle authority to Super Admin only.
     */
    public function manageLifecycle(User $user): bool
    {
        return false;
    }
}
