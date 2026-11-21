<?php

namespace App\Http\Middleware;

use App\Models\Expense;
use App\Models\Request as RequestModel;
use App\Models\SaleInvoice;
use App\Services\TenantPermissionService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionMiddleware
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissions
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthorizationException('عذراً، يجب تسجيل الدخول أولاً للوصول لهذا المسار.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (str_starts_with($permission, 'expenses.')) {
            $expenseId = (int) ($request->route('id') ?? 0);
            $expense = $expenseId > 0
                ? Expense::withTrashed()->find($expenseId)
                : null;
            $networkId = (int) (
                $request->input('network_id')
                ?? $request->query('network_id')
                ?? $expense?->network_id
                ?? 0
            );

            if ($networkId <= 0 || ! $this->tenantPermissions->allowsNetwork($user, $networkId, $permission)) {
                throw new AuthorizationException('عذراً، لا تملك هذه الصلاحية داخل الشبكة المحددة.');
            }

            return $next($request);
        }

        if ($permission === 'requests.approve') {
            $requestId = (int) ($request->route('id') ?? 0);
            $record = $requestId > 0 ? RequestModel::withTrashed()->find($requestId) : null;
            $networkId = (int) (
                $request->input('network_id')
                ?? $request->query('network_id')
                ?? $record?->network_id
                ?? 0
            );

            if ($networkId <= 0 || ! $this->tenantPermissions->allowsNetwork($user, $networkId, $permission)) {
                throw new AuthorizationException('عذراً، لا تملك صلاحية اعتماد الطلبات داخل الشبكة المحددة.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'sales.')) {
            $invoiceId = (int) ($request->route('id') ?? 0);
            $invoice = $invoiceId > 0 ? SaleInvoice::withTrashed()->find($invoiceId) : null;
            $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? $invoice?->network_id ?? 0);
            $posId = (int) ($request->input('pos_id') ?? $request->query('pos_id') ?? $invoice?->pos_id ?? 0);

            $authorized = ($networkId > 0 && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission))
                || ($posId > 0 && $this->tenantPermissions->allowsPos($user, $posId, $permission));

            if (! $authorized) {
                throw new AuthorizationException('عذراً، لا تملك هذه الصلاحية داخل الشبكة أو نقطة البيع المحددة.');
            }

            return $next($request);
        }

        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException('عذراً، ليس لديك الصلاحية الكافية للقيام بهذا الإجراء.');
        }

        return $next($request);
    }
}
