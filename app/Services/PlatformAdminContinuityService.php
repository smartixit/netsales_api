<?php

namespace App\\Services;

use App\\Enums\\RoleKey;
use App\\Enums\\UserStatus;
use App\\Models\\Role;
use App\\Models\\User;
use Illuminate\\Validation\\ValidationException;

/**
 * Serializes administrative account mutations and preserves one active
 * platform SUPER_ADMIN at all times.
 *
 * Callers must run these methods inside the same database transaction that
 * performs the mutation. Locking the singleton SUPER_ADMIN role row provides
 * a common mutex for delete, disable, demote, ban and create/promote flows.
 */
class PlatformAdminContinuityService
{
    public function lockSuperAdminRole(): Role
    {
        return Role::query()
            ->where('role_key', RoleKey::SUPER_ADMIN->value)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function assertMutationIsSafe(
        Role $superAdminRole,
        User $target,
        ?int $nextRoleId = null,
        ?UserStatus $nextStatus = null,
        bool $willBeDeleted = false,
    ): void {
        $isCurrentActiveAdmin = (int) $target->role_id === (int) $superAdminRole->role_id
            && $this->statusValue($target->status) === UserStatus::ACTIVE->value
            && $target->deleted_at === null;

        if (! $isCurrentActiveAdmin) {
            return;
        }

        $effectiveRoleId = $nextRoleId ?? (int) $target->role_id;
        $effectiveStatus = $nextStatus?->value ?? $this->statusValue($target->status);
        $willRemainActiveAdmin = ! $willBeDeleted
            && $effectiveRoleId === (int) $superAdminRole->role_id
            && $effectiveStatus === UserStatus::ACTIVE->value;

        if ($willRemainActiveAdmin) {
            return;
        }

        $activeAdmins = User::query()
            ->where('role_id', $superAdminRole->role_id)
            ->where('status', UserStatus::ACTIVE->value)
            ->count();

        if ($activeAdmins <= 1) {
            throw ValidationException::withMessages([
                'user_id' => ['لا يمكن تنفيذ العملية لأنها ستؤدي إلى فقدان آخر حساب SUPER_ADMIN نشط. أنشئ أو فعّل حساباً إدارياً بديلاً أولاً.'],
            ]);
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function assertBulkMutationIsSafe(Role $superAdminRole, array $userIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        if ($ids === []) {
            return;
        }

        $activeAdmins = User::query()
            ->where('role_id', $superAdminRole->role_id)
            ->where('status', UserStatus::ACTIVE->value)
            ->count();

        $targetedActiveAdmins = User::query()
            ->where('role_id', $superAdminRole->role_id)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereIn('user_id', $ids)
            ->count();

        if ($targetedActiveAdmins > 0 && $activeAdmins - $targetedActiveAdmins < 1) {
            throw ValidationException::withMessages([
                'ids' => ['لا يمكن تنفيذ العملية المجمعة لأنها ستؤدي إلى فقدان آخر حساب SUPER_ADMIN نشط.'],
            ]);
        }
    }

    public function isSuperAdminRoleId(int $roleId): bool
    {
        return Role::query()
            ->where('role_key', RoleKey::SUPER_ADMIN->value)
            ->whereKey($roleId)
            ->exists();
    }

    private function statusValue(UserStatus|string|null $status): ?string
    {
        return $status instanceof UserStatus ? $status->value : $status;
    }
}
