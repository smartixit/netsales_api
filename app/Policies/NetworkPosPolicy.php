<?php

namespace App\Policies;

use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkPosPolicy
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

    public function view(User $user, NetworkPos $networkPos): bool
    {
        // 1. ينتمي للشبكة (مالك أو مستخدم شبكة)
        if ((int) $networkPos->network?->owner_id === (int) $user->user_id
            || $user->networkUsers()->where('network_id', $networkPos->network_id)->exists()
        ) {
            return true;
        }

        // 2. ينتمي لنقطة البيع (مالك أو مستخدم نقطة بيع)
        if ((int) $networkPos->pos?->owner_id === (int) $user->user_id
            || $user->posUsers()->where('pos_id', $networkPos->pos_id)->exists()
        ) {
            return true;
        }

        return false;
    }

    public function create(User $user, int $networkId): bool
    {
        // يجب أن يكون مالكاً للشبكة أو مستخدماً فيها
        return Network::where('network_id', $networkId)
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->user_id)
                    ->orWhereHas('memberships', fn ($m) => $m->where('user_id', $user->user_id));
            })->exists();
    }

    public function update(User $user, NetworkPos $networkPos): bool
    {
        // إدارة وتعديل الارتباط من صلاحية الشبكة المعنية
        $networkId = $networkPos->network_id;

        return Network::where('network_id', $networkId)
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->user_id)
                    ->orWhereHas('memberships', fn ($m) => $m->where('user_id', $user->user_id));
            })->exists();
    }

    public function delete(User $user, NetworkPos $networkPos): bool
    {
        return $this->update($user, $networkPos);
    }
}
