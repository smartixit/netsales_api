<?php

namespace App\Services;

use App\Enums\RoleScope;
use App\Models\NetworkPos;
use App\Interfaces\SupportTicketRepositoryInterface;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class SupportTicketAuthorizationService
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissions,
        private readonly SupportTicketRepositoryInterface $ticketRepository,
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

        if ($actor->role_scope === RoleScope::PLATFORM->value) {
            $this->assertPlatformPermission($actor, $permission);

            return $filters;
        }

        $networkIds = $this->tenantPermissions->authorizedNetworkIds($actor, $permission);
        $posIds = $this->tenantPermissions->authorizedPosIds($actor, $permission);

        if (isset($filters['network_id'])) {
            $requestedNetworkId = (int) $filters['network_id'];

            $networkAllowed = in_array($requestedNetworkId, $networkIds, true);
            $linkedPosAllowed = $posIds !== []
                && NetworkPos::query()
                    ->where('network_id', $requestedNetworkId)
                    ->whereIn('pos_id', $posIds)
                    ->exists();

            if (! $networkAllowed && ! $linkedPosAllowed) {
                $networkIds = [];
                $posIds = [];
            } elseif ($networkAllowed) {
                $networkIds = [$requestedNetworkId];
            }
            // Retain network_id so the repository applies it as an additional
            // query constraint even when access is derived from a POS membership.
        }

        if (isset($filters['pos_id'])) {
            $requestedPosId = (int) $filters['pos_id'];

            $posAllowed = in_array($requestedPosId, $posIds, true);
            $linkedNetworkAllowed = $networkIds !== []
                && NetworkPos::query()
                    ->where('pos_id', $requestedPosId)
                    ->whereIn('network_id', $networkIds)
                    ->exists();

            if (! $posAllowed && ! $linkedNetworkAllowed) {
                $networkIds = [];
                $posIds = [];
            } elseif ($posAllowed) {
                $posIds = [$requestedPosId];
            }
            // Retain pos_id as a second server-side query constraint.
        }

        $filters['authorized_network_ids'] = array_values(array_unique($networkIds));
        $filters['authorized_pos_ids'] = array_values(array_unique($posIds));

        return $filters;
    }

    public function assertTicket(User $actor, SupportTicket $ticket, string $permission): void
    {
        $this->assertTarget($actor, (int) $ticket->network_id, (int) ($ticket->pos_id ?? 0), $permission);
    }

    /**
     * @param array<int, int> $ids
     */
    public function assertBatch(
        User $actor,
        array $ids,
        string $permission,
        bool $withTrashed = false
    ): void {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'ids' => ['يجب تحديد تذكرة واحدة على الأقل.'],
            ]);
        }

        $tickets = $this->ticketRepository->findManyByIds($ids, $withTrashed);

        if ($tickets->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['بعض التذاكر المحددة غير موجودة أو لا تطابق حالة الحذف المطلوبة.'],
            ]);
        }

        foreach ($tickets as $ticket) {
            $this->assertTicket($actor, $ticket, $permission);
        }
    }

    public function assertTarget(User $actor, int $networkId, ?int $posId, string $permission): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->role_scope === RoleScope::PLATFORM->value) {
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
        if (! $this->tenantPermissions->allowsPlatform($actor, $permission)) {
            throw new AuthorizationException('لا تملك صلاحية إدارة تذاكر الدعم على مستوى المنصة.');
        }
    }
}
