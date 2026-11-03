<?php

namespace App\Policies;

use App\Models\Pos;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Pos $pos): bool
    {
        // 1. مالك نقطة البيع
        if ((int) $pos->owner_id === (int) $user->user_id) {
            return true;
        }

        // 2. موظف أو عضو في نقطة البيع
        if ($user->pos()->where('pos.pos_id', $pos->pos_id)->exists()) {
            return true;
        }

        if (method_exists($user, 'posUsers') && $user->posUsers()->where('pos_id', $pos->pos_id)->exists()) {
            return true;
        }

        // 3. مالك أو عضو في شبكة مرتبطة بنقطة البيع هذه
        if ($user->networks()->whereHas('networkPos', fn ($q) => $q->where('pos_id', $pos->pos_id))->exists()) {
            return true;
        }

        if (method_exists($user, 'networkUsers') && $user->networkUsers()->whereHas('network.networkPos', fn ($q) => $q->where('pos_id', $pos->pos_id))->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('SUPER_ADMIN');
    }

    public function update(User $user, Pos $pos): bool
    {
        // مالك نقطة البيع أو موظف يحمل صلاحية التعديل
        if ((int) $pos->owner_id === (int) $user->user_id) {
            return true;
        }

        return $user->posUsers()
            ->where('pos_id', $pos->pos_id)
            ->whereHas('role.permissions', fn ($q) => $q->where('permission_key', 'pos.edit'))
            ->exists();
    }

    public function delete(User $user, Pos $pos): bool
    {
        return (int) $pos->owner_id === (int) $user->user_id;
    }

    public function approve(User $user, Pos $pos): bool
    {
        return $user->isSuperAdmin();
    }

    public function reject(User $user, Pos $pos): bool
    {
        return $user->isSuperAdmin();
    }
}

