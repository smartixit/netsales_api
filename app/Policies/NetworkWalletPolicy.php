<?php

namespace App\Policies;

use App\Models\NetworkWallet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkWalletPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->networks()->exists()
            || $user->networkUsers()->where('status', 'ACTIVE')->exists();
    }

    public function view(User $user, NetworkWallet $wallet): bool
    {
        // 1. Check if user is owner of the network
        if ($user->networks()->where('networks.network_id', $wallet->network_id)->exists()) {
            return true;
        }

        // 2. Check if user belongs to the network
        if ($user->networkUsers()
            ->where('network_id', $wallet->network_id)
            ->where('status', 'ACTIVE')
            ->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Creating a network wallet is allowed only for an explicitly authorized
     * platform actor or an active member holding the permission in this network.
     */
    public function createForNetwork(User $user, int $networkId): bool
    {
        return $this->hasNetworkPermission($user, $networkId, 'network_wallets.create');
    }

    /**
     * Initial balance funding is a privileged financial operation.
     */
    public function deposit(User $user, NetworkWallet $wallet): bool
    {
        return $this->hasNetworkPermission(
            $user,
            (int) $wallet->network_id,
            'network_wallets.create',
        );
    }

    /**
     * Direct withdrawals/status changes require the network-scoped edit grant.
     */
    public function withdraw(User $user, NetworkWallet $wallet): bool
    {
        return $this->hasNetworkPermission(
            $user,
            (int) $wallet->network_id,
            'network_wallets.edit',
        );
    }

    public function update(User $user, NetworkWallet $wallet): bool
    {
        return $this->hasNetworkPermission(
            $user,
            (int) $wallet->network_id,
            'network_wallets.edit',
        );
    }

    /**
     * Wallet deletion, restoration and force deletion remain platform-only.
     * The before() hook grants this ability to Super Admin.
     */
    public function manageLifecycle(User $user): bool
    {
        return false;
    }

    private function hasNetworkPermission(User $user, int $networkId, string $permission): bool
    {
        if ($networkId <= 0) {
            return false;
        }

        $keys = [$permission, strtoupper($permission)];

        if ($user->permissions()->whereIn('permission_key', $keys)->exists()) {
            return true;
        }

        // A global permission may be granted only by a platform-scoped role.
        if ($user->role()
            ->where('scope', 'PLATFORM')
            ->whereHas('permissions', fn ($query) => $query->whereIn('permission_key', $keys))
            ->exists()) {
            return true;
        }

        return $user->networkUsers()
            ->where('network_id', $networkId)
            ->where('status', 'ACTIVE')
            ->whereHas('role.permissions', fn ($query) => $query->whereIn('permission_key', $keys))
            ->exists();
    }
}
