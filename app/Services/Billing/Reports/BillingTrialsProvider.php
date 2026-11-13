<?php

namespace App\Services\Billing\Reports;

use Illuminate\Support\Facades\DB;

final class BillingTrialsProvider extends ConfiguredBillingReportProvider
{
    private function lifecycleEventCount(
        ?int $networkId,
        array $filters,
        string $eventType
    ): int {
        $query = DB::table('billing_subscription_events as e')
            ->join(
                'billing_trial_redemptions as r',
                'r.billing_subscription_id',
                '=',
                'e.billing_subscription_id'
            )
            ->join(
                'billing_subscriptions as s',
                's.billing_subscription_id',
                '=',
                'e.billing_subscription_id'
            )
            ->join(
                'billing_accounts as ba',
                'ba.billing_account_id',
                '=',
                'r.billing_account_id'
            )
            ->whereNull('s.deleted_at')
            ->where('e.event_type', $eventType);

        if ($networkId !== null) {
            $query->where('ba.network_id', $networkId);
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('r.reserved_at', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('r.reserved_at', '<=', $filters['to_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('r.status', strtoupper((string) $filters['status']));
        }

        $this->applySearchColumns(
            $query,
            $filters,
            ['r.status', 'CAST(r.trial_redemption_id AS CHAR)']
        );

        return (int) $query->distinct()->count('r.trial_redemption_id');
    }

    public function summary(?int $networkId, array $filters): array
    {
        $query = DB::table('billing_trial_redemptions as r')
            ->join(
                'billing_accounts as ba',
                'ba.billing_account_id',
                '=',
                'r.billing_account_id'
            )
            ->join(
                'billing_subscriptions as s',
                's.billing_subscription_id',
                '=',
                'r.billing_subscription_id'
            )
            ->whereNull('s.deleted_at');

        if ($networkId !== null) {
            $query->where('ba.network_id', $networkId);
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('r.reserved_at', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('r.reserved_at', '<=', $filters['to_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('r.status', strtoupper((string) $filters['status']));
        }
        $this->applySearchColumns(
            $query,
            $filters,
            ['r.status', 'CAST(r.trial_redemption_id AS CHAR)']
        );

        $statuses = (clone $query)
            ->select('r.status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('r.status')
            ->get();

        $policies = (clone $query)
            ->select('r.trial_policy_version_id', 'r.redemption_scope')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('r.trial_policy_version_id', 'r.redemption_scope')
            ->get();

        $countStatus = static fn (string $status): int =>
            (int) ($statuses->firstWhere('status', $status)?->total ?? 0);

        $revokedQuery = (clone $query)->where('r.status', 'REVOKED');
        $revokedQuery->whereNotExists(function ($events): void {
            $events->selectRaw('1')
                ->from('billing_subscription_events as rejection_events')
                ->whereColumn(
                    'rejection_events.billing_subscription_id',
                    'r.billing_subscription_id'
                )
                ->where(
                    'rejection_events.event_type',
                    'TRIAL_REDEMPTION_REJECTED'
                );
        });

        return [
            'record_count' => (int) $statuses->sum('total'),
            'reserved' => (int) $statuses->sum('total'),
            'pending_approval' => $countStatus('PENDING_APPROVAL'),
            'started' => (int) (clone $query)
                ->whereNotNull('r.started_at')
                ->distinct()
                ->count('r.trial_redemption_id'),
            'completed' => $countStatus('COMPLETED'),
            'expired' => $this->lifecycleEventCount(
                $networkId,
                $filters,
                'TRIAL_EXPIRED_WITHOUT_CONVERSION'
            ),
            'rejected' => $this->lifecycleEventCount(
                $networkId,
                $filters,
                'TRIAL_REDEMPTION_REJECTED'
            ),
            'revoked' => (int) $revokedQuery
                ->distinct()
                ->count('r.trial_redemption_id'),
            'status_distribution' => $statuses
                ->map(static fn ($item): array => [
                    'status' => $item->status,
                    'count' => (int) $item->total,
                ])
                ->all(),
            'by_policy_version_and_scope' => $policies
                ->map(static fn ($item): array => [
                    'trial_policy_version_id' =>
                        (int) $item->trial_policy_version_id,
                    'redemption_scope' => $item->redemption_scope,
                    'count' => (int) $item->total,
                ])
                ->all(),
        ];
    }
}
