<?php

namespace App\Interfaces;

/**
 * واجهة مستودع إحصائيات لوحة القيادة الموحدة (Dashboard Repository Interface).
 *
 * تتولى استعلامات التجميع المحسوبة مباشرة في قاعدة البيانات،
 * مع الالتزام التام بسياسة منع التجاوزات المعمارية.
 */
interface DashboardRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * جلب مؤشرات وإحصائيات مزود الشبكة للفترة المحددة مع المقارنة التلقائية بالفترة السابقة.
     *
     * @return array<string, mixed>
     */
    public function getNetworkMetrics(int $networkId, string $period = 'today'): array;

    /**
     * جلب مؤشرات وإحصائيات وكيل نقطة البيع للفترة المحددة مع حساب المركز المالي.
     *
     * @return array<string, mixed>
     */
    public function getPosMetrics(int $posId, ?int $networkId = null, string $period = 'today'): array;

    /**
     * جلب مؤشرات المنظومة الكلية للمدير العام للنظام.
     *
     * @return array<string, mixed>
     */
    public function getSuperAdminMetrics(string $period = 'today'): array;

    /**
     * جلب العمليات والحركات الأخيرة الموحدة لمزود الشبكة (فواتير مبيعات + طلبات كروت).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivitiesForNetwork(int $networkId, int $limit = 5): array;

    /**
     * جلب العمليات والحركات الأخيرة الموحدة لوكيل نقطة البيع.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivitiesForPos(int $posId, int $limit = 5): array;

    /**
     * جلب الحركات الأخيرة السيادية لكافة قطاعات النظام للمدير العام.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivitiesForAdmin(int $limit = 5): array;
}
