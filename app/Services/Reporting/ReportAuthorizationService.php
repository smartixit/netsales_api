<?php

namespace App\Services\Reporting;

use App\Enums\RoleScope;
use App\Models\ReportRun;
use App\Models\User;
use App\Services\Billing\BillingAuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;

class ReportAuthorizationService
{
    public function __construct(
        private BillingAuthorizationService $billingAuthorization
    ) {}

    /**
     * @param array<string, mixed> $definition
     */
    public function authorizeNetworkDefinition(
        User $actor,
        int $networkId,
        array $definition
    ): void {
        if (($definition['permission'] ?? null) === 'NETWORK_OWNER_ONLY') {
            $this->billingAuthorization->assertNetworkOwner($actor, $networkId);

            return;
        }

        $this->billingAuthorization->assertNetworkPermission(
            $actor,
            $networkId,
            (string) $definition['permission']
        );
    }

    /**
     * @param array<string, mixed> $definition
     */
    public function authorizePlatformDefinition(User $actor, array $definition): void
    {
        $this->billingAuthorization->assertPlatform($actor);

        $permission = (string) ($definition['permission'] ?? '');
        if ($permission !== '' && ! $actor->hasPermission($permission)) {
            throw new AuthorizationException('ليس لديك الصلاحية المطلوبة لتشغيل هذا التقرير.');
        }
    }

    public function authorizeRun(User $actor, ReportRun $run): void
    {
        if ($run->scope_type === 'NETWORK') {
            if ($run->network_id === null) {
                throw new AuthorizationException('نطاق تقرير الشبكة غير مكتمل.');
            }

            if ($run->permission_key === 'NETWORK_OWNER_ONLY') {
                $this->billingAuthorization->assertNetworkOwner($actor, (int) $run->network_id);

                return;
            }

            $this->billingAuthorization->assertNetworkPermission(
                $actor,
                (int) $run->network_id,
                $run->permission_key
            );

            return;
        }

        if ($run->scope_type === 'PLATFORM') {
            $this->billingAuthorization->assertPlatform($actor);
            if (! $actor->hasPermission($run->permission_key)) {
                throw new AuthorizationException('ليس لديك الصلاحية المطلوبة للوصول إلى ملف التقرير.');
            }

            return;
        }

        throw new AuthorizationException('نطاق التقرير غير معروف.');
    }
}
