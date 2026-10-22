<?php

namespace App\Repositories;

use App\Enums\InvoicePaymentStatus;
use App\Enums\RequestStatus;
use App\Interfaces\DashboardRepositoryInterface;
use App\Models\SaleInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * مستودع تجميع إحصائيات ومؤشرات لوحة القيادة الموحدة (Enterprise Dashboard Repository).
 *
 * يعتمد حصرياً على استعلامات التجميع السريعة على مستوى محرك قاعدة البيانات (Database Push-down)،
 * مع استخدام الفهارس المعرفة مسبقاً وتفادي الـ N+1 Query Problem نهائياً.
 */
class DashboardRepository extends BaseRepository implements DashboardRepositoryInterface
{
    public function __construct(SaleInvoice $model)
    {
        parent::__construct($model);
    }

    /**
     * حساب النطاقات الزمنية للفترة المطلوبة ومثيلتها السابقة بدقة عالية.
     *
     * @return array{cur_start: string, cur_end: string, prev_start: string, prev_end: string}
     */
    protected function resolveDateRanges(string $period): array
    {
        $now = Carbon::now('Asia/Aden');

        return match ($period) {
            'yesterday' => [
                'cur_start' => $now->copy()->subDay()->startOfDay()->toDateTimeString(),
                'cur_end' => $now->copy()->subDay()->endOfDay()->toDateTimeString(),
                'prev_start' => $now->copy()->subDays(2)->startOfDay()->toDateTimeString(),
                'prev_end' => $now->copy()->subDays(2)->endOfDay()->toDateTimeString(),
            ],
            'this_week' => [
                'cur_start' => $now->copy()->startOfWeek()->toDateTimeString(),
                'cur_end' => $now->copy()->endOfWeek()->toDateTimeString(),
                'prev_start' => $now->copy()->subWeek()->startOfWeek()->toDateTimeString(),
                'prev_end' => $now->copy()->subWeek()->endOfWeek()->toDateTimeString(),
            ],
            'this_month' => [
                'cur_start' => $now->copy()->startOfMonth()->toDateTimeString(),
                'cur_end' => $now->copy()->endOfMonth()->toDateTimeString(),
                'prev_start' => $now->copy()->subMonth()->startOfMonth()->toDateTimeString(),
                'prev_end' => $now->copy()->subMonth()->endOfMonth()->toDateTimeString(),
            ],
            'this_year' => [
                'cur_start' => $now->copy()->startOfYear()->toDateTimeString(),
                'cur_end' => $now->copy()->endOfYear()->toDateTimeString(),
                'prev_start' => $now->copy()->subYear()->startOfYear()->toDateTimeString(),
                'prev_end' => $now->copy()->subYear()->endOfYear()->toDateTimeString(),
            ],
            default => [ // 'today'
                'cur_start' => $now->copy()->startOfDay()->toDateTimeString(),
                'cur_end' => $now->copy()->endOfDay()->toDateTimeString(),
                'prev_start' => $now->copy()->subDay()->startOfDay()->toDateTimeString(),
                'prev_end' => $now->copy()->subDay()->endOfDay()->toDateTimeString(),
            ],
        };
    }

