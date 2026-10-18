<?php

namespace App\Http\Middleware;

use App\Enums\RoleKey;
use App\Models\User;
use App\Services\SystemSettingService;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * وسيط التحقق من وضع الصيانة المركزي للنظام (Centralized Maintenance Mode Middleware).
 *
 * يتحكم به عبر إعداد `maintenance_mode` في جدول `system_settings`:
 * - عند التعطيل (0): تمر كافة الطلبات بشكل طبيعي.
 * - عند التفعيل (1): يُحظر وصول المستخدمين العاديين ويُستثنى مديرو النظام (Super Admin / App Admin)
 *   ومسارات الفحص الصحي (Health Check) لضمان استمرارية المراقبة.
 */
class CheckMaintenanceMode
{
    use ApiResponseTrait;

    public function __construct(
        protected SystemSettingService $systemSettingService
    ) {}

    /**
     * معالجة الطلب والتأكد من وضع الصيانة.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isMaintenance = $this->systemSettingService->getBool('maintenance_mode', false);

        if (! $isMaintenance) {
            return $next($request);
        }

        // استثناء مسارات الفحص الصحي ومسارات الوثائق
        if ($request->is('up') || $request->is('api/v1/auth/health-check') || $request->is('api/documentation')) {
            return $next($request);
        }

        // السماح لمديري النظام (Super Admin) بإجراء عمليات الصيانة وتفقد النظام
        $user = $request->user();
        if ($user instanceof User) {
            if ($user->hasRole(RoleKey::SUPER_ADMIN)) {
                return $next($request);
            }
        }

        return $this->errorResponse(
            'النظام حالياً في وضع الصيانة المجدولة لتحسين الأداء وتحديث الخدمات. يرجى المحاولة في وقت لاحق.',
            ['maintenance_mode' => true],
            503
        );
    }
}
