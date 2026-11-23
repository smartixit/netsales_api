<?php

namespace App\Policies;

use App\Models\PosWallet;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosWalletPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPlatform($user, 'pos_wallets.view')
            || $permissions->allowsAnyNetwork($user, 'pos_wallets.view')
            || $permissions->allowsAnyPos($user, 'pos_wallets.view');
    }

    public function view(User $user, PosWallet $wallet): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPlatform($user, 'pos_wallets.view')
            || $permissions->allowsNetwork(
                $user,
                (int) $wallet->network_id,
                'pos_wallets.view'
            ) || $permissions->allowsPos(
            $user,
            (int) $wallet->pos_id,
            'pos_wallets.view'
        );
    }

    public function createForNetwork(User $user, int $networkId, int $posId): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            $networkId,
            'pos_wallets.create'
        );
    }

    public function deposit(User $user, PosWallet $wallet): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.deposit'
        );
    }

    public function withdraw(User $user, PosWallet $wallet): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.withdraw'
        );
    }

    public function transferFromNetwork(User $user, int $networkId, int $posId): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            $networkId,
            'pos_wallets.transfer_from_network'
        );
    }

    public function transferToNetwork(User $user, int $networkId, int $posId): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
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
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.edit'
        );
    }

    public function delete(User $user, PosWallet $wallet): bool
    {
        return false;
    }

    public function manageLifecycle(User $user): bool
    {
        return false;
    }

    public function manageCreditLimit(User $user, PosWallet $wallet): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $wallet->network_id,
            'pos_wallets.manage_credit_limit'
        );
    }
}
