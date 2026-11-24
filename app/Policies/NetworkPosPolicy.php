<?php

namespace App\Policies;

use App\Models\NetworkPos;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkPosPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPlatform($user, 'networks.view')
            || $permissions->allowsPlatform($user, 'pos.view')
            || $permissions->allowsAnyNetwork($user, 'networks.view')
            || $permissions->allowsAnyPos($user, 'pos.view');
    }

    public function view(User $user, NetworkPos $networkPos): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPlatform($user, 'networks.view')
            || $permissions->allowsPlatform($user, 'pos.view')
            || $permissions->allowsNetwork($user, (int) $networkPos->network_id, 'networks.view')
            || $permissions->allowsPos($user, (int) $networkPos->pos_id, 'pos.view');
    }

    public function create(User $user, int $networkId): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            $networkId,
            'networks.create_pos_link'
        );
    }

    public function update(User $user, NetworkPos $networkPos): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $networkPos->network_id,
            'networks.update_pos_link'
        );
    }

    public function toggle(User $user, NetworkPos $networkPos): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $networkPos->network_id,
            'networks.toggle_pos_link'
        );
    }

    public function delete(User $user, NetworkPos $networkPos): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $networkPos->network_id,
            'networks.delete_pos_link'
        );
    }
}
