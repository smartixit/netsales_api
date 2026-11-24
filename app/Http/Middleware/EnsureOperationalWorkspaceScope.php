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
 * يضمن السيادة الأمنية وعزل المنصات عبر رفض وصول جميع حسابات PLATFORM
 * إلى مسارات B2B التشغيلية، وتوجيهها إلى مساحة إدارة المنصة المخصصة.
 */
class EnsureOperationalWorkspaceScope
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->role_scope === RoleScope::PLATFORM->value) {
            return $this->errorResponse(
                'حسابات نطاق المنصة مخصصة لإدارة المنصة ولا يمكن استخدامها لتنفيذ العمليات التشغيلية لمساحات NETWORK و POS.',
                [
                    'scope' => ['يجب استخدام حساب ضمن نطاق الشبكة أو نقطة البيع لتنفيذ العمليات التشغيلية.'],
                ],
                403
            );
        }

        return $next($request);
    }
}
