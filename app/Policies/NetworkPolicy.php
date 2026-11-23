<?php

namespace App\Policies;

use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        $permissions = app(TenantPermissionService::class);

        if ($permissions->allowsAnyNetwork($user, 'networks.view')) {
            return true;
        }

        $posIds = $permissions->authorizedPosIds($user, 'networks.view');

        return $posIds !== []
            && NetworkPos::query()->whereIn('pos_id', $posIds)->exists();
    }

    public function view(User $user, Network $network): bool
    {
        $permissions = app(TenantPermissionService::class);

        if ($permissions->allowsNetwork($user, (int) $network->network_id, 'networks.view')) {
            return true;
        }

        $posIds = $permissions->authorizedPosIds($user, 'networks.view');

        return $posIds !== []
            && NetworkPos::query()
                ->where('network_id', $network->network_id)
                ->whereIn('pos_id', $posIds)
                ->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Network $network): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $network->network_id,
            'networks.edit'
        );
    }

    public function delete(User $user, Network $network): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $network->network_id,
            'networks.delete'
        );
    }

    public function approve(User $user, Network $network): bool
    {
        return $user->isSuperAdmin();
    }

    public function reject(User $user, Network $network): bool
    {
        return $user->isSuperAdmin();
    }
}
