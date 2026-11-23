<?php

namespace App\Services\Billing;

use App\Enums\RoleScope;
use App\Services\TenantPermissionService;
use App\Models\BillingAccount;
use App\Models\Network;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BillingAuthorizationService
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissions
    ) {}

    public function assertPlatform(User $user): void
    {
        if (! $user->isActive()
            || $user->role_scope !== RoleScope::PLATFORM->value
            || ! $user->role?->isActive()
        ) {
            throw new AuthorizationException('هذا الإجراء متاح لمستخدمي نطاق المنصة فقط.');
        }
    }

    public function assertBillingAccountAccess(User $user, BillingAccount $account): void
    {
        if ($user->role_scope === RoleScope::PLATFORM->value) {
            $this->assertPlatform($user);

            return;
        }

        $this->assertNetworkOwner($user, (int) $account->network_id);
    }

    public function assertNetworkPermission(User $user, int $networkId, string $permission): Network
    {
        $network = Network::query()->find($networkId);

        if (! $network) {
            throw (new ModelNotFoundException)->setModel(Network::class, [$networkId]);
        }

        if (! $this->tenantPermissions->allowsNetwork($user, $networkId, $permission)) {
            throw new AuthorizationException('لا تملك الصلاحية المطلوبة ضمن نطاق هذه الشبكة.');
        }

        return $network;
    }

    public function assertNetworkOwner(User $user, int $networkId): Network
    {
        $network = Network::query()->find($networkId);

        if (! $network) {
            throw (new ModelNotFoundException)->setModel(Network::class, [$networkId]);
        }

        if (! $user->isActive() || (int) $network->owner_id !== (int) $user->user_id) {
            throw new AuthorizationException('إدارة حساب فوترة الشبكة متاحة لمالك الشبكة النشط فقط.');
        }

        return $network;
    }
}
