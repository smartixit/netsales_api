<?php

namespace App\Services;

use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Models\NetworkPos;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\User;

final class TenantPermissionService
{
    public function allowsPlatform(User $user, string $permission): bool
    {
        if (! app(PlatformPermissionRegistry::class)->allows($permission)) {
            return false;
        }

        if (! $user->isActive()
            || $user->role?->scope !== RoleScope::PLATFORM
            || ! $user->role->isActive()
        ) {
            return false;
        }

        // Resolve only direct-user and top-level platform-role grants.
        // User::hasPermission() also unions network/POS memberships and is
        // therefore not safe for a platform-scope authorization decision.
        $directGrant = $user->permissions()
            ->where('permission_key', $permission)
            ->where('status', true)
            ->exists();

        $roleGrant = $user->role->permissions()
            ->where('permission_key', $permission)
            ->where('status', true)
            ->exists();

        return $directGrant || $roleGrant;
    }

    public function allowsNetwork(User $user, int $networkId, string $permission): bool
    {
        if ($networkId <= 0
            || ! $user->isActive()
            || $user->role_scope === RoleScope::PLATFORM->value
        ) {
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
        if ($posId <= 0
            || ! $user->isActive()
            || $user->role_scope === RoleScope::PLATFORM->value
        ) {
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

    public function allowsNetworkThroughNetworkOrLinkedPos(
        User $user,
        int $networkId,
        string $permission
    ): bool {
        if ($this->allowsNetwork($user, $networkId, $permission)) {
            return true;
        }

        $posIds = $this->authorizedPosIds($user, $permission);

        return $posIds !== []
            && NetworkPos::query()
                ->where('network_id', $networkId)
                ->where('status', true)
                ->whereIn('pos_id', $posIds)
                ->exists();
    }

    public function allowsPosThroughPosOrLinkedNetwork(
        User $user,
        int $posId,
        string $permission
    ): bool {
        if ($this->allowsPos($user, $posId, $permission)) {
            return true;
        }

        $networkIds = $this->authorizedNetworkIds($user, $permission);

        return $networkIds !== []
            && NetworkPos::query()
                ->where('pos_id', $posId)
                ->where('status', true)
                ->whereIn('network_id', $networkIds)
                ->exists();
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
        if (! $user->isActive() || $user->role_scope === RoleScope::PLATFORM->value) {
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
        if (! $user->isActive() || $user->role_scope === RoleScope::PLATFORM->value) {
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
