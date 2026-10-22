<?php

namespace App\Services;

use App\Enums\RoleKey;
use App\Helpers\AmountFormatter;
use App\Http\Requests\API\V1\Dashboard\DashboardOverviewRequest;
use App\Interfaces\DashboardRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\RequestRepositoryInterface;
use App\Interfaces\SaleInvoiceRepositoryInterface;
use App\Models\Network;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * خدمة إحصائيات ومؤشرات لوحة القيادة الموحدة (Enterprise Dashboard Service).
 *
 * تتولى تنسيق البيانات المجمعة، إدارة الكاش الميكروي السريع (Micro-caching)،
 * وتطبيق نمط التخصيص التلقائي بحسب الدور المؤسسي (Role-based Dashboard Strategy).
 */
class DashboardService
{
    public function __construct(
        protected DashboardRepositoryInterface $dashboardRepository,
        protected NetworkRepositoryInterface $networkRepository,
        protected PosRepositoryInterface $posRepository,
        protected SaleInvoiceRepositoryInterface $saleInvoiceRepository,
        protected RequestRepositoryInterface $requestRepository,
    ) {}

    /**
     * جلب نظرة عامة شاملة للوحة القيادة متوافقة مع دور المستخدم النشط.
     *
     * @return array<string, mixed>
     */
    public function getOverview(User $user, DashboardOverviewRequest $request): array
    {
        $period = $request->getPeriod();
        $isForceRefresh = $request->boolean('refresh', false);

        $roleKey = $user->role?->role_key instanceof RoleKey
            ? $user->role->role_key
            : RoleKey::tryFrom((string) ($user->role?->role_key ?? ''));

        $roleKeyEnum = $roleKey ?? RoleKey::SUPER_ADMIN;
        $roleKeyValue = $roleKeyEnum->value;

        // توليد مفتاح كاش ذكي بحسب المستخدم والدور والفترة
        $contextId = $request->getNetworkId() ?? $request->getPosId() ?? 'default';
        $cacheKey = "dashboard:user:{$user->user_id}:{$roleKeyValue}:{$period}:{$contextId}";

        if ($isForceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($user, $request, $period, $roleKeyEnum): array {
            if ($user->isSuperAdmin()) {
                return $this->buildSuperAdminDashboard($period);
            }

            if ($user->hasRole(RoleKey::POS_AGENT) || $user->hasRole(RoleKey::POS_USER)) {
                return $this->buildPosAgentDashboard($user, $request, $period);
            }

            // الافتراضي لمزود الشبكة أو مستخدم الشبكة
            return $this->buildNetworkOwnerDashboard($user, $request, $period, $roleKeyEnum);
        });
    }

