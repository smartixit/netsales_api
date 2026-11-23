<?php

namespace App\Http\Middleware;

use App\Enums\RoleScope;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
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
            $roleScope = $user->role?->scope;
            $isPlatformUser = $roleScope === RoleScope::PLATFORM
                || $roleScope === RoleScope::PLATFORM->value
                || $user->role_scope === RoleScope::PLATFORM->value;

            if ($isPlatformUser) {
                return $this->errorResponse(
                    'حسابات نطاق المنصة مخصصة حصرياً لتطبيق إدارة المنصة، ولا يمكنها تنفيذ العمليات التشغيلية لمساحات عمل الشبكات ونقاط البيع.',
                    [
                        'scope' => ['لا يُسمح لحسابات PLATFORM باستخدام واجهات NETWORK أو POS التشغيلية.'],
                    ],
                    403
                );
            }
        }

        return $next($request);
    }
}
