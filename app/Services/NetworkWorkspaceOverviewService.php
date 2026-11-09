<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\SaleType;
use App\Models\Expense;
use App\Models\Network;
use App\Models\NetworkWallet;
use App\Models\Request as ProcurementRequest;
use App\Models\SaleInvoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class NetworkWorkspaceOverviewService
{
    private const TIMEZONE = 'Asia/Aden';

    /**
     * Build an overview using only records and permissions in the requested network.
     *
     * @return array<string, mixed>
     */
    public function getOverview(User $user, Network $network, string $period): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $days = $this->periodDays($period);
        $from = $now->subDays($days - 1)->startOfDay();
        $networkId = (int) $network->network_id;
        $can = static fn (string $permission): bool => $user->hasPermission($permission, $networkId);

        $metrics = [];
        $recentPendingRequests = [];

        if ($can('pos.view')) {
            $links = DB::table('network_pos')
                ->join('pos', 'pos.pos_id', '=', 'network_pos.pos_id')
                ->whereNull('pos.deleted_at')
                ->where('network_pos.network_id', $networkId)
                ->selectRaw(
                    'COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN network_pos.status = 1 THEN 1 ELSE 0 END), 0) as active,
                    COALESCE(SUM(CASE WHEN network_pos.status = 0 THEN 1 ELSE 0 END), 0) as inactive'
                )
                ->first();

            $metrics['points_of_sale'] = [
                'total' => (int) ($links->total ?? 0),
                'active' => (int) ($links->active ?? 0),
                'inactive' => (int) ($links->inactive ?? 0),
            ];
        }

        if ($can('requests.view')) {
            $requestCounts = DB::table('requests')
                ->where('network_id', $networkId)
                ->whereNull('deleted_at')
                ->select('status')
                ->selectRaw('COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $metrics['requests'] = [
                'total' => (int) $requestCounts->sum(),
                'pending' => (int) ($requestCounts[RequestStatus::PENDING->value] ?? 0),
                'approved' => (int) ($requestCounts[RequestStatus::APPROVED->value] ?? 0),
                'rejected' => (int) ($requestCounts[RequestStatus::REJECTED->value] ?? 0),
                'cancelled' => (int) ($requestCounts[RequestStatus::CANCELLED->value] ?? 0),
            ];

            $pendingRequests = ProcurementRequest::query()
                ->where('network_id', $networkId)
                ->where('status', RequestStatus::PENDING->value)
                ->with(['pos:pos_id,pos_name'])
                ->orderByDesc('request_date')
                ->orderByDesc('request_id')
                ->limit(5)
                ->get([
                    'request_id',
                    'request_uuid',
                    'network_id',
                    'pos_id',
                    'sale_type',
                    'request_date',
                    'status',
                ]);

            $recentPendingRequests = $pendingRequests->map(
                static function (ProcurementRequest $request): array {
                    $saleType = $request->sale_type instanceof SaleType
                        ? $request->sale_type
                        : SaleType::tryFrom((string) $request->sale_type);

                    return [
                        'request_id' => (int) $request->request_id,
                        'request_uuid' => $request->request_uuid,
                        'pos_id' => (int) $request->pos_id,
                        'pos_name' => $request->pos?->pos_name ?? 'نقطة بيع غير متاحة',
                        'sale_type' => $saleType?->value,
                        'sale_type_label' => $saleType?->label(),
                        'request_date' => $request->request_date?->toIso8601String(),
                        'status' => RequestStatus::PENDING->value,
                        'status_label' => RequestStatus::PENDING->label(),
                    ];
                }
            )->values()->all();
        }

        if ($can('voucher_packages.view')) {
            $packages = DB::table('voucher_packages')
                ->where('network_id', $networkId)
                ->whereNull('deleted_at')
                ->selectRaw(
                    'COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN network_pos.status = 1 THEN 1 ELSE 0 END), 0) as active,
                    COALESCE(SUM(CASE WHEN network_pos.status = 0 THEN 1 ELSE 0 END), 0) as inactive'
                )
                ->first();

            $metrics['packages'] = [
                'total' => (int) ($packages->total ?? 0),
                'active' => (int) ($packages->active ?? 0),
                'inactive' => (int) ($packages->inactive ?? 0),
            ];
        }

        if ($can('sales.view')) {
            $sales = SaleInvoice::query()
                ->where('network_id', $networkId)
                ->whereBetween('invoice_date', [$from, $now])
                ->selectRaw(
                    'COUNT(*) as invoice_count,
                    COALESCE(SUM(invoice_total_amount), 0) as total_amount,
                    COALESCE(SUM(paid_amount), 0) as paid_amount,
                    COALESCE(SUM(remaining_amount), 0) as outstanding_amount'
                )
                ->first();

            $metrics['sales'] = [
                'invoice_count' => (int) ($sales?->invoice_count ?? 0),
                'total_amount' => $this->formatAmount($sales?->total_amount),
                'paid_amount' => $this->formatAmount($sales?->paid_amount),
                'outstanding_amount' => $this->formatAmount($sales?->outstanding_amount),
            ];
        }

        if ($can('expenses.view')) {
            $expenses = Expense::query()
                ->where('network_id', $networkId)
                ->whereBetween('expense_date', [$from, $now])
                ->selectRaw(
                    'COUNT(*) as expense_count,
                    COALESCE(SUM(expense_total_amount), 0) as total_amount'
                )
                ->first();

            $metrics['expenses'] = [
                'count' => (int) ($expenses?->expense_count ?? 0),
                'total_amount' => $this->formatAmount($expenses?->total_amount),
            ];
        }

        if ($can('network_wallets.view')) {
            $wallet = NetworkWallet::query()
                ->where('network_id', $networkId)
                ->first(['wallet_id', 'network_id', 'wallet_balance', 'status']);

            $metrics['wallet'] = $wallet === null
                ? null
                : [
                    'wallet_id' => (int) $wallet->wallet_id,
                    'balance' => $this->formatAmount($wallet->wallet_balance),
                    'is_active' => (bool) $wallet->status,
                ];
        }

        $network->loadMissing('owner');

        return [
            'generated_at' => $now->toIso8601String(),
            'timezone' => self::TIMEZONE,
            'period' => [
                'key' => $period,
                'days' => $days,
                'from' => $from->toIso8601String(),
                'to' => $now->toIso8601String(),
            ],
            'network' => $network,
            'metrics' => $metrics,
            'recent_pending_requests' => $recentPendingRequests,
        ];
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

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }
}
