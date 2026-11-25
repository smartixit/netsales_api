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

    public function viewAgreements(User $user, NetworkPos $networkPos): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPlatform($user, 'networks.view_pos_agreements')
            || $permissions->allowsNetwork($user, (int) $networkPos->network_id, 'networks.view_pos_agreements')
            || $permissions->allowsPos($user, (int) $networkPos->pos_id, 'pos.view_network_agreements');
    }

    public function manageAgreements(User $user, NetworkPos $networkPos): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsNetwork($user, (int) $networkPos->network_id, 'networks.manage_pos_agreements')
            || $permissions->allowsPos($user, (int) $networkPos->pos_id, 'pos.manage_network_agreements');
    }

    public function activateAgreement(User $user, NetworkPos $networkPos): bool
    {
        return $this->manageAgreements($user, $networkPos);
    }

    public function terminateAgreement(User $user, NetworkPos $networkPos): bool
    {
        return $this->manageAgreements($user, $networkPos);
    }

    public function viewEvents(User $user, NetworkPos $networkPos): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPlatform($user, 'networks.view_pos_link_events')
            || $permissions->allowsNetwork($user, (int) $networkPos->network_id, 'networks.view_pos_link_events')
            || $permissions->allowsPos($user, (int) $networkPos->pos_id, 'pos.view_network_link_events');
    }

    public function restore(User $user, NetworkPos $networkPos): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsNetwork($user, (int) $networkPos->network_id, 'networks.restore_pos_link')
            || $permissions->allowsPos($user, (int) $networkPos->pos_id, 'pos.restore_network_link');
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
