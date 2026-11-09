<?php

namespace App\\Http\\Middleware;

use App\\Enums\\RoleScope;
use App\\Models\\SecurityBan;
use App\\Models\\User;
use App\\Models\\UserDevice;
use App\\Models\\UserSession;
use Closure;
use Illuminate\\Auth\\Access\\AuthorizationException;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class EnsurePlatformUserScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor || $actor->role?->scope !== RoleScope::PLATFORM) {
            throw new AuthorizationException('هذا الإجراء متاح لمستخدمي نطاق المنصة فقط.');
        }

        if ($request->is('api/v1/users/batch-*')) {
            $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
            $users = User::withTrashed()->with('role')->whereIn('user_id', $ids)->get();

            if ($ids === [] || $users->count() !== count($ids) || $users->contains(fn (User $user): bool => $user->role?->scope !== RoleScope::PLATFORM)) {
                throw new AuthorizationException('تتضمن العملية حسابات غير موجودة أو خارج نطاق المنصة.');
            }
        }

        $target = $this->resolveTargetUser($request);

        if ($target !== null && $target->role?->scope !== RoleScope::PLATFORM) {
            throw new AuthorizationException('لا يمكن إدارة حساب خارج نطاق المنصة من لوحة التحكم.');
        }

        return $next($request);
    }

    private function resolveTargetUser(Request $request): ?User
    {
        $routeId = $request->route('id');

        if ($request->routeIs('api.v1.users.*') && $routeId !== null) {
            return User::withTrashed()->with('role')->findOrFail((int) $routeId);
        }

        if ($request->is('api/v1/users/*/sessions') || $request->is('api/v1/users/*/devices') || $request->is('api/v1/security/users/*/sessions') || $request->is('api/v1/security/users/*/devices')) {
            return User::withTrashed()->with('role')->findOrFail((int) $routeId);
        }

        if ($request->is('api/v1/security/ban-user') || $request->is('api/v1/ban-user')) {
            return User::withTrashed()->with('role')->findOrFail((int) $request->input('user_id'));
        }

        if ($request->is('api/v1/security/ban-device') || $request->is('api/v1/ban-device')) {
            $device = UserDevice::withTrashed()->with(['user' => fn ($query) => $query->withTrashed()->with('role')])->findOrFail((int) $request->input('device_id'));
            return $device->user;
        }

        if ($request->is('api/v1/security/sessions/*/terminate') || $request->is('api/v1/sessions/*/terminate')) {
            $session = UserSession::with(['user' => fn ($query) => $query->withTrashed()->with('role')])->findOrFail((int) $routeId);
            return $session->user;
        }

        if ($request->is('api/v1/security/bans/*') || $request->is('api/v1/bans/*')) {
            $ban = SecurityBan::withTrashed()->with([
                'user' => fn ($query) => $query->withTrashed()->with('role'),
                'device' => fn ($query) => $query->withTrashed()->with(['user' => fn ($userQuery) => $userQuery->withTrashed()->with('role')]),
            ])->findOrFail((int) $routeId);
            return $ban->user ?? $ban->device?->user;
        }

        return null;
    }
}
