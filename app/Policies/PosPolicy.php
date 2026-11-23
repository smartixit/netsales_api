<?php

namespace App\Policies;

use App\Models\Pos;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosPolicy
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
        return $this->tenantPermissions->allowsAnyPos($user, 'pos.view')
            || $this->tenantPermissions->allowsAnyNetwork($user, 'pos.view');
    }

    public function view(User $user, Pos $pos): bool
    {
        if ($this->tenantPermissions->allowsPos($user, (int) $pos->pos_id, 'pos.view')) {
            return true;
        }

        return $this->tenantPermissions->allowsAnyNetwork($user, 'pos.view')
            && $pos->networkPos()
                ->where('status', true)
                ->whereIn('network_id', $this->tenantPermissions->authorizedNetworkIds($user, 'pos.view'))
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pos.create');
    }

    public function update(User $user, Pos $pos): bool
    {
        return $this->tenantPermissions->allowsPos(
            $user,
            (int) $pos->pos_id,
            'pos.update'
        );
    }

    public function delete(User $user, Pos $pos): bool
    {
        return $user->hasPermission('pos.delete');
    }

    public function restore(User $user, Pos $pos): bool
    {
        return $user->hasPermission('pos.restore');
    }

    public function forceDelete(User $user, Pos $pos): bool
    {
        return $user->hasPermission('pos.force_delete');
    }

    public function approve(User $user, Pos $pos): bool
    {
        return $user->hasPermission('pos.approve');
    }

    public function reject(User $user, Pos $pos): bool
    {
        return $user->hasPermission('pos.reject');
    }
}
