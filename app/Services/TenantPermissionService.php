<?php

namespace App\Services;

use App\Enums\RoleScope;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\User;

final class TenantPermissionService
{
    public function allowsNetwork(User $user, int $networkId, string $permission): bool
    {
        $networkOwned = Network::query()
            ->where('network_id', $networkId)
            ->where('owner_id', $user->user_id)
            ->exists();

        if ($networkOwned && $this->roleAllows($user, RoleScope::NETWORK, $permission)) {
            return true;
        }

        return NetworkUser::query()
            ->where('network_id', $networkId)
            ->where('user_id', $user->user_id)
            ->where('status', 'ACTIVE')
            ->whereHas('role', fn ($query) => $query
                ->where('scope', RoleScope::NETWORK->value)
                ->where('status', true)
                ->whereHas('permissions', fn ($permissions) => $permissions
                    ->where('permission_key', $permission)
                    ->where('status', true)))
            ->exists();
    }

    public function allowsPos(User $user, int $posId, string $permission): bool
    {
        $posOwned = Pos::query()
            ->where('pos_id', $posId)
            ->where('owner_id', $user->user_id)
            ->exists();

        if ($posOwned && $this->roleAllows($user, RoleScope::POS, $permission)) {
            return true;
        }

        return PosUser::query()
            ->where('pos_id', $posId)
            ->where('user_id', $user->user_id)
            ->where('status', 'ACTIVE')
            ->whereHas('role', fn ($query) => $query
                ->where('scope', RoleScope::POS->value)
                ->where('status', true)
                ->whereHas('permissions', fn ($permissions) => $permissions
                    ->where('permission_key', $permission)->where('status', true)))
            ->exists();
    }

    private function roleAllows(User $user, RoleScope $scope, string $permission): bool
    {
        return $user->role()
            ->where('scope', $scope->value)
            ->where('status', true)
            ->whereHas('permissions', fn ($query) => $query->where('permission_key', $permission))
            ->exists();
    }
}