    /**
     * حساب نسبة التغير المئوي بين فترتين.
     */
    protected function calculateGrowth(float $current, float $previous): float
    {
        if ($previous <= 0.001) {
            return $current > 0.001 ? 100.0 : 0.0;
        }

        return (float) round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * جلب مؤشرات وإحصائيات مزود الشبكة للفترة المحددة.
     */
    public function getNetworkMetrics(int $networkId, string $period = 'today'): array
    {
        $ranges = $this->resolveDateRanges($period);

        // 1. مؤشرات المبيعات المجمعة للفترة الحالية والسابقة
        $salesData = DB::table('sale_invoices')
            ->where('network_id', $networkId)
            ->whereNull('deleted_at')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN invoice_total_amount ELSE 0 END), 0) AS current_sales,
                COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN invoice_total_amount ELSE 0 END), 0) AS previous_sales,
                COUNT(CASE WHEN invoice_date BETWEEN ? AND ? THEN 1 END) AS current_invoices_count,
                COUNT(CASE WHEN invoice_date BETWEEN ? AND ? THEN 1 END) AS previous_invoices_count,
                COALESCE(SUM(CASE WHEN payment_status != "PAID" AND invoice_date BETWEEN ? AND ? THEN remaining_amount ELSE 0 END), 0) AS credit_sales_amount,
                COALESCE(SUM(CASE WHEN payment_status = "PAID" AND invoice_date BETWEEN ? AND ? THEN paid_amount ELSE 0 END), 0) AS cash_sales_amount
            ', [
                $ranges['cur_start'], $ranges['cur_end'],
                $ranges['prev_start'], $ranges['prev_end'],
                $ranges['cur_start'], $ranges['cur_end'],
                $ranges['prev_start'], $ranges['prev_end'],
                $ranges['cur_start'], $ranges['cur_end'],
                $ranges['cur_start'], $ranges['cur_end'],
            ])
            ->first();

        // 2. مؤشرات طلبات الكروت المجمعة
        $requestsData = DB::table('requests')
            ->where('network_id', $networkId)
            ->whereNull('deleted_at')
            ->selectRaw('
                COUNT(CASE WHEN status = "PENDING" THEN 1 END) AS pending_count,
                COUNT(CASE WHEN status = "APPROVED" AND request_date BETWEEN ? AND ? THEN 1 END) AS approved_count,
                COUNT(CASE WHEN status = "REJECTED" AND request_date BETWEEN ? AND ? THEN 1 END) AS rejected_count,
                COALESCE(SUM(CASE WHEN status = "PENDING" THEN request_total_amount ELSE 0 END), 0) AS pending_total_amount
            ', [
                $ranges['cur_start'], $ranges['cur_end'],
                $ranges['cur_start'], $ranges['cur_end'],
            ])
            ->first();

        // 3. نقاط البيع المرتبطة النشطة
        $posData = DB::table('network_pos')
            ->where('network_id', $networkId)
            ->selectRaw('
                COUNT(*) AS total_pos_count,
                COUNT(CASE WHEN status = 1 THEN 1 END) AS active_pos_count
            ')
            ->first();

        // 4. الباقة الأكثر مبيعاً في الفترة الحالية
        $topPackage = DB::table('sale_invoice_items as sii')
            ->join('sale_invoices as si', 'si.invoice_id', '=', 'sii.invoice_id')
            ->join('voucher_packages as vp', 'vp.package_id', '=', 'sii.package_id')
            ->where('si.network_id', $networkId)
            ->whereNull('si.deleted_at')
            ->whereBetween('si.invoice_date', [$ranges['cur_start'], $ranges['cur_end']])
            ->select(
                'sii.package_id',
                'vp.package_name',
                'vp.package_color',
                DB::raw('SUM(sii.voucher_count) as total_vouchers_count'),
                DB::raw('SUM(sii.item_total_amount) as total_revenue')
            )
            ->groupBy('sii.package_id', 'vp.package_name', 'vp.package_color')
            ->orderByDesc('total_vouchers_count')
            ->first();

        // 5. محفظة الشبكة والسيولة
        $wallet = DB::table('network_wallets')
            ->where('network_id', $networkId)
            ->whereNull('deleted_at')
            ->select('wallet_id', 'wallet_uuid', 'wallet_balance', 'status')
            ->first();

        $curSales = (float) ($salesData->current_sales ?? 0.0);
        $prevSales = (float) ($salesData->previous_sales ?? 0.0);
        $walletBal = (float) ($wallet->wallet_balance ?? 0.0);

        return [
            'liquidity' => [
                'wallet_id' => $wallet->wallet_id ?? null,
                'wallet_uuid' => $wallet->wallet_uuid ?? null,
                'wallet_balance' => $walletBal,
                'currency' => 'YER',
                'currency_symbol' => 'ر.ي',
                'status' => (bool) ($wallet->status ?? true),
                'status_label' => ($wallet->status ?? true) ? 'محفظة نشطة' : 'محفظة معطلة',
                'is_overdrawn' => false,
                'debit_amount' => 0.0,
                'credit_amount' => $walletBal,
            ],
            'kpis' => [
                'sales' => [
                    'current_amount' => $curSales,
                    'previous_amount' => $prevSales,
                    'growth_percentage' => $this->calculateGrowth($curSales, $prevSales),
                    'invoices_count' => (int) ($salesData->current_invoices_count ?? 0),
                    'cash_sales_amount' => (float) ($salesData->cash_sales_amount ?? 0.0),
                    'credit_sales_amount' => (float) ($salesData->credit_sales_amount ?? 0.0),
                    'label' => $this->getPeriodLabel($period, 'مبيعات'),
                ],
                'requests' => [
                    'pending_count' => (int) ($requestsData->pending_count ?? 0),
                    'approved_count' => (int) ($requestsData->approved_count ?? 0),
                    'rejected_count' => (int) ($requestsData->rejected_count ?? 0),
                    'pending_total_amount' => (float) ($requestsData->pending_total_amount ?? 0.0),
                    'has_urgent' => ((int) ($requestsData->pending_count ?? 0)) > 0,
                    'label' => 'طلبات الكروت',
                ],
                'pos_network' => [
                    'active_count' => (int) ($posData->active_pos_count ?? 0),
                    'total_count' => (int) ($posData->total_pos_count ?? 0),
                    'label' => 'نقاط التوزيع النشطة',
                ],
                'top_package' => $topPackage ? [
                    'package_id' => (int) $topPackage->package_id,
                    'package_name' => (string) $topPackage->package_name,
                    'package_color' => $topPackage->package_color,
                    'vouchers_sold_count' => (int) $topPackage->total_vouchers_count,
                    'revenue' => (float) $topPackage->total_revenue,
                    'label' => 'الباقة الأكثر طلباً',
                ] : null,
            ],
            'ranges' => $ranges,
        ];
    }

    /**
     * جلب مؤشرات وإحصائيات وكيل نقطة البيع.
     */
    public function getPosMetrics(int $posId, ?int $networkId = null, string $period = 'today'): array
    {
        $ranges = $this->resolveDateRanges($period);

        // 1. محفظة نقطة البيع والمركز المحاسبي (مدين / دائن / متزن)
        $walletQuery = DB::table('pos_wallets')
            ->where('pos_id', $posId)
            ->whereNull('deleted_at');

        if ($networkId) {
            $walletQuery->where('network_id', $networkId);
        }

        $wallet = $walletQuery->first();
        $balance = (float) ($wallet->wallet_balance ?? 0.0);
        $isOverdrawn = $balance < -0.001;
        $debitAmount = $isOverdrawn ? abs($balance) : 0.0;
        $creditAmount = $balance > 0.001 ? $balance : 0.0;

        $accountingStatus = $isOverdrawn ? 'DEBIT' : ($balance > 0.001 ? 'CREDIT' : 'ZERO');
        $accountingLabel = match ($accountingStatus) {
            'DEBIT' => 'مدين (عليك مديونية مستحقة للشبكة)',
            'CREDIT' => 'دائن (رصيد متاح في محفظتك)',
            'ZERO' => 'متزن (لا يوجد رصيد أو مديونية)',
        };

        // 2. مبيعات نقطة البيع
        $salesQuery = DB::table('sale_invoices')
            ->where('pos_id', $posId)
            ->whereNull('deleted_at');

        if ($networkId) {
            $salesQuery->where('network_id', $networkId);
        }

        $salesData = $salesQuery->selectRaw('
            COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN invoice_total_amount ELSE 0 END), 0) AS current_sales,
            COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN invoice_total_amount ELSE 0 END), 0) AS previous_sales,
            COUNT(CASE WHEN invoice_date BETWEEN ? AND ? THEN 1 END) AS current_invoices_count,
            COALESCE(SUM(CASE WHEN payment_status != "PAID" THEN remaining_amount ELSE 0 END), 0) AS remaining_debt_to_network
        ', [
            $ranges['cur_start'], $ranges['cur_end'],
            $ranges['prev_start'], $ranges['prev_end'],
            $ranges['cur_start'], $ranges['cur_end'],
        ])
        ->first();

        // 3. طلبات كروت نقطة البيع من الشبكة
        $requestsQuery = DB::table('requests')
            ->where('pos_id', $posId)
            ->whereNull('deleted_at');

        if ($networkId) {
            $requestsQuery->where('network_id', $networkId);
        }

        $requestsData = $requestsQuery->selectRaw('
            COUNT(CASE WHEN status = "PENDING" THEN 1 END) AS pending_count,
            COUNT(CASE WHEN status = "APPROVED" AND request_date BETWEEN ? AND ? THEN 1 END) AS approved_count,
            COUNT(CASE WHEN status = "REJECTED" AND request_date BETWEEN ? AND ? THEN 1 END) AS rejected_count,
            COALESCE(SUM(CASE WHEN status = "PENDING" THEN request_total_amount ELSE 0 END), 0) AS pending_total_amount
        ', [
            $ranges['cur_start'], $ranges['cur_end'],
            $ranges['cur_start'], $ranges['cur_end'],
        ])
        ->first();

        $curSales = (float) ($salesData->current_sales ?? 0.0);
        $prevSales = (float) ($salesData->previous_sales ?? 0.0);

        return [
            'liquidity' => [
                'wallet_id' => $wallet->wallet_id ?? null,
                'wallet_uuid' => $wallet->wallet_uuid ?? null,
                'wallet_balance' => $balance,
                'currency' => 'YER',
                'currency_symbol' => 'ر.ي',
                'status' => (bool) ($wallet->status ?? true),
                'status_label' => ($wallet->status ?? true) ? 'محفظة نشطة' : 'محفظة معطلة',
                'accounting_status' => $accountingStatus,
                'accounting_status_label' => $accountingLabel,
                'is_overdrawn' => $isOverdrawn,
                'debit_amount' => $debitAmount,
                'credit_amount' => $creditAmount,
            ],
            'kpis' => [
                'sales' => [
                    'current_amount' => $curSales,
                    'previous_amount' => $prevSales,
                    'growth_percentage' => $this->calculateGrowth($curSales, $prevSales),
                    'invoices_count' => (int) ($salesData->current_invoices_count ?? 0),
                    'remaining_debt' => (float) ($salesData->remaining_debt_to_network ?? 0.0),
                    'label' => $this->getPeriodLabel($period, 'مبيعات'),
                ],
                'requests' => [
                    'pending_count' => (int) ($requestsData->pending_count ?? 0),
                    'approved_count' => (int) ($requestsData->approved_count ?? 0),
                    'rejected_count' => (int) ($requestsData->rejected_count ?? 0),
                    'pending_total_amount' => (float) ($requestsData->pending_total_amount ?? 0.0),
                    'has_urgent' => false,
                    'label' => 'طلبات التوريد الصادرة',
                ],
            ],
            'ranges' => $ranges,
        ];
    }

    /**
     * جلب مؤشرات المنظومة الكلية للمدير العام.
     */
    public function getSuperAdminMetrics(string $period = 'today'): array
    {
        $ranges = $this->resolveDateRanges($period);

        $networksCount = DB::table('networks')
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = "APPROVED" THEN 1 END) as approved,
                COUNT(CASE WHEN status = "PENDING" THEN 1 END) as pending
            ')
            ->first();

        $posCount = DB::table('pos')
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = "APPROVED" THEN 1 END) as approved,
                COUNT(CASE WHEN status = "PENDING" THEN 1 END) as pending
            ')
            ->first();

        $salesData = DB::table('sale_invoices')
            ->whereNull('deleted_at')
            ->selectRaw('
                COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN invoice_total_amount ELSE 0 END), 0) AS current_sales,
                COALESCE(SUM(CASE WHEN invoice_date BETWEEN ? AND ? THEN invoice_total_amount ELSE 0 END), 0) AS previous_sales,
                COUNT(CASE WHEN invoice_date BETWEEN ? AND ? THEN 1 END) AS current_invoices_count
            ', [
                $ranges['cur_start'], $ranges['cur_end'],
                $ranges['prev_start'], $ranges['prev_end'],
                $ranges['cur_start'], $ranges['cur_end'],
            ])
            ->first();

        $requestsPending = DB::table('requests')
            ->where('status', 'PENDING')
            ->whereNull('deleted_at')
            ->count();

        $curSales = (float) ($salesData->current_sales ?? 0.0);
        $prevSales = (float) ($salesData->previous_sales ?? 0.0);

        return [
            'liquidity' => [
                'wallet_balance' => $curSales,
                'currency' => 'YER',
                'currency_symbol' => 'ر.ي',
                'status' => true,
                'status_label' => 'المنظومة في حالة تشغيلية ممتازة',
                'is_overdrawn' => false,
                'debit_amount' => 0.0,
                'credit_amount' => $curSales,
            ],
            'kpis' => [
                'platform_sales' => [
                    'current_amount' => $curSales,
                    'previous_amount' => $prevSales,
                    'growth_percentage' => $this->calculateGrowth($curSales, $prevSales),
                    'invoices_count' => (int) ($salesData->current_invoices_count ?? 0),
                    'label' => $this->getPeriodLabel($period, 'تداول المنظومة'),
                ],
                'networks' => [
                    'total' => (int) ($networksCount->total ?? 0),
                    'approved' => (int) ($networksCount->approved ?? 0),
                    'pending' => (int) ($networksCount->pending ?? 0),
                    'label' => 'شبكات الإنترنت',
                ],
                'pos' => [
                    'total' => (int) ($posCount->total ?? 0),
                    'approved' => (int) ($posCount->approved ?? 0),
                    'pending' => (int) ($posCount->pending ?? 0),
                    'label' => 'نقاط البيع',
                ],
                'pending_requests' => [
                    'count' => $requestsPending,
                    'has_urgent' => $requestsPending > 0,
                    'label' => 'طلبات بانتظار المراجعة',
                ],
            ],
            'ranges' => $ranges,
        ];
    }

    /**
     * جلب العمليات والحركات الأخيرة الموحدة لمزود الشبكة.
     */
    public function getRecentActivitiesForNetwork(int $networkId, int $limit = 5): array
    {
        $invoices = DB::table('sale_invoices as si')
            ->join('pos as p', 'p.pos_id', '=', 'si.pos_id')
            ->where('si.network_id', $networkId)
            ->whereNull('si.deleted_at')
            ->orderByDesc('si.created_at')
            ->limit($limit)
            ->select([
                'si.invoice_id as id',
                'si.invoice_uuid as uuid',
                DB::raw('"SALE_INVOICE" as type'),
                DB::raw('"فاتورة مبيعات كروت" as title'),
                'p.pos_name as party_name',
                'si.invoice_total_amount as amount',
                'si.payment_status as status',
                'si.sale_type',
                'si.created_at',
            ])
            ->get();

        $requests = DB::table('requests as r')
            ->join('pos as p', 'p.pos_id', '=', 'r.pos_id')
            ->where('r.network_id', $networkId)
            ->whereNull('r.deleted_at')
            ->orderByDesc('r.created_at')
            ->limit($limit)
            ->select([
                'r.request_id as id',
                'r.request_uuid as uuid',
                DB::raw('"VOUCHER_REQUEST" as type'),
                DB::raw('"طلب توريد باقات كروت" as title'),
                'p.pos_name as party_name',
                'r.request_total_amount as amount',
                'r.status as status',
                'r.sale_type',
                'r.created_at',
            ])
            ->get();

        return $this->mergeAndSortActivities($invoices, $requests, $limit);
    }

    /**
     * جلب العمليات الأخيرة الموحدة لوكيل نقطة البيع.
     */
    public function getRecentActivitiesForPos(int $posId, int $limit = 5): array
    {
        $invoices = DB::table('sale_invoices as si')
            ->join('networks as n', 'n.network_id', '=', 'si.network_id')
            ->where('si.pos_id', $posId)
            ->whereNull('si.deleted_at')
            ->orderByDesc('si.created_at')
            ->limit($limit)
            ->select([
                'si.invoice_id as id',
                'si.invoice_uuid as uuid',
                DB::raw('"SALE_INVOICE" as type'),
                DB::raw('"فاتورة شراء كروت" as title'),
                'n.network_name as party_name',
                'si.invoice_total_amount as amount',
                'si.payment_status as status',
                'si.sale_type',
                'si.created_at',
            ])
            ->get();

        $requests = DB::table('requests as r')
            ->join('networks as n', 'n.network_id', '=', 'r.network_id')
            ->where('r.pos_id', $posId)
            ->whereNull('r.deleted_at')
            ->orderByDesc('r.created_at')
            ->limit($limit)
            ->select([
                'r.request_id as id',
                'r.request_uuid as uuid',
                DB::raw('"VOUCHER_REQUEST" as type'),
                DB::raw('"طلب توريد باقات كروت" as title'),
                'n.network_name as party_name',
                'r.request_total_amount as amount',
                'r.status as status',
                'r.sale_type',
                'r.created_at',
            ])
            ->get();

        return $this->mergeAndSortActivities($invoices, $requests, $limit);
    }

    /**
     * جلب الحركات الأخيرة السيادية للمدير العام.
     */
    public function getRecentActivitiesForAdmin(int $limit = 5): array
    {
        $invoices = DB::table('sale_invoices as si')
            ->join('networks as n', 'n.network_id', '=', 'si.network_id')
            ->join('pos as p', 'p.pos_id', '=', 'si.pos_id')
            ->whereNull('si.deleted_at')
            ->orderByDesc('si.created_at')
            ->limit($limit)
            ->select([
                'si.invoice_id as id',
                'si.invoice_uuid as uuid',
                DB::raw('"SALE_INVOICE" as type'),
                DB::raw('"فاتورة مبيعات شبكة" as title'),
                DB::raw('CONCAT(n.network_name, " -> ", p.pos_name) as party_name'),
                'si.invoice_total_amount as amount',
                'si.payment_status as status',
                'si.sale_type',
                'si.created_at',
            ])
            ->get();

        $requests = DB::table('requests as r')
            ->join('networks as n', 'n.network_id', '=', 'r.network_id')
            ->join('pos as p', 'p.pos_id', '=', 'r.pos_id')
            ->whereNull('r.deleted_at')
            ->orderByDesc('r.created_at')
            ->limit($limit)
            ->select([
                'r.request_id as id',
                'r.request_uuid as uuid',
                DB::raw('"VOUCHER_REQUEST" as type'),
                DB::raw('"طلب توريد باقات كروت" as title'),
                DB::raw('CONCAT(p.pos_name, " -> ", n.network_name) as party_name'),
                'r.request_total_amount as amount',
                'r.status as status',
                'r.sale_type',
                'r.created_at',
            ])
            ->get();

        return $this->mergeAndSortActivities($invoices, $requests, $limit);
    }

    /**
     * دمج وترتيب الحركات الأخيرة وتنسيق التسميات.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $invoices
     * @param  \Illuminate\Support\Collection<int, mixed>  $requests
     * @return array<int, array<string, mixed>>
     */
    protected function mergeAndSortActivities($invoices, $requests, int $limit): array
    {
        return $invoices->concat($requests)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->map(function ($item): array {
                $statusLabel = $this->resolveStatusLabel($item->type, (string) $item->status);

                return [
                    'id' => (int) $item->id,
                    'uuid' => (string) $item->uuid,
                    'type' => (string) $item->type,
                    'title' => (string) $item->title,
                    'party_name' => (string) $item->party_name,
                    'amount' => (float) $item->amount,
                    'status' => (string) $item->status,
                    'status_label' => $statusLabel,
                    'sale_type' => (string) ($item->sale_type ?? 'CASH'),
                    'created_at' => $item->created_at ? Carbon::parse($item->created_at)->toDateTimeString() : null,
                ];
            })
            ->all();
    }

    /**
     * تحديد التسمية العربية الصريحة للحالة بالاعتماد على Enums المحددة.
     */
    protected function resolveStatusLabel(string $type, string $status): string
    {
        if ($type === 'SALE_INVOICE') {
            return InvoicePaymentStatus::tryFrom($status)?->label() ?? $status;
        }

        return RequestStatus::tryFrom($status)?->label() ?? $status;
    }

    /**
     * عنوان الفترة بالعربية.
     */
    protected function getPeriodLabel(string $period, string $prefix): string
    {
        return match ($period) {
            'yesterday' => "{$prefix} الأمس",
            'this_week' => "{$prefix} هذا الأسبوع",
            'this_month' => "{$prefix} هذا الشهر",
            'this_year' => "{$prefix} هذا العام",
            default => "{$prefix} اليوم",
        };
    }
}
