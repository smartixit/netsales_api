<?php

namespace App\Policies;

use App\Models\Network;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkPolicy
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

    public function view(User $user, Network $network): bool
    {
        // 1. مالك الشبكة
        if ((int) $network->owner_id === (int) $user->user_id) {
            return true;
        }

        // 2. موظف أو عضو في الشبكة
        if ($user->networks()->where('networks.network_id', $network->network_id)->exists()) {
            return true;
        }

        if (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $network->network_id)->exists()) {
            return true;
        }

        // 3. مالك أو عضو في نقطة بيع مرتبطة بهذه الشبكة عبر رابط نشط
        if ($user->pos()->whereHas('networkPos', fn ($q) => $q->where('network_id', $network->network_id))->exists()) {
            return true;
        }

        if (method_exists($user, 'posUsers') && $user->posUsers()->whereHas('pos.networkPos', fn ($q) => $q->where('network_id', $network->network_id))->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole('SUPER_ADMIN');
    }

    public function update(User $user, Network $network): bool
    {
        // مالك الشبكة أو موظف يحمل صلاحية التعديل
        if ((int) $network->owner_id === (int) $user->user_id) {
            return true;
        }

        return $user->networkUsers()
            ->where('network_id', $network->network_id)
            ->whereHas('role.permissions', fn ($q) => $q->where('permission_key', 'networks.edit'))
            ->exists();
    }

    public function delete(User $user, Network $network): bool
    {
        return (int) $network->owner_id === (int) $user->user_id;
    }

    public function approve(User $user, Network $network): bool
    {
        return $user->isSuperAdmin();
    }

    public function reject(User $user, Network $network): bool
    {
        return $user->isSuperAdmin();
    }
}
