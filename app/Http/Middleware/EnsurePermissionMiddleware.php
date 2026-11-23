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

        if (! $user->isActive()) {
            throw new AuthorizationException('الحساب غير نشط ولا يمكنه تنفيذ عمليات محمية.');
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

            if ($networkId > 0) {
                $authorized = $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
            } elseif ($request->has('ids')) {
                $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
                $records = Expense::withTrashed()->whereIn('expense_id', $ids)->get();
                $authorized = $ids !== [] && $records->count() === count($ids)
                    && $records->every(fn (Expense $record): bool =>
                        $this->tenantPermissions->allowsNetwork($user, (int) $record->network_id, $permission));
            } elseif ($permission === 'expenses.view') {
                $authorized = $this->tenantPermissions->allowsAnyNetwork($user, $permission);
            } else {
                $authorized = false;
            }

            if (! $authorized) {
                throw new AuthorizationException('عذراً، لا تملك هذه الصلاحية داخل الشبكة المحددة.');
            }

            return $next($request);
        }

        if ($permission === 'requests.create') {
            $posId = (int) ($request->input('pos_id') ?? $request->header('X-Pos-Id') ?? 0);
            if ($posId <= 0 || ! $this->tenantPermissions->allowsPos($user, $posId, $permission)) {
                throw new AuthorizationException('عذراً، لا تملك صلاحية إنشاء الطلب داخل نقطة البيع المحددة.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'requests.')) {
            $requestId = (int) ($request->route('id') ?? 0);
            $record = $requestId > 0 ? RequestModel::withTrashed()->find($requestId) : null;
            $networkId = (int) (
                $request->input('network_id')
                ?? $request->query('network_id')
                ?? $record?->network_id
                ?? 0
            );
            $posId = (int) (
                $request->input('pos_id')
                ?? $request->query('pos_id')
                ?? $record?->pos_id
                ?? 0
            );

            $authorized = false;
            if ($record !== null) {
                $networkAuthorized = $networkId > 0
                    && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
                $posAuthorized = $posId > 0
                    && $this->tenantPermissions->allowsPos($user, $posId, $permission);

                $authorized = in_array($permission, ['requests.approve', 'requests.reject', 'requests.preview_modification'], true)
                    ? $networkAuthorized
                    : ($networkAuthorized || $posAuthorized);
            } elseif ($networkId > 0 && $posId > 0) {
                $networkAuthorized = $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
                $posAuthorized = $this->tenantPermissions->allowsPos($user, $posId, $permission);
                $authorized = in_array($permission, ['requests.approve', 'requests.reject'], true)
                    ? $networkAuthorized
                    : ($networkAuthorized || $posAuthorized);
            } elseif ($networkId > 0) {
                $authorized = $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
            } elseif ($posId > 0) {
                $authorized = $this->tenantPermissions->allowsPos($user, $posId, $permission);
            } elseif ($request->has('ids')) {
                $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
                $records = RequestModel::withTrashed()->whereIn('request_id', $ids)->get();
                $authorized = $ids !== [] && $records->count() === count($ids)
                    && $records->every(fn (RequestModel $record): bool =>
                        $this->tenantPermissions->allowsNetwork($user, (int) $record->network_id, $permission)
                        || $this->tenantPermissions->allowsPos($user, (int) $record->pos_id, $permission));
            } elseif ($record === null && in_array($permission, ['requests.view', 'requests.create'], true)) {
                $authorized = $this->tenantPermissions->allowsAnyOperational($user, $permission);
            }

            if (! $authorized) {
                throw new AuthorizationException('عذراً، لا تملك الصلاحية المطلوبة داخل مساحة العمل المحددة.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'sales.')) {
            $invoiceId = (int) ($request->route('id') ?? 0);
            $invoice = $invoiceId > 0 ? SaleInvoice::withTrashed()->find($invoiceId) : null;

            // Detail reads are authorized by SaleInvoicePolicy in the controller,
            // preserving its resource-specific 403 response contract.
            if ($invoice !== null && $permission === 'sales.view') {
                return $next($request);
            }

            $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? $invoice?->network_id ?? 0);
            $posId = (int) ($request->input('pos_id') ?? $request->query('pos_id') ?? $invoice?->pos_id ?? 0);

            $authorized = ($networkId > 0 && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission))
                || ($posId > 0 && $this->tenantPermissions->allowsPos($user, $posId, $permission));

            if ($networkId <= 0 && $posId <= 0 && $invoice === null && $permission === 'sales.view') {
                $authorized = $this->tenantPermissions->allowsAnyOperational($user, $permission);
            }

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
