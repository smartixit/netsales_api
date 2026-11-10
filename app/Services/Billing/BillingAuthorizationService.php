<?php

namespace App\Services\Billing;

use App\Enums\RoleScope;
use App\Models\{BillingAccount, Network, User};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BillingAuthorizationService
{
    public function assertPlatform(User $user): void
    {
        if ($user->role?->scope !== RoleScope::PLATFORM) {
            throw new AuthorizationException('هذا الإجراء متاح لمستخدمي نطاق المنصة فقط.');
        }
    }

    public function assertBillingAccountAccess(User $user, BillingAccount $account): void
    {
        if ($user->role?->scope === RoleScope::PLATFORM) {
            $this->assertPlatform($user);

            return;
        }

        $this->assertNetworkOwner($user, (int) $account->network_id);
    }

    public function assertNetworkPermission(User $user, int $networkId, string $permission): Network
    {
        $network = Network::query()->find($networkId);

        if (! $network) {
            throw (new ModelNotFoundException())->setModel(Network::class, [$networkId]);
        }

        if ((int) $network->owner_id === (int) $user->user_id) {
            return $network;
        }

        $allowed = $user->networkUsers()
            ->where('network_id', $networkId)
            ->where('status', 'ACTIVE')
            ->whereHas('role.permissions', fn ($query) => $query->where('permission_key', $permission))
            ->exists();

        if (! $allowed) {
            throw new AuthorizationException('لا تملك الصلاحية المطلوبة ضمن نطاق هذه الشبكة.');
        }

        return $network;
    }

    public function assertNetworkOwner(User $user, int $networkId): Network
    {
        $network = Network::query()->find($networkId);

        if (! $network) {
            throw (new ModelNotFoundException())->setModel(Network::class, [$networkId]);
        }

        if ((int) $network->owner_id !== (int) $user->user_id) {
            throw new AuthorizationException('إدارة حساب فوترة الشبكة متاحة لمالك الشبكة فقط.');
        }

        return $network;
    }
}
