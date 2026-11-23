<?php

namespace App\Policies;

use App\Enums\RoleKey;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkPolicy
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
        return $this->tenantPermissions->allowsAnyNetwork($user, 'networks.view')
            || $this->tenantPermissions->allowsAnyPos($user, 'networks.view');
    }

    public function view(User $user, Network $network): bool
    {
        if ($this->tenantPermissions->allowsNetwork($user, (int) $network->network_id, 'networks.view')) {
            return true;
        }

        $authorizedPosIds = $this->tenantPermissions->authorizedPosIds($user, 'network_pos.view');
        if ($authorizedPosIds === []) {
            return false;
        }

        return NetworkPos::query()
            ->where('network_id', $network->network_id)
            ->whereIn('pos_id', $authorizedPosIds)
            ->where('status', true)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('networks.create');
    }

    public function update(User $user, Network $network): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $network->network_id,
            'networks.update'
        );
    }

    public function delete(User $user, Network $network): bool
    {
        return $this->tenantPermissions->allowsNetwork(
            $user,
            (int) $network->network_id,
            'networks.delete'
        );
    }

    public function restore(User $user, Network $network): bool
    {
        return $user->hasPermission('networks.restore');
    }

    public function forceDelete(User $user, Network $network): bool
    {
        return $user->hasPermission('networks.force_delete');
    }

    public function approve(User $user, Network $network): bool
    {
        return $user->hasPermission('networks.approve');
    }

    public function reject(User $user, Network $network): bool
    {
        return $user->hasPermission('networks.reject');
    }
}