    /**
     * بناء لوحة القيادة لمزود الشبكة.
     *
     * @return array<string, mixed>
     */
    protected function buildNetworkOwnerDashboard(User $user, DashboardOverviewRequest $request, string $period, ?RoleKey $roleKeyEnum = null): array
    {
        /** @var Network|null $network */
        $network = null;
        $requestedNetworkId = $request->getNetworkId();

        if ($requestedNetworkId) {
            $network = $user->networks()->where('network_id', $requestedNetworkId)->with(['owner', 'wallet'])->first();
        }

        if (! $network) {
            $network = $user->networks()->with(['owner', 'wallet'])->first();
        }

        $effectiveRole = $roleKeyEnum ?? RoleKey::NETWORK_OWNER;

        if (! $network) {
            return [
                'role_key' => $effectiveRole,
                'period' => $period,
                'context' => [],
                'network' => null,
                'network_wallet' => null,
                'liquidity' => [
                    'balance' => 0.0,
                    'currency' => 'YER',
                    'status' => 'INACTIVE',
                ],
                'kpis' => [],
                'alerts' => [
                    [
                        'id' => 'alert_no_network',
                        'type' => 'SETUP_REQUIRED',
                        'severity' => 'INFO',
                        'title' => 'لا توجد شبكة مسجلة',
                        'message' => 'يرجى تسجيل أو اعتماد شبكتك للبدء في تتبع المؤشرات المالية',
                        'count' => 0,
                    ],
                ],
                'recent_activities' => [],
                'recent_invoices' => [],
                'recent_requests' => [],
            ];
        }

        $kpis = $this->dashboardRepository->getNetworkMetrics($network->network_id, $period);
        $recentInvoices = $this->saleInvoiceRepository->getFiltered(['network_id' => $network->network_id, 'per_page' => 5], ['pos', 'createdBy', 'items.voucherPackage']);
        $recentRequests = $this->requestRepository->getFiltered(['network_id' => $network->network_id, 'per_page' => 5], ['pos', 'createdBy', 'items.voucherPackage']);
        $recentActivities = $this->dashboardRepository->getRecentActivitiesForNetwork($network->network_id, 5);

        // تجهيز رادار التنبيهات التشغيلية اللحظية
        $alerts = [];
        $pendingCount = (int) ($kpis['requests']['pending_count'] ?? 0);
        if ($pendingCount > 0) {
            $alerts[] = [
                'id' => 'alert_pending_requests',
                'type' => 'PENDING_APPROVAL',
                'severity' => 'WARNING',
                'title' => 'طلبات كروت بانتظار الاعتماد',
                'message' => "لديك {$pendingCount} طلب كروت من نقاط البيع بانتظار مراجعتك واعتمادك الفوري.",
                'count' => $pendingCount,
                'action_url' => '/requests?status=PENDING',
                'action_label' => 'مراجعة الطلبات',
            ];
        }

        $balance = (float) ($network->wallet?->wallet_balance ?? 0.0);
        if ($balance <= 0.0) {
            $formattedZero = AmountFormatter::formatWithCurrency(0, space: true);
            $alerts[] = [
                'id' => 'alert_low_balance',
                'type' => 'LOW_LIQUIDITY',
                'severity' => 'INFO',
                'title' => 'رصيد المحفظة المركزية',
                'message' => "رصيد محفظة الشبكة {$formattedZero}. يمكنك تغذية المحفظة أو متابعة تحصيلات نقاط البيع.",
                'count' => 0,
                'action_url' => '/network-wallets',
                'action_label' => 'شحن المحفظة',
            ];
        }

        return [
            'role_key' => $effectiveRole,
            'period' => $period,
            'context' => [
                'network_id' => $network->network_id,
                'network_name' => $network->network_name,
                'network_code' => $network->network_code,
                'status' => $network->status,
            ],
            'network' => $network,
            'network_wallet' => $network->wallet,
            'liquidity' => [
                'balance' => (float) ($network->wallet?->wallet_balance ?? 0.0),
                'currency' => $network->wallet?->currency ?? 'YER',
                'status' => $network->wallet?->status ?? 'ACTIVE',
            ],
            'kpis' => $kpis,
            'alerts' => $alerts,
            'recent_activities' => $recentActivities,
            'recent_invoices' => $recentInvoices,
            'recent_requests' => $recentRequests,
        ];
    }

