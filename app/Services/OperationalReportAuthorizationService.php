<?php

namespace App\Services;

use App\Enums\OperationalReportType;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OperationalReportAuthorizationService
{
    public function authorize(
        User $user,
        OperationalReportType $reportType,
        int $networkId,
        ?int $posId,
        bool $forExport = false
    ): void {
        $network = Network::query()->find($networkId);

        if (! $network) {
            throw (new ModelNotFoundException())->setModel(Network::class, [$networkId]);
        }

        $networkMembership = NetworkUser::query()
            ->where('network_id', $networkId)
            ->where('user_id', $user->user_id)
            ->where('status', 'ACTIVE')
            ->exists();

        $isNetworkOwner = (int) $network->owner_id === (int) $user->user_id;
        $canUseNetworkScope = $isNetworkOwner || $networkMembership;

        // These datasets are network-owned and are not filtered by POS. A
        // POS-only membership must never receive the full network dataset.
        if (! $canUseNetworkScope && in_array($reportType, [
            OperationalReportType::EXPENSES,
            OperationalReportType::NETWORK_WALLET,
        ], true)) {
            throw new AuthorizationException('يتطلب هذا التقرير عضوية نشطة في الشبكة المالكة للبيانات.');
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
            if ($posId === null) {
                throw new AuthorizationException('يجب تحديد نقطة البيع للوصول إلى تقارير نطاق نقطة البيع.');
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

            $tenantId = $posId;
        } else {
            $tenantId = $networkId;
        }

        $requiredPermission = $forExport
            ? $reportType->exportPermission()
            : $reportType->reportPermission();

        if (! $user->hasPermission($requiredPermission, $tenantId)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا التقرير أو تصديره ضمن نطاق العمل المحدد.');
        }
    }
}
