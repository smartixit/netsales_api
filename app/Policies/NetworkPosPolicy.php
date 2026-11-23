<?php

namespace App\Policies;

use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkPosPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly TenantPermissionService $tenantPermissions
    ) {}

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $this->tenantPermissions->allowsAnyNetwork($user, 'network_pos.view')
            || $this->tenantPermissions->allowsAnyPos($user, 'network_pos.view');
    }

    public function view(User $user, NetworkPos $networkPos): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $networkPos->network_id,
            'network_pos.view'
        ) || $this->tenantPermissions->allowsPos(
            $user,
            (int) $networkPos->pos_id,
            'network_pos.view'
        );
    }

    public function create(User $user, int $networkId): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            $networkId,
            'network_pos.create'
        );
    }

    public function update(User $user, NetworkPos $networkPos): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $networkPos->network_id,
            'network_pos.update'
        );
    }

    public function toggleStatus(User $user, NetworkPos $networkPos): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $networkPos->network_id,
            'network_pos.toggle_status'
        );
    }

    public function delete(User $user, NetworkPos $networkPos): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $networkPos->network_id,
            'network_pos.delete'
        );
    }
}