    /**
     * بناء لوحة القيادة لوكيل نقطة البيع.
     *
     * @return array<string, mixed>
     */
    protected function buildPosAgentDashboard(User $user, DashboardOverviewRequest $request, string $period): array
    {
        /** @var Pos|null $pos */
        $pos = $user->pos()->with(['owner', 'wallets'])->first();

        if (! $pos) {
            return [
                'role_key' => RoleKey::POS_AGENT,
                'period' => $period,
                'context' => [],
                'pos' => null,
                'pos_wallet' => null,
                'liquidity' => [
                    'balance' => 0.0,
                    'currency' => 'YER',
                    'status' => 'INACTIVE',
                    'is_overdrawn' => false,
                    'debit_amount' => 0.0,
                ],
                'kpis' => [],
                'alerts' => [
                    [
                        'id' => 'alert_no_pos',
                        'type' => 'SETUP_REQUIRED',
                        'severity' => 'INFO',
                        'title' => 'لا توجد نقطة بيع مسجلة',
                        'message' => 'يرجى استكمال تسجيل نقطة البيع واعتمادها للبدء',
                        'count' => 0,
                    ],
                ],
                'recent_activities' => [],
                'recent_invoices' => [],
                'recent_requests' => [],
            ];
        }

        $targetNetworkId = $request->getNetworkId();
        $kpis = $this->dashboardRepository->getPosMetrics($pos->pos_id, $targetNetworkId, $period);
        /** @var PosWallet|null $posWallet */
        $posWallet = $targetNetworkId
            ? $pos->wallets()->where('network_id', $targetNetworkId)->first()
            : $pos->wallets()->first();

        $invoiceFilters = ['pos_id' => $pos->pos_id, 'per_page' => 5];
        $requestFilters = ['pos_id' => $pos->pos_id, 'per_page' => 5];
        if ($targetNetworkId) {
            $invoiceFilters['network_id'] = $targetNetworkId;
            $requestFilters['network_id'] = $targetNetworkId;
        }

        $recentInvoices = $this->saleInvoiceRepository->getFiltered($invoiceFilters, ['network', 'createdBy', 'items.voucherPackage']);
        $recentRequests = $this->requestRepository->getFiltered($requestFilters, ['network', 'createdBy', 'items.voucherPackage']);
        $recentActivities = $this->dashboardRepository->getRecentActivitiesForPos($pos->pos_id, 5);

        // تجهيز رادار التنبيهات لنقطة البيع
        $alerts = [];
        $isOverdrawn = $posWallet instanceof PosWallet && $posWallet->isOverdrawn();
        $debitAmount = $posWallet instanceof PosWallet ? $posWallet->getDebitAmount() : 0.0;

        if ($isOverdrawn && $debitAmount > 0) {
            $formattedDebit = AmountFormatter::formatWithCurrency($debitAmount, space: true);
            $alerts[] = [
                'id' => 'alert_pos_debt',
                'type' => 'DEBT_OVERDUE',
                'severity' => 'DANGER',
                'title' => 'مديونية مستحقة للشبكة',
                'message' => "حساب محفظتك مدين بمبلغ {$formattedDebit} لصالح مزود الشبكة.",
                'count' => 1,
                'action_url' => '/pos-wallets',
                'action_label' => 'تسوية الحساب',
            ];
        }

        $pendingRequests = (int) ($kpis['requests']['pending_count'] ?? 0);
        if ($pendingRequests > 0) {
            $alerts[] = [
                'id' => 'alert_pos_pending_requests',
                'type' => 'REQUESTS_IN_PROGRESS',
                'severity' => 'INFO',
                'title' => 'طلبات كروت قيد المراجعة',
                'message' => "لديك {$pendingRequests} طلب كروت أرسلته للشبكة وبانتظار اعتماد التوريد.",
                'count' => $pendingRequests,
                'action_url' => '/requests',
                'action_label' => 'متابعة الطلبات',
            ];
        }

        return [
            'role_key' => RoleKey::POS_AGENT,
            'period' => $period,
            'context' => [
                'pos_id' => $pos->pos_id,
                'pos_name' => $pos->pos_name,
                'pos_code' => $pos->pos_code,
                'status' => $pos->status,
            ],
            'pos' => $pos,
            'pos_wallet' => $posWallet,
            'liquidity' => [
                'balance' => (float) ($posWallet?->wallet_balance ?? 0.0),
                'currency' => $posWallet?->currency ?? 'YER',
                'status' => $posWallet?->status ?? 'ACTIVE',
                'is_overdrawn' => $posWallet instanceof PosWallet && $posWallet->isOverdrawn(),
                'debit_amount' => (float) ($posWallet instanceof PosWallet ? $posWallet->getDebitAmount() : 0.0),
            ],
            'kpis' => $kpis,
            'alerts' => $alerts,
            'recent_activities' => $recentActivities,
            'recent_invoices' => $recentInvoices,
            'recent_requests' => $recentRequests,
        ];
    }

    /**
     * بناء لوحة القيادة السيادية للمدير العام.
     *
     * @return array<string, mixed>
     */
    protected function buildSuperAdminDashboard(string $period): array
    {
        $kpis = $this->dashboardRepository->getSuperAdminMetrics($period);
        $recentInvoices = $this->saleInvoiceRepository->getFiltered(['per_page' => 5], ['network', 'pos', 'createdBy']);
        $recentRequests = $this->requestRepository->getFiltered(['per_page' => 5], ['network', 'pos', 'createdBy']);
        $recentActivities = $this->dashboardRepository->getRecentActivitiesForAdmin(5);

        $alerts = [];
        $pendingRequests = (int) ($kpis['pending_requests']['count'] ?? 0);
        if ($pendingRequests > 0) {
            $alerts[] = [
                'id' => 'alert_admin_pending_requests',
                'type' => 'ADMIN_OVERVIEW',
                'severity' => 'WARNING',
                'title' => 'طلبات تداول معلقة في المنظومة',
                'message' => "يوجد {$pendingRequests} طلب كروت معلق بين الشبكات ونقاط البيع لم يُعتمد بعد.",
                'count' => $pendingRequests,
                'action_url' => '/requests',
                'action_label' => 'عرض الطلبات',
            ];
        }

        return [
            'role_key' => RoleKey::SUPER_ADMIN,
            'period' => $period,
            'context' => [
                'system_name' => 'NetSales Central Hub',
                'status' => 'ACTIVE',
            ],
            'liquidity' => [
                'balance' => 0.0,
                'currency' => 'YER',
                'status' => 'ACTIVE',
            ],
            'kpis' => $kpis,
            'alerts' => $alerts,
            'recent_activities' => $recentActivities,
            'recent_invoices' => $recentInvoices,
            'recent_requests' => $recentRequests,
        ];
    }
}
