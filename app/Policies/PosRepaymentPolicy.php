<?php

namespace App\Policies;

use App\Models\PosRepayment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosRepaymentPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user, int $networkId, ?int $posId = null): bool
    {
        // 1. Network owner or authorized network member
        if ($user->networks()->where('networks.network_id', $networkId)->exists()) {
            return true;
        }
        if (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $networkId)->exists()) {
            return true;
        }

        // 2. POS user viewing only their own network debt
        if ($posId !== null && $user->pos && (int) $user->pos->pos_id === $posId) {
            return true;
        }
        if ($posId !== null && method_exists($user, 'posUsers') && $user->posUsers()->where('pos_id', $posId)->exists()) {
            return true;
        }

        return false;
    }

    public function view(User $user, PosRepayment $repayment): bool
    {
        // Network owner / member
        if ($user->networks()->where('networks.network_id', $repayment->network_id)->exists()) {
            return true;
        }
        if (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $repayment->network_id)->exists()) {
            return true;
        }

        // POS owner / member
        if ($user->pos && (int) $user->pos->pos_id === (int) $repayment->pos_id) {
            return true;
        }
        if (method_exists($user, 'posUsers') && $user->posUsers()->where('pos_id', $repayment->pos_id)->exists()) {
            return true;
        }

        return false;
    }

    public function collect(User $user, int $networkId): bool
    {
        // POS users CANNOT collect debt or authorize repayments
        if ($user->pos) {
            return false;
        }

        // Network owner
        if ($user->networks()->where('networks.network_id', $networkId)->exists()) {
            return true;
        }

        // Network user with pos_wallets.manage or pos_wallets.create permission
        if (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $networkId)->exists()) {
            return $user->hasPermission('pos_wallets.create') || $user->hasPermission('pos_wallets.manage');
        }

        return false;
    }

    public function reverse(User $user, PosRepayment $repayment): bool
    {
        // POS users CANNOT reverse repayments
        if ($user->pos) {
            return false;
        }

        // Network owner of the associated network
        if ($user->networks()->where('networks.network_id', $repayment->network_id)->exists()) {
            return true;
        }

        // Network user with manage permission
        if (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $repayment->network_id)->exists()) {
            return $user->hasPermission('pos_wallets.manage');
        }

        return false;
    }
}

