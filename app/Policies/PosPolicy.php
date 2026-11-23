<?php

namespace App\Policies;

use App\Models\NetworkPos;
use App\Models\Pos;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        $permissions = app(TenantPermissionService::class);

        if ($permissions->allowsPlatform($user, 'pos.view')
            || $permissions->allowsAnyPos($user, 'pos.view')
        ) {
            return true;
        }

        $networkIds = $permissions->authorizedNetworkIds($user, 'pos.view');

        return $networkIds !== []
            && NetworkPos::query()->whereIn('network_id', $networkIds)->exists();
    }

    public function view(User $user, Pos $pos): bool
    {
        $permissions = app(TenantPermissionService::class);

        if ($permissions->allowsPlatform($user, 'pos.view')
            || $permissions->allowsPos($user, (int) $pos->pos_id, 'pos.view')
        ) {
            return true;
        }

        $networkIds = $permissions->authorizedNetworkIds($user, 'pos.view');

        return $networkIds !== []
            && NetworkPos::query()
                ->where('pos_id', $pos->pos_id)
                ->whereIn('network_id', $networkIds)
                ->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Pos $pos): bool
    {
        return app(TenantPermissionService::class)->allowsPos(
            $user,
            (int) $pos->pos_id,
            'pos.edit'
        );
    }

    public function delete(User $user, Pos $pos): bool
    {
        return app(TenantPermissionService::class)->allowsPos(
            $user,
            (int) $pos->pos_id,
            'pos.delete'
        );
    }

    public function approve(User $user, Pos $pos): bool
    {
        return app(TenantPermissionService::class)->allowsPlatform($user, 'pos.approve');
    }

    public function reject(User $user, Pos $pos): bool
    {
        return app(TenantPermissionService::class)->allowsPlatform($user, 'pos.reject');
    }
}
