<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionMiddleware
{
    /**
     * فرض صلاحية محددة على المسار.
     *
     * الاستخدام في المسارات:
     * ```php
     * Route::post('send', [...])->middleware('permission:notifications.send');
     * ```
     * الممر يرفض الطلب برمز 403 إن لم يملك المستخدم الصلاحية
     * (سواءً عبر دوره أو عبر الصلاحيات المباشرة).
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthorizationException('عذراً، يجب تسجيل الدخول أولاً للوصول لهذا المسار.');
        }

        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException('عذراً، ليس لديك الصلاحية الكافية للقيام بهذا الإجراء.');
        }

        return $next($request);
    }
}
