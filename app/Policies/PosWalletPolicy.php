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

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PosWallet $wallet): bool
    {
        return false;
    }

    public function delete(User $user, PosWallet $wallet): bool
    {
        return false;
    }

    public function manageCreditLimit(User $user, PosWallet $wallet): bool
    {
        // 1. POS users can NEVER manage or modify their own credit limit
        if ($user->pos && (int) $user->pos->pos_id === (int) $wallet->pos_id) {
            return false;
        }
        if (method_exists($user, 'posUsers') && $user->posUsers()->where('pos_id', $wallet->pos_id)->exists()) {
            return false;
        }

        // 2. Network owner of the associated Network can manage credit limit
        if ($user->networks()->where('networks.network_id', $wallet->network_id)->exists()) {
            return true;
        }

        // 3. Network user with pos_wallets.manage permission
        if (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $wallet->network_id)->exists()) {
            return $user->hasPermission('pos_wallets.manage');
        }

        return false;
    }
}
