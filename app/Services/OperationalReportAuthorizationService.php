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
    public function __construct(private readonly OperationalReportProviderRegistry $providers) {}

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

        if (! $canUseNetworkScope && $posId === null) {
            throw new AuthorizationException('يجب تحديد نقطة البيع للوصول إلى تقارير نطاق نقطة البيع.');
        }

        if ($posId !== null) {
            $activeLink = NetworkPos::query()
                ->where('network_id', $networkId)
                ->where('pos_id', $posId)
                ->where('status', true)
                ->exists();
            if (! $activeLink) {
                throw new AuthorizationException('نقطة البيع غير مرتبطة بهذه الشبكة أو أن الارتباط غير نشط.');
            }
        }

        if (! $canUseNetworkScope) {
            if (! in_array('pos', $definition['supported_scopes'] ?? [], true)) {
                throw new AuthorizationException('هذا التقرير متاح ضمن نطاق الشبكة فقط.');
            }

            $isPosOwner = Pos::query()
                ->where('pos_id', $posId)
                ->where('owner_id', $user->user_id)
                ->exists();
            $posMembership = PosUser::query()
                ->where('pos_id', $posId)
                ->where('user_id', $user->user_id)
                ->where('status', 'ACTIVE')
                ->exists();

            if (! $isPosOwner && ! $posMembership) {
                throw new AuthorizationException('لا توجد عضوية نشطة تخولك الوصول إلى بيانات نقطة البيع المحددة.');
            }
        }

        $permission = $definition['permissions'][$forExport ? 'export' : 'view'] ?? null;
        if (! is_string($permission) || $permission === '') {
            throw new AuthorizationException('لم تُعرّف صلاحية هذا التقرير.');
        }

        $tenantId = $canUseNetworkScope ? $networkId : (int) $posId;
        if (! $user->hasPermission($permission, $tenantId)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا التقرير أو تصديره ضمن نطاق العمل المحدد.');
        }
    }
}
