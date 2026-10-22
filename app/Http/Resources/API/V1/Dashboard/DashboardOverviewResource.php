<?php

namespace App\Http\Resources\API\V1\Dashboard;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\RoleKey;
use App\Enums\SaleType;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\Network\NetworkWalletResource;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Http\Resources\API\V1\Pos\PosWalletResource;
use App\Http\Resources\API\V1\Request\RequestResource;
use App\Http\Resources\API\V1\SaleInvoice\SaleInvoiceResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد تحويل بيانات لوحة القيادة الموحدة (Dashboard Overview Resource).
 *
 * يعتمد حصرياً على الـ Enums المحددة الصريحة لكافة الحالات والتسميات،
 * مع الالتزام التام بنسق موارد النظام الموحدة وتنسيق الوقت القياسي عبر toTimeString.
 */
class DashboardOverviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleKey = $this->resolveRoleKey();

        return [
            'role_key' => $roleKey instanceof RoleKey ? $roleKey->value : (string) ($this['role_key'] ?? 'UNKNOWN'),
            'role_label' => $roleKey instanceof RoleKey
                ? $roleKey->label()
                : (RoleKey::tryFrom((string) ($this['role_key'] ?? ''))?->label() ?? (string) ($this['role_label'] ?? '')),
            'period' => (string) ($this['period'] ?? 'today'),

            // سياق الكيان (الشبكة أو نقطة البيع)
            'context' => $this->formatContext(),

            // موارد الكيانات الموحدة
            'network' => isset($this['network']) && $this['network'] ? new NetworkResource($this['network']) : null,
            'pos' => isset($this['pos']) && $this['pos'] ? new PosResource($this['pos']) : null,
            'network_wallet' => isset($this['network_wallet']) && $this['network_wallet'] ? new NetworkWalletResource($this['network_wallet']) : null,
            'pos_wallet' => isset($this['pos_wallet']) && $this['pos_wallet'] ? new PosWalletResource($this['pos_wallet']) : null,

            // السيولة المالية
            'liquidity' => (array) ($this['liquidity'] ?? []),

            // المؤشرات الإحصائية المحسوبة في قاعدة البيانات
            'kpis' => (array) ($this['kpis'] ?? []),

            // رادار التنبيهات التشغيلية اللحظية
            'alerts' => (array) ($this['alerts'] ?? []),

            // العمليات الأخيرة الموحدة (شريط زمني مدمج)
            'recent_activities' => $this->formatRecentActivities(),

            // الموارد القياسية المجمعة للعمليات والطلبات
            'recent_invoices' => isset($this['recent_invoices']) && $this['recent_invoices']
                ? SaleInvoiceResource::collection($this['recent_invoices'])
                : [],
            'recent_requests' => isset($this['recent_requests']) && $this['recent_requests']
                ? RequestResource::collection($this['recent_requests'])
                : [],

            // التوقيت وحالة التزامن
            'telemetry' => [
                'synced_at' => Carbon::now('Asia/Aden')->toDateTimeString(),
                'server_time' => Carbon::now('Asia/Aden')->toTimeString(),
            ],
        ];
    }

    /**
     * استخراج كائن RoleKey الصريح بنسق Enums الموحد.
     */
    protected function resolveRoleKey(): ?RoleKey
    {
        $raw = $this['role_key'] ?? null;
        if ($raw instanceof RoleKey) {
            return $raw;
        }

        return is_string($raw) ? RoleKey::tryFrom($raw) : null;
    }

    /**
     * تنسيق سياق الكيان باستخدام Enums المحددة (NetworkStatus أو PosStatus).
     *
     * @return array<string, mixed>
     */
    protected function formatContext(): array
    {
        $context = (array) ($this['context'] ?? []);
        $rawStatus = $context['status'] ?? null;

        if (isset($context['network_id'])) {
            $status = $rawStatus instanceof NetworkStatus
                ? $rawStatus
                : (is_string($rawStatus) ? NetworkStatus::tryFrom($rawStatus) : null);

            $context['status'] = $status instanceof NetworkStatus ? $status->value : (string) $rawStatus;
            $context['status_label'] = $status instanceof NetworkStatus
                ? $status->label()
                : (NetworkStatus::tryFrom((string) $rawStatus)?->label() ?? ($context['status_label'] ?? ''));
        } elseif (isset($context['pos_id'])) {
            $status = $rawStatus instanceof PosStatus
                ? $rawStatus
                : (is_string($rawStatus) ? PosStatus::tryFrom($rawStatus) : null);

            $context['status'] = $status instanceof PosStatus ? $status->value : (string) $rawStatus;
            $context['status_label'] = $status instanceof PosStatus
                ? $status->label()
                : (PosStatus::tryFrom((string) $rawStatus)?->label() ?? ($context['status_label'] ?? ''));
        }

        return $context;
    }

    /**
     * تنسيق الحركات الأخيرة باستخدام Enums الصريحة (SaleType, InvoicePaymentStatus, RequestStatus).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function formatRecentActivities(): array
    {
        $activities = (array) ($this['recent_activities'] ?? []);

        return array_map(function ($item): array {
            $item = (array) $item;
            $type = (string) ($item['type'] ?? '');
            $rawSaleType = $item['sale_type'] ?? null;
            $rawStatus = $item['status'] ?? null;

            // تحويل نمط البيع عبر كلاس SaleType الصريح
            $saleTypeEnum = $rawSaleType instanceof SaleType
                ? $rawSaleType
                : (is_string($rawSaleType) ? SaleType::tryFrom($rawSaleType) : null);

            $item['sale_type'] = $saleTypeEnum instanceof SaleType ? $saleTypeEnum->value : (string) $rawSaleType;
            $item['sale_type_label'] = $saleTypeEnum instanceof SaleType
                ? $saleTypeEnum->label()
                : (SaleType::tryFrom((string) $rawSaleType)?->label() ?? (string) $rawSaleType);

            // تحويل حالة السجل وفق نوع العملية بالاعتماد على Enums المحددة
            if ($type === 'SALE_INVOICE') {
                $invoiceStatus = $rawStatus instanceof InvoicePaymentStatus
                    ? $rawStatus
                    : (is_string($rawStatus) ? InvoicePaymentStatus::tryFrom($rawStatus) : null);

                $item['status'] = $invoiceStatus instanceof InvoicePaymentStatus ? $invoiceStatus->value : (string) $rawStatus;
                $item['status_label'] = $invoiceStatus instanceof InvoicePaymentStatus
                    ? $invoiceStatus->label()
                    : (InvoicePaymentStatus::tryFrom((string) $rawStatus)?->label() ?? (string) ($item['status_label'] ?? $rawStatus));
            } elseif ($type === 'VOUCHER_REQUEST') {
                $reqStatus = $rawStatus instanceof RequestStatus
                    ? $rawStatus
                    : (is_string($rawStatus) ? RequestStatus::tryFrom($rawStatus) : null);

                $item['status'] = $reqStatus instanceof RequestStatus ? $reqStatus->value : (string) $rawStatus;
                $item['status_label'] = $reqStatus instanceof RequestStatus
                    ? $reqStatus->label()
                    : (RequestStatus::tryFrom((string) $rawStatus)?->label() ?? (string) ($item['status_label'] ?? $rawStatus));
            }

            return $item;
        }, $activities);
    }
}
