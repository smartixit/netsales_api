<?php

namespace App\Services;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\ProviderSubscriptionStatus;
use App\Enums\RequestStatus;
use App\Models\Expense;
use App\Models\Network;
use App\Models\Pos;
use App\Models\ProviderSubscription;
use App\Models\SaleInvoice;
use App\Models\SubscriptionPlanRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SystemLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardOverviewService
{
    private const TIMEZONE = 'Asia/Aden';

    private const EXPIRING_WITHIN_DAYS = 7;

    private const RECENT_ACTIVITY_LIMIT = 8;

    private const ATTENTION_ITEMS_LIMIT = 5;

    /**
     * Build permission-aware metrics from persisted records.
     *
     * @return array<string, mixed>
     */
    public function getOverview(User $user, string $period = '30d'): array
    {
        $now = Carbon::now(self::TIMEZONE);
        $periodDays = $this->periodDays($period);
        $periodStart = $now->copy()->subDays($periodDays - 1)->startOfDay();
        $bucket = $this->periodBucket($period);
        $permissions = array_fill_keys($user->getAllPermissions(), true);
        $can = static fn (string $permission): bool => $user->isSuperAdmin()
            || isset($permissions[$permission]);

        $metrics = [];
        $charts = [
            'subscription_trend' => [],
            'provider_distribution' => [],
            'operational_activity' => [],
        ];
        $attention = [];
        $recentActivity = [];

        if ($can('networks.view')) {
            $networkCounts = $this->statusCounts('networks');
            $metrics['networks'] = $this->entityStatusMetric($networkCounts);

            $this->appendStatusDistribution(
                $charts['provider_distribution'],
                'network',
                $networkCounts,
                NetworkStatus::labels()
            );

            $pendingNetworks = Network::query()
                ->where('status', NetworkStatus::PENDING->value)
                ->orderBy('created_at')
                ->limit(self::ATTENTION_ITEMS_LIMIT)
                ->get(['network_id', 'network_name', 'created_at']);

            $attention['pending_networks'] = [
                'count' => (int) ($networkCounts[NetworkStatus::PENDING->value] ?? 0),
                'items' => $pendingNetworks->map(fn (Network $network): array => [
                    'id' => (int) $network->network_id,
                    'title' => $network->network_name,
                    'subtitle' => 'طلب اعتماد مزود شبكة',
                    'route_key' => 'networks',
                    'occurred_at' => $network->created_at?->toIso8601String(),
                    'due_at' => null,
                ])->values()->all(),
            ];
        }

        if ($can('pos.view')) {
            $posCounts = $this->statusCounts('pos');
            $metrics['pos'] = $this->entityStatusMetric($posCounts);

            $this->appendStatusDistribution(
                $charts['provider_distribution'],
                'pos',
                $posCounts,
                PosStatus::labels()
            );

            $pendingPos = Pos::query()
                ->where('status', PosStatus::PENDING->value)
                ->orderBy('created_at')
                ->limit(self::ATTENTION_ITEMS_LIMIT)
                ->get(['pos_id', 'pos_name', 'created_at']);

            $attention['pending_pos'] = [
                'count' => (int) ($posCounts[PosStatus::PENDING->value] ?? 0),
                'items' => $pendingPos->map(fn (Pos $pos): array => [
                    'id' => (int) $pos->pos_id,
                    'title' => $pos->pos_name,
                    'subtitle' => 'طلب اعتماد نقطة بيع',
                    'route_key' => 'pos',
                    'occurred_at' => $pos->created_at?->toIso8601String(),
                    'due_at' => null,
                ])->values()->all(),
            ];
        }

        if ($can('subscriptions.view')) {
            $subscriptionCounts = $this->subscriptionStatusCounts();
            $activeSubscriptions = $this->activeSubscriptionQuery($now)->count();
            $expiringSubscriptions = $this->expiringSubscriptionQuery($now)->count();

            $metrics['subscriptions'] = [
                'total' => array_sum($subscriptionCounts),
                'active' => $activeSubscriptions,
                'queued' => (int) ($subscriptionCounts[ProviderSubscriptionStatus::QUEUED->value] ?? 0),
                'expired' => $this->expiredSubscriptionCount($now),
                'expiring_within_7_days' => $expiringSubscriptions,
            ];

            $charts['subscription_trend'] = $this->subscriptionTrend($now, $period, $periodStart, $bucket);

            $expiringItems = $this->expiringSubscriptionQuery($now)
                ->with(['network:network_id,network_name', 'plan:plan_id,plan_name'])
                ->orderBy('subscription_end_date')
                ->limit(self::ATTENTION_ITEMS_LIMIT)
                ->get([
                    'subscription_id',
                    'network_id',
                    'plan_id',
                    'subscription_start_date',
                    'subscription_end_date',
                ]);

            $attention['expiring_subscriptions'] = [
                'count' => $expiringSubscriptions,
                'items' => $expiringItems->map(fn (ProviderSubscription $subscription): array => [
                    'id' => (int) $subscription->subscription_id,
                    'title' => $subscription->network?->network_name ?? 'شبكة غير متاحة',
                    'subtitle' => $subscription->plan?->plan_name ?? 'اشتراك مزود',
                    'route_key' => 'provider_subscriptions',
                    'occurred_at' => $subscription->subscription_start_date?->toIso8601String(),
                    'due_at' => $subscription->subscription_end_date?->toIso8601String(),
                ])->values()->all(),
            ];

            $pendingPlanRequests = SubscriptionPlanRequest::query()
                ->where('status', RequestStatus::PENDING->value)
                ->with([
                    'network:network_id,network_name',
                    'plan:plan_id,plan_name',
                ])
                ->orderBy('created_at')
                ->limit(self::ATTENTION_ITEMS_LIMIT)
                ->get(['request_id', 'network_id', 'plan_id', 'request_uuid', 'created_at']);

            $attention['pending_subscription_requests'] = [
                'count' => SubscriptionPlanRequest::query()
                    ->where('status', RequestStatus::PENDING->value)
                    ->count(),
                'items' => $pendingPlanRequests->map(fn (SubscriptionPlanRequest $request): array => [
                    'id' => (int) $request->request_id,
                    'title' => $request->network?->network_name ?? $request->request_uuid,
                    'subtitle' => $request->plan?->plan_name ?? 'طلب اشتراك أو ترقية',
                    'route_key' => 'subscription_requests',
                    'occurred_at' => $request->created_at?->toIso8601String(),
                    'due_at' => null,
                ])->values()->all(),
            ];
        }

        if ($can('support.view')) {
            $metrics['support'] = [
                // The current support_tickets schema has no open/closed state.
                // Report the actual non-deleted ticket count, not a guessed "open" count.
                'total_tickets' => SupportTicket::query()->count(),
                'unread_messages' => SupportTicketMessage::query()
                    ->where('is_read', false)
                    ->whereHas('ticket')
                    ->count(),
            ];
        }

        if ($can('sales.view')) {
            $sales = SaleInvoice::query()
                ->whereBetween('invoice_date', [$periodStart, $now])
                ->selectRaw(
                    'COUNT(*) as invoice_count,
                    COALESCE(SUM(invoice_total_amount), 0) as total_amount,
                    COALESCE(SUM(paid_amount), 0) as paid_amount,
                    COALESCE(SUM(remaining_amount), 0) as outstanding_amount'
                )
                ->first();

            $metrics['sales'] = [
                'invoice_count' => (int) ($sales?->invoice_count ?? 0),
                'total_amount' => (string) ($sales?->total_amount ?? '0.00'),
                'paid_amount' => (string) ($sales?->paid_amount ?? '0.00'),
                'outstanding_amount' => (string) ($sales?->outstanding_amount ?? '0.00'),
            ];
        }

        if ($can('expenses.view')) {
            $expenses = Expense::query()
                ->whereBetween('expense_date', [$periodStart, $now])
                ->selectRaw(
                    'COUNT(*) as expense_count,
                    COALESCE(SUM(expense_total_amount), 0) as total_amount'
                )
                ->first();

            $metrics['expenses'] = [
                'count' => (int) ($expenses?->expense_count ?? 0),
                'total_amount' => (string) ($expenses?->total_amount ?? '0.00'),
            ];
        }

        if ($can('system_logs.view')) {
            $charts['operational_activity'] = $this->systemActivityTrend($now, $period, $periodStart, $bucket);
            $recentActivity = $this->recentSystemActivity();
        } elseif ($can('support.view')) {
            $charts['operational_activity'] = $this->supportActivityTrend($now, $period, $periodStart, $bucket);
            $recentActivity = $this->recentSupportActivity();
        }

        return [
            'generated_at' => $now->toIso8601String(),
            'timezone' => self::TIMEZONE,
            'period' => [
                'key' => $period,
                'days' => $periodDays,
                'from' => $periodStart->toIso8601String(),
                'to' => $now->toIso8601String(),
            ],
            'metrics' => $metrics,
            'charts' => $charts,
            'attention' => $attention,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(string $table): array
    {
        return DB::table($table)
            ->whereNull('deleted_at')
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function entityStatusMetric(array $counts): array
    {
        return [
            'total' => array_sum($counts),
            'active' => (int) ($counts['ACTIVE'] ?? 0),
            'pending' => (int) ($counts['PENDING'] ?? 0),
            'inactive' => (int) ($counts['INACTIVE'] ?? 0),
            'rejected' => (int) ($counts['REJECTED'] ?? 0),
            'banned' => (int) ($counts['BANNED'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function subscriptionStatusCounts(): array
    {
        return DB::table('provider_subscriptions')
            ->whereNull('deleted_at')
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    private function activeSubscriptionQuery(Carbon $now)
    {
        return ProviderSubscription::query()
            ->where('status', ProviderSubscriptionStatus::ACTIVE->value)
            ->where(function ($query) use ($now): void {
                $query->whereNull('subscription_end_date')
                    ->orWhere('subscription_end_date', '>', $now);
            });
    }

    private function expiringSubscriptionQuery(Carbon $now)
    {
        return ProviderSubscription::query()
            ->where('status', ProviderSubscriptionStatus::ACTIVE->value)
            ->whereBetween('subscription_end_date', [
                $now,
                $now->copy()->addDays(self::EXPIRING_WITHIN_DAYS),
            ]);
    }

    private function expiredSubscriptionCount(Carbon $now): int
    {
        return ProviderSubscription::query()
            ->where(function ($query) use ($now): void {
                $query->where('status', ProviderSubscriptionStatus::EXPIRED->value)
                    ->orWhere(function ($activeQuery) use ($now): void {
                        $activeQuery->where('status', ProviderSubscriptionStatus::ACTIVE->value)
                            ->whereNotNull('subscription_end_date')
                            ->where('subscription_end_date', '<=', $now);
                    });
            })
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $distribution
     * @param  array<string, int>  $counts
     * @param  array<string, string>  $labels
     */
    private function appendStatusDistribution(
        array &$distribution,
        string $entity,
        array $counts,
        array $labels
    ): void {
        foreach ($labels as $status => $label) {
            $distribution[] = [
                'entity' => $entity,
                'status' => $status,
                'status_label' => $label,
                'value' => (int) ($counts[$status] ?? 0),
            ];
        }
    }

    /**
     * Select chart granularity from the same period used by the KPI queries.
     */
    private function periodBucket(string $period): string
    {
        return match ($period) {
            '7d', '30d' => 'day',
            '90d' => 'week',
            '12m' => 'month',
            default => 'day',
        };
    }

    private function bucketSqlFormat(string $bucket): string
    {
        return match ($bucket) {
            'week' => '%x-W%v',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }

    /**
     * @return array<int, array{period: string, value: int}>
     */
    private function subscriptionTrend(
        Carbon $now,
        string $period,
        Carbon $periodStart,
        string $bucket
    ): array {
        $format = $this->bucketSqlFormat($bucket);
        $rows = ProviderSubscription::query()
            ->whereBetween('subscription_start_date', [$periodStart, $now])
            ->selectRaw("DATE_FORMAT(subscription_start_date, '{$format}') as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total', 'bucket');

        return $this->timeSeries($rows->all(), $now, $period, $bucket, 'period');
    }

    /**
     * @return array<int, array{date: string, value: int}>
     */
    private function systemActivityTrend(
        Carbon $now,
        string $period,
        Carbon $periodStart,
        string $bucket
    ): array {
        $format = $this->bucketSqlFormat($bucket);
        $rows = SystemLog::query()
            ->whereBetween('log_date', [$periodStart, $now])
            ->selectRaw("DATE_FORMAT(log_date, '{$format}') as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total', 'bucket');

        return $this->timeSeries($rows->all(), $now, $period, $bucket, 'date');
    }

    /**
     * @return array<int, array{date: string, value: int}>
     */
    private function supportActivityTrend(
        Carbon $now,
        string $period,
        Carbon $periodStart,
        string $bucket
    ): array {
        $format = $this->bucketSqlFormat($bucket);
        $rows = SupportTicket::query()
            ->whereBetween('created_at', [$periodStart, $now])
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('total', 'bucket');

        return $this->timeSeries($rows->all(), $now, $period, $bucket, 'date');
    }

    /**
     * Fill empty buckets so charts keep a stable, honest time axis.
     *
     * @param  array<string, int|string>  $counts
     * @return array<int, array{period?: string, date?: string, value: int}>
     */
    private function timeSeries(
        array $counts,
        Carbon $now,
        string $period,
        string $bucket,
        string $labelKey
    ): array {
        $keys = [];

        if ($bucket === 'day') {
            $days = $period === '7d' ? 7 : 30;
            $firstDay = $now->copy()->startOfDay()->subDays($days - 1);

            for ($offset = 0; $offset < $days; $offset++) {
                $keys[] = $firstDay->copy()->addDays($offset)->toDateString();
            }
        } elseif ($bucket === 'week') {
            $firstWeek = $now->copy()->startOfWeek(Carbon::MONDAY);

            for ($offset = 12; $offset >= 0; $offset--) {
                $keys[] = $firstWeek->copy()->subWeeks($offset)->format('o-\\WW');
            }
        } else {
            $firstMonth = $now->copy()->startOfMonth()->subMonths(11);

            for ($offset = 0; $offset < 12; $offset++) {
                $keys[] = $firstMonth->copy()->addMonths($offset)->format('Y-m');
            }
        }

        return array_map(
            static fn (string $key): array => [
                $labelKey => $key,
                'value' => (int) ($counts[$key] ?? 0),
            ],
            $keys
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentSystemActivity(): array
    {
        return SystemLog::query()
            ->with(['user:user_id,user_full_name'])
            ->orderByDesc('log_id')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get([
                'log_id',
                'user_id',
                'module',
                'action',
                'entity_type',
                'entity_id',
                'log_date',
            ])
            ->map(fn (SystemLog $log): array => [
                'id' => (int) $log->log_id,
                'title' => (string) $log->action,
                'module' => (string) $log->module,
                'entity_type' => (string) $log->entity_type,
                'entity_id' => $log->entity_id !== null ? (int) $log->entity_id : null,
                'actor_name' => $log->user?->user_full_name,
                'occurred_at' => $log->log_date?->toIso8601String(),
                'source' => 'system_log',
            ])
            ->values()
            ->all();
    }

    /**
     * Support agents without audit permission see recent ticket creation only.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentSupportActivity(): array
    {
        return SupportTicket::query()
            ->with(['network:network_id,network_name'])
            ->orderByDesc('created_at')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get(['ticket_id', 'network_id', 'ticket_subject', 'created_at'])
            ->map(fn (SupportTicket $ticket): array => [
                'id' => (int) $ticket->ticket_id,
                'title' => (string) $ticket->ticket_subject,
                'module' => 'support',
                'entity_type' => 'support_ticket',
                'entity_id' => (int) $ticket->ticket_id,
                'actor_name' => $ticket->network?->network_name,
                'occurred_at' => $ticket->created_at?->toIso8601String(),
                'source' => 'support_ticket',
            ])
            ->values()
            ->all();
    }

    private function periodDays(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '90d' => 90,
            '12m' => 365,
            default => 30,
        };
    }
}
