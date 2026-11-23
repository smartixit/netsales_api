<?php

namespace App\Policies;

use App\Models\NetworkWallet;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkWalletPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPlatform($user, 'network_wallets.view')
            || $permissions->allowsAnyNetwork($user, 'network_wallets.view');
    }

    public function view(User $user, NetworkWallet $wallet): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPlatform($user, 'network_wallets.view')
            || $permissions->allowsNetwork($user, (int) $wallet->network_id, 'network_wallets.view');
    }

    public function createForNetwork(User $user, int $networkId): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            $networkId,
            'network_wallets.create'
        );
    }

    public function deposit(User $user, NetworkWallet $wallet): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'network_wallets.deposit'
        );
    }

    public function withdraw(User $user, NetworkWallet $wallet): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'network_wallets.withdraw'
        );
    }

    public function update(User $user, NetworkWallet $wallet): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'network_wallets.edit'
        );
    }

    public function manageLifecycle(User $user): bool
    {
        return false;
    }
}
