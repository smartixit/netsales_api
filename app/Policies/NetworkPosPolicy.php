<?php

namespace App\Policies;

use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkPosPolicy
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
        return app(TenantPermissionService::class)->allowsAnyNetwork($user, 'networks.view')
            || app(TenantPermissionService::class)->allowsAnyPos($user, 'pos.view');
    }

    public function view(User $user, NetworkPos $networkPos): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsNetwork($user, (int) $networkPos->network_id, 'networks.view')
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
