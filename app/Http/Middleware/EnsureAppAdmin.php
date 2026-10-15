<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يقيّد الوصول لمدير التطبيق فقط (Super Admin).
 */
class EnsureAppAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        $isSuperAdmin = $user !== null && (
            (int) $user->role_id === 1
            || $user->role?->role_key === 'super_admin'
        );

        if (! $isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'هذه العملية متاحة لمدير التطبيق فقط.',
                'data' => (object) [],
                'errors' => [],
            ], 403);
        }

        return $next($request);
    }
}
