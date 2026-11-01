<?php

namespace App\Http\Middleware;

use App\Enums\RoleKey;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * وسيط حظر حسابات إدارة المنصة العامة من العمليات التشغيلية الميدانية.
 *
 * يضمن السيادة الأمنية وعزل المنصات عبر رفض وصول حسابات SUPER_ADMIN و SUPPORT_AGENT
 * إلى مسارات B2B التشغيلية، وتوجيههم حصرياً لتطبيق Windows المكتبي المخصص لإدارة المنصة.
 */
class EnsureOperationalWorkspaceScope
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $roleKey = null;

            if ($user->role) {
                $roleKey = $user->role->role_key;
            } elseif ($user->role_id) {
                $roleKey = DB::table('roles')->where('role_id', $user->role_id)->value('role_key');
            }

            if ($roleKey === RoleKey::SUPER_ADMIN->value || $roleKey === RoleKey::SUPPORT_AGENT->value || $user->isSuperAdmin()) {
                return $this->errorResponse(
                    'حسابات إدارة المنصة العامة (SUPER_ADMIN / SUPPORT_AGENT) مخصصة حصرياً لتطبيق إدارة المنصة على نظام Windows، ولا يمكن استخدامها لإجراء عمليات تشغيلية ميدانية.',
                    [
                        'scope' => ['غير مصرح لحسابات إدارة المنصة العامة بتنفيذ العمليات الميدانية لمساحات عمل NETWORK و POS.'],
                    ],
                    403
                );
            }
        }

        return $next($request);
    }
}
