<?php

namespace App\Services;

use App\Enums\RoleScope;
use App\Models\NetworkPos;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class SupportTicketAuthorizationService
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissions,
    ) {}

    /**
     * Restrict operational ticket lists to the actor's active memberships.
     *
     * Platform support actors may see the platform-wide queue. Operational
     * users receive server-applied tenant ID filters; client-supplied filters
     * can narrow that scope but can never widen it.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function scopeListFilters(User $actor, array $filters, string $permission = 'support.view'): array
    {
        if ($actor->isSuperAdmin()) {
            return $filters;
        }

        if ($actor->role?->scope === RoleScope::PLATFORM) {
            $this->assertPlatformPermission($actor, $permission);

            return $filters;
        }

        $networkIds = $this->tenantPermissions->authorizedNetworkIds($actor, $permission);
        $posIds = $this->tenantPermissions->authorizedPosIds($actor, $permission);

        if (isset($filters['network_id'])) {
            $requestedNetworkId = (int) $filters['network_id'];
            $networkIds = in_array($requestedNetworkId, $networkIds, true)
                ? [$requestedNetworkId]
                : [];
        }

        if (isset($filters['pos_id'])) {
            $requestedPosId = (int) $filters['pos_id'];
            $posIds = in_array($requestedPosId, $posIds, true)
                ? [$requestedPosId]
                : [];
        }

        unset($filters['network_id'], $filters['pos_id']);

        $filters['authorized_network_ids'] = array_values(array_unique($networkIds));
        $filters['authorized_pos_ids'] = array_values(array_unique($posIds));

        return $filters;
    }

    public function assertTicket(User $actor, SupportTicket $ticket, string $permission): void
    {
        $this->assertTarget($actor, (int) $ticket->network_id, (int) ($ticket->pos_id ?? 0), $permission);
    }

    public function assertTarget(User $actor, int $networkId, ?int $posId, string $permission): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->role?->scope === RoleScope::PLATFORM) {
            $this->assertPlatformPermission($actor, $permission);

            return;
        }

        if ($networkId <= 0) {
            throw new AuthorizationException('يجب تحديد شبكة صالحة ضمن مساحة العمل المصرح بها.');
        }

        $networkAllowed = $this->tenantPermissions->allowsNetwork($actor, $networkId, $permission);
        $posAllowed = $posId !== null
            && $posId > 0
            && $this->tenantPermissions->allowsPos($actor, $posId, $permission);

        if (! $networkAllowed && ! $posAllowed) {
            throw new AuthorizationException('لا تملك الصلاحية المطلوبة على الشبكة أو نقطة البيع المرتبطة بالتذكرة.');
        }

        if ($posId !== null && $posId > 0 && ! NetworkPos::query()
            ->where('network_id', $networkId)
            ->where('pos_id', $posId)
            ->exists()) {
            throw new AuthorizationException('نقطة البيع المحددة ليست مرتبطة بالشبكة المسجلة في التذكرة.');
        }
    }

    private function assertPlatformPermission(User $actor, string $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            throw new AuthorizationException('لا تملك صلاحية إدارة تذاكر الدعم على مستوى المنصة.');
        }
    }
}
