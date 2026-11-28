<?php

namespace App\Services\Billing;

use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\VoucherPackage;

class BillingResourceUsageService
{
    /**
     * Return the live usage snapshot for a supported resource entitlement.
     *
     * The count queries deliberately mirror the corresponding write guards:
     * inactive POS links still occupy a seat, soft-deleted POS links do not;
     * soft-deleted voucher packages keep their reservation; invited members
     * occupy a member seat while the network owner is excluded.
     *
     * @return array{used:int,limit:int|null,remaining:int|null,utilization_percent:float|null,unlimited:bool,exhausted:bool}|null
     */
    public function snapshot(int $networkId, string $featureKey, string $configuredLimit): ?array
    {
        $limit = filter_var(trim($configuredLimit), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < -1) {
            return null;
        }

        $used = match ($featureKey) {
            'max_pos_count' => NetworkPos::query()
                ->where('network_id', $networkId)
                ->count(),
            'max_voucher_packages' => VoucherPackage::withTrashed()
                ->where('network_id', $networkId)
                ->count(),
            'max_network_members' => $this->networkMemberCount($networkId),
            default => null,
        };

        if ($used === null) {
            return null;
        }

        $unlimited = $limit === -1;
        $remaining = $unlimited ? null : max(0, $limit - $used);
        $percentage = $unlimited
            ? null
            : ($limit === 0
                ? ($used > 0 ? 100.0 : 0.0)
                : round(min(100, ($used / $limit) * 100), 1));

        return [
            'used' => $used,
            'limit' => $unlimited ? null : $limit,
            'remaining' => $remaining,
            'utilization_percent' => $percentage,
            'unlimited' => $unlimited,
            'exhausted' => ! $unlimited && $used >= $limit,
        ];
    }

    private function networkMemberCount(int $networkId): ?int
    {
        $network = Network::query()->find($networkId);
        if (! $network) {
            return null;
        }

        return NetworkUser::query()
            ->where('network_id', $networkId)
            ->where('user_id', '!=', (int) $network->owner_id)
            ->whereIn('status', ['ACTIVE', 'INVITED'])
            ->count();
    }
}
