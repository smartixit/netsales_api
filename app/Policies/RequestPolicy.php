<?php

namespace App\Policies;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class RequestPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return app(TenantPermissionService::class)->allowsAnyOperational($user, 'requests.view');
    }

    public function view(User $user, RequestModel $request): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsNetwork($user, (int) $request->network_id, 'requests.view')
            || $permissions->allowsPos($user, (int) $request->pos_id, 'requests.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RequestModel $request): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsPos($user, (int) $request->pos_id, 'requests.update)
            || $permissions->allowsNetwork($user, (int) $request->network_id, 'requests.edit');
    }

    public function review(User $user, RequestModel $request): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $request->network_id,
            'requests.approve'
        );
    }

    public function approve(User $user, RequestModel $request): bool
    {
        return $this->review($user, $request);
    }

    public function delete(User $user, RequestModel $request): bool
    {
        return app(TenantPermissionService::class)->allowsNetwork(
            $user,
            (int) $request->network_id,
            'requests.delete'
        ) || app(TenantPermissionService::class)->allowsPos(
            $user,
            (int) $request->pos_id,
            'requests.delete'
        );
    }
}
