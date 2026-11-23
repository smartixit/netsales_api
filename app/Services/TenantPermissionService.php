<?php

namespace App\Services;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\User;

final class TenantPermissionService
{
    public function allowsPlatform(User $user, string $permission): bool
    {
        if (! $user->isActive()
            || $user->role?->scope !== RoleScope::PLATFORM
            || ! $user->role->isActive()
        ) {
            return false;
        }

        // Keep platform direct-user grants working, but never consult the
        // union of network/POS memberships for a platform authorization.
        return $user->hasPermission($permission);
    }

    public function allowsNetwork(User $user, int $networkId, string $permission): bool
    {
        if ($networkId <= 0 || ! $user->isActive()) {
            return false;
        }

        // Explicit ownership is authoritative. A stale or inactive membership
        // row must never suppress the permissions of the actual network owner.
        $ownsNetwork = Network::query()
            ->where('network_id', $networkId)
            ->where('owner_id', $user->user_id)
            ->exists();

        if ($ownsNetwork && $this->roleAllows(RoleKey::NETWORK_OWNER, RoleScope::NETWORK, $permission)) {
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
        if ($posId <= 0 || ! $user->isActive()) {
            return false;
        }

        // POS ownership remains independent of any historical membership row.
        $ownsPos = Pos::query()
            ->where('pos_id', $posId)
            ->where('owner_id', $user->user_id)
            ->exists();

        if ($ownsPos && $this->roleAllows(RoleKey::POS_AGENT, RoleScope::POS, $permission)) {
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
                    ->where('permission_key', $permission)
                    ->where('status', true)))
            ->exists();
    }

    public function allowsNetworkOrPos(
        User $user,
        int $networkId,
        int $posId,
        string $permission
    ): bool {
        return $this->allowsNetwork($user, $networkId, $permission)
            || $this->allowsPos($user, $posId, $permission);
    }

    public function allowsAnyNetwork(User $user, string $permission): bool
    {
        return $this->authorizedNetworkIds($user, $permission) !== [];
    }

    public function allowsAnyPos(User $user, string $permission): bool
    {
        return $this->authorizedPosIds($user, $permission) !== [];
    }

    /**
     * @return array<int, int>
     */
    public function authorizedNetworkIds(User $user, string $permission): array
    {
        if (! $user->isActive()) {
            return [];
        }

        $memberNetworkIds = NetworkUser::query()
            ->where('user_id', $user->user_id)
            ->where('status', 'ACTIVE')
            ->whereHas('role', fn ($query) => $query
                ->where('scope', RoleScope::NETWORK->value)
                ->where('status', true)
                ->whereHas('permissions', fn ($permissions) => $permissions
                    ->where('permission_key', $permission)
                    ->where('status', true)))
            ->pluck('network_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $hasOwnerRolePermission = $this->roleAllows(
            RoleKey::NETWORK_OWNER,
            RoleScope::NETWORK,
            $permission
        );

        if (! $hasOwnerRolePermission) {
            return array_values(array_unique($memberNetworkIds));
        }

        $ownerNetworkIds = Network::query()
            ->where('owner_id', $user->user_id)
            ->pluck('network_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($memberNetworkIds, $ownerNetworkIds)));
    }

    /**
     * @return array<int, int>
     */
    public function authorizedPosIds(User $user, string $permission): array
    {
        if (! $user->isActive()) {
            return [];
        }

        $memberPosIds = PosUser::query()
            ->where('user_id', $user->user_id)
            ->where('status', 'ACTIVE')
            ->whereHas('role', fn ($query) => $query
                ->where('scope', RoleScope::POS->value)
                ->where('status', true)
                ->whereHas('permissions', fn ($permissions) => $permissions
                    ->where('permission_key', $permission)
                    ->where('status', true)))
            ->pluck('pos_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $hasOwnerRolePermission = $this->roleAllows(
            RoleKey::POS_AGENT,
            RoleScope::POS,
            $permission
        );

        if (! $hasOwnerRolePermission) {
            return array_values(array_unique($memberPosIds));
        }

        $ownerPosIds = Pos::query()
            ->where('owner_id', $user->user_id)
            ->pluck('pos_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($memberPosIds, $ownerPosIds)));
    }

    public function allowsAnyOperational(User $user, string $permission): bool
    {
        return $this->allowsAnyNetwork($user, $permission)
            || $this->allowsAnyPos($user, $permission);
    }

    private function roleAllows(RoleKey $roleKey, RoleScope $scope, string $permission): bool
    {
        return Role::query()
            ->where('role_key', $roleKey->value)
            ->where('scope', $scope->value)
            ->where('status', true)
            ->whereHas('permissions', fn ($query) => $query
                ->where('permission_key', $permission)
                ->where('status', true))
            ->exists();
    }
}
