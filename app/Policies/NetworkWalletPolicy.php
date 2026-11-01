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
        return true;
    }

    public function view(User $user, NetworkWallet $wallet): bool
    {
        // 1. Check if user is owner of the network
        if ($user->networks()->where('networks.network_id', $wallet->network_id)->exists()) {
            return true;
        }

        // 2. Check if user belongs to the network
        if (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $wallet->network_id)->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NetworkWallet $wallet): bool
    {
        return false;
    }

    public function delete(User $user, NetworkWallet $wallet): bool
    {
        return false;
    }
}
