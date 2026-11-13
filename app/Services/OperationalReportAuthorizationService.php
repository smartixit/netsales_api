<?php

namespace App\Services;

use App\Enums\OperationalReportType;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\User;
use App\Services\Reports\OperationalReportProviderRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class OperationalReportAuthorizationService
{
    public function __construct(
        private readonly OperationalReportProviderRegistry $providers,
        private readonly TenantPermissionService $tenantPermissions
    ) {}

    public function authorize(
        User $user,
        OperationalReportType $reportType,
        int $networkId,
        ?int $posId,
        bool $forExport = false,
        bool $validateReportScope = true
    ): void {
        $definition = $this->providers->get($reportType)->definition();
        $posSelection = $definition['pos_selection'] ?? 'optional';

        if ($validateReportScope && $posSelection === 'required' && $posId === null) {
            throw new AuthorizationException('يتطلب هذا التقرير تحديد نقطة البيع.');
        }
        if ($validateReportScope && $posSelection === 'forbidden' && $posId !== null) {
            throw new AuthorizationException('هذا التقرير لا يقبل تحديد نقطة بيع.');
        }

        $network = Network::query()->find($networkId);
        if (! $network) {
            throw (new ModelNotFoundException())->setModel(Network::class, [$networkId]);
        }

        $networkMembership = NetworkUser::query()
            ->where('network_id', $networkId)
            ->where('user_id', $user->user_id)
            ->where('status', 'ACTIVE')
            ->exists();
        $canUseNetworkScope = (int) $network->owner_id === (int) $user->user_id || $networkMembership;

        $canUsePosScope = false;
        if ($posId !== null) {
            $activeLink = NetworkPos::query()
                ->where('network_id', $networkId)
                ->where('pos_id', $posId)
                ->where('status', true)
                ->exists();
            if (! $activeLink) {
                throw new AuthorizationException('نقطة البيع غير مرتبطة بهذه الشبكة أو أن الارتباط غير نشط.');
            }

            if (in_array('pos', $definition['supported_scopes'] ?? [], true)) {
                $isPosOwner = Pos::query()
                    ->where('pos_id', $posId)
                    ->where('owner_id', $user->user_id)
                    ->exists();
                $posMembership = PosUser::query()
                    ->where('pos_id', $posId)
                    ->where('user_id', $user->user_id)
                    ->where('status', 'ACTIVE')
                    ->exists();
                $canUsePosScope = $isPosOwner || $posMembership;
            }
        }

        $permission = $definition['permissions'][$forExport ? 'export' : 'view'] ?? null;
        if (! is_string($permission) || $permission === '') {
            throw new AuthorizationException('لم تُعرّف صلاحية هذا التقرير.');
        }

        $supportedScopes = $definition['supported_scopes'] ?? [];
        $hasNetworkPermission = in_array('network', $supportedScopes, true)
            && $canUseNetworkScope
            && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
        $hasPosPermission = in_array('pos', $supportedScopes, true)
            && $canUsePosScope
            && $this->tenantPermissions->allowsPos($user, (int) $posId, $permission);

        if (! $hasNetworkPermission && ! $hasPosPermission) {
            throw new AuthorizationException('لا تملك عضوية وصلاحية التقرير ضمن نطاق الشبكة أو نقطة البيع المحدد.');
        }
    }
}
