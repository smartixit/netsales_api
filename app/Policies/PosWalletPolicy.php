<?php

namespace App\Policies;

use App\Models\PosWallet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosWalletPolicy
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
        return $user->pos()->exists()
            || $user->posUsers()->where('status', 'ACTIVE')->exists()
            || $user->networks()->exists()
            || $user->networkUsers()->where('status', 'ACTIVE')->exists();
    }

    public function view(User $user, PosWallet $wallet): bool
    {
        // 1. Check if user is owner of the POS
        if ($user->pos && (int) $user->pos->pos_id === (int) $wallet->pos_id) {
            return true;
        }

        // 2. Check if user belongs to the POS
        if ($user->posUsers()->where('pos_id', $wallet->pos_id)->where('status', 'ACTIVE')->exists()) {
            return true;
        }

        // 3. Check if user is owner or member of the associated Network
        if ($user->networks()->where('networks.network_id', $wallet->network_id)->exists()) {
            return true;
        }
        if ($user->networkUsers()->where('network_id', $wallet->network_id)->where('status', 'ACTIVE')->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Create a POS wallet only for an actor with permission in the owning
     * network or in the specific POS membership.
     */
    public function createForNetwork(User $user, int $networkId, int $posId): bool
    {
        return $this->hasPosWalletPermission($user, $networkId, $posId, 'pos_wallets.create');
    }

    public function deposit(User $user, PosWallet $wallet): bool
    {
        return $this->hasPosWalletPermission(
            $user,
            (int) $wallet->network_id,
            (int) $wallet->pos_id,
            'pos_wallets.create',
        );
    }

    public function withdraw(User $user, PosWallet $wallet): bool
    {
        return $this->hasPosWalletPermission(
            $user,
            (int) $wallet->network_id,
            (int) $wallet->pos_id,
            'pos_wallets.edit',
        );
    }

    public function transferFromNetwork(User $user, int $networkId, int $posId): bool
    {
        return $this->hasPosWalletPermission($user, $networkId, $posId, 'pos_wallets.create');
    }

    public function transferToNetwork(User $user, int $networkId, int $posId): bool
    {
        return $this->hasPosWalletPermission($user, $networkId, $posId, 'pos_wallets.edit');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PosWallet $wallet): bool
    {
        return $this->hasPosWalletPermission(
            $user,
            (int) $wallet->network_id,
            (int) $wallet->pos_id,
            'pos_wallets.edit',
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
        // A POS owner/member must never change its own credit facility.
        if ($user->pos && (int) $user->pos->pos_id === (int) $wallet->pos_id) {
            return false;
        }
        if ($user->posUsers()
            ->where('pos_id', $wallet->pos_id)
            ->where('status', 'ACTIVE')
            ->exists()) {
            return false;
        }

        return $this->hasPosWalletPermission(
            $user,
            (int) $wallet->network_id,
            (int) $wallet->pos_id,
            'pos_wallets.manage',
        );
    }

    /**
     * Resolve direct/platform grants globally and tenant role grants only
     * through the exact ACTIVE network/POS membership. Do not use
     * User::hasPermission($key, $tenantId) here because the same numeric ID
     * can exist in both network_id and pos_id namespaces.
     */
    private function hasPosWalletPermission(
        User $user,
        int $networkId,
        int $posId,
        string $permission,
    ): bool {
        if ($networkId <= 0 || $posId <= 0) {
            return false;
        }

        $keys = [$permission, strtoupper($permission)];

        if ($user->permissions()->whereIn('permission_key', $keys)->exists()) {
            return true;
        }

        if ($user->role()
            ->where('scope', 'PLATFORM')
            ->whereHas('permissions', fn ($query) => $query->whereIn('permission_key', $keys))
            ->exists()) {
            return true;
        }

        $networkMembershipGrant = $user->networkUsers()
            ->where('network_id', $networkId)
            ->where('status', 'ACTIVE')
            ->whereHas('role.permissions', fn ($query) => $query->whereIn('permission_key', $keys))
            ->exists();

        if ($networkMembershipGrant) {
            return true;
        }

        return $user->posUsers()
            ->where('pos_id', $posId)
            ->where('status', 'ACTIVE')
            ->whereHas('role.permissions', fn ($query) => $query->whereIn('permission_key', $keys))
            ->exists();
    }
}
