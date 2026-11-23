<?php

namespace App\Http\Middleware;

use App\Enums\RoleKey;
use App\Models\Expense;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkPosLinkingChallenge;
use App\Models\Pos;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
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

        $operationalPrefixes = [
            'networks.',
            'pos.',
            'network_pos.',
            'network_wallets.',
            'pos_wallets.',
            'voucher_packages.',
            'package_discounts.',
            'requests.',
            'sales.',
            'expenses.',
        ];
        $isOperationalPermission = collect($operationalPrefixes)
            ->contains(fn (string $prefix): bool => str_starts_with($permission, $prefix));

        $platformOnlyOperationalPermissions = [
            'networks.create',
            'networks.restore',
            'networks.force_delete',
            'networks.approve',
            'networks.reject',
            'pos.create',
            'pos.delete',
            'pos.restore',
            'pos.force_delete',
            'pos.approve',
            'pos.reject',
            'network_wallets.view_trashed',
            'network_wallets.delete_wallet',
            'network_wallets.restore_wallet',
            'network_wallets.force_delete_wallet',
            'network_wallets.bulk_delete_wallets',
            'network_wallets.bulk_restore_wallets',
            'network_wallets.bulk_force_delete_wallets',
            'pos_wallets.view_trashed',
            'pos_wallets.delete_wallet',
            'pos_wallets.restore_wallet',
            'pos_wallets.force_delete_wallet',
            'pos_wallets.bulk_delete_wallets',
            'pos_wallets.bulk_restore_wallets',
            'pos_wallets.bulk_force_delete_wallets',
        ];

        if ($isOperationalPermission && (
            $user->hasRole(RoleKey::SUPPORT_AGENT)
            || ($user->isSuperAdmin() && ! in_array($permission, $platformOnlyOperationalPermissions, true))
        )) {
            throw new AuthorizationException('حسابات إدارة المنصة العامة لا تستخدم الصلاحيات التشغيلية للشبكات ونقاط البيع.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (str_starts_with($permission, 'networks.')) {
            if (in_array($permission, [
                'networks.create',
                'networks.restore',
                'networks.force_delete',
                'networks.approve',
                'networks.reject',
            ], true)) {
                if (! $user->hasPermission($permission)) {
                    throw new AuthorizationException('هذه العملية متاحة لإدارة المنصة فقط.');
                }

                return $next($request);
            }

            $networkId = (int) (
                $request->input('network_id')
                ?? $request->query('network_id')
                ?? $request->route('networkId')
                ?? $request->route('id')
                ?? 0
            );

            $authorized = $networkId > 0
                ? $this->tenantPermissions->allowsNetwork($user, $networkId, $permission)
                : $this->tenantPermissions->allowsAnyNetwork($user, $permission);

            if (! $authorized && $permission === 'networks.view' && $networkId > 0) {
                $posIds = $this->tenantPermissions->authorizedPosIds($user, 'network_pos.view');
                $authorized = $posIds !== [] && NetworkPos::query()
                    ->where('network_id', $networkId)
                    ->whereIn('pos_id', $posIds)
                    ->where('status', true)
                    ->exists();
            }

            if (! $authorized) {
                throw new AuthorizationException('لا تملك الصلاحية المطلوبة داخل الشبكة المحددة.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'pos.')) {
            if (in_array($permission, [
                'pos.create',
                'pos.delete',
                'pos.restore',
                'pos.force_delete',
                'pos.approve',
                'pos.reject',
            ], true)) {
                if (! $user->hasPermission($permission)) {
                    throw new AuthorizationException('هذه العملية متاحة لإدارة المنصة فقط.');
                }

                return $next($request);
            }

            $posId = (int) ($request->route('id') ?? $request->input('pos_id') ?? $request->query('pos_id') ?? 0);
            if ($posId > 0) {
                $authorized = $this->tenantPermissions->allowsPos($user, $posId, $permission);

                if (! $authorized && $permission === 'pos.view') {
                    $networkIds = $this->tenantPermissions->authorizedNetworkIds($user, 'pos.view');
                    $authorized = $networkIds !== [] && NetworkPos::query()
                        ->where('pos_id', $posId)
                        ->whereIn('network_id', $networkIds)
                        ->where('status', true)
                        ->exists();
                }
            } else {
                $authorized = $this->tenantPermissions->allowsAnyPos($user, $permission)
                    || ($permission === 'pos.view' && $this->tenantPermissions->allowsAnyNetwork($user, $permission));
            }

            if (! $authorized) {
                throw new AuthorizationException('لا تملك الصلاحية المطلوبة داخل نقطة البيع المحددة.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'network_pos.')) {
            $challengeUuid = (string) ($request->route('uuid') ?? $request->input('challenge_uuid') ?? '');
            $challenge = $challengeUuid !== ''
                ? NetworkPosLinkingChallenge::query()->where('challenge_uuid', $challengeUuid)->first()
                : null;

            if (in_array($permission, [
                'network_pos.preview_invitation',
                'network_pos.confirm_invitation',
            ], true)) {
                $authorized = $challenge !== null
                    ? $this->tenantPermissions->allowsPos($user, (int) $challenge->pos_id, $permission)
                    : $this->tenantPermissions->allowsAnyPos($user, $permission);
            } elseif ($permission === 'network_pos.view_invitation_status') {
                $authorized = $challenge !== null && (
                    $this->tenantPermissions->allowsNetwork($user, (int) $challenge->network_id, $permission)
                    || $this->tenantPermissions->allowsPos($user, (int) $challenge->pos_id, $permission)
                );
            } elseif ($permission === 'network_pos.revoke_invitation') {
                $authorized = $challenge !== null
                    && $this->tenantPermissions->allowsNetwork($user, (int) $challenge->network_id, $permission);
            } elseif ($permission === 'network_pos.view') {
                $authorized = $this->tenantPermissions->allowsAnyNetwork($user, $permission)
                    || $this->tenantPermissions->allowsAnyPos($user, $permission);
            } elseif ($request->has('ids')) {
                $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
                $records = NetworkPos::query()->whereIn('network_pos_id', $ids)->get();
                $authorized = $ids !== [] && $records->count() === count($ids)
                    && $records->every(fn (NetworkPos $link): bool =>
                        $this->tenantPermissions->allowsNetwork($user, (int) $link->network_id, $permission));
            } else {
                $networkId = (int) (
                    $request->input('network_id')
                    ?? $request->query('network_id')
                    ?? $request->route('networkId')
                    ?? 0
                );
                if ($networkId <= 0 && $request->route('id') !== null) {
                    $link = NetworkPos::query()->find((int) $request->route('id'));
                    $networkId = (int) ($link?->network_id ?? 0);
                }
                $authorized = $networkId > 0
                    ? $this->tenantPermissions->allowsNetwork($user, $networkId, $permission)
                    : $this->tenantPermissions->allowsAnyNetwork($user, $permission);
            }

            if (! $authorized) {
                throw new AuthorizationException('لا تملك الصلاحية المطلوبة لإدارة ارتباطات الشبكة ونقاط البيع.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'voucher_packages.')) {
            if (in_array($permission, ['voucher_packages.view', 'voucher_packages.view_trashed'], true)) {
                $authorized = $this->tenantPermissions->allowsAnyNetwork($user, $permission)
                    || $this->tenantPermissions->allowsAnyPos($user, $permission);
            } else {
                $packageId = (int) $request->route('id');
                $package = $packageId > 0 ? VoucherPackage::withTrashed()->find($packageId) : null;
                if ($permission === 'voucher_packages.create') {
                    $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? 0);
                } else {
                    $networkId = (int) ($package?->network_id ?? 0);
                }
                if ($request->has('ids')) {
                    $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
                    $packages = VoucherPackage::withTrashed()->whereIn('package_id', $ids)->get();
                    $authorized = $ids !== [] && $packages->count() === count($ids)
                        && $packages->every(fn (VoucherPackage $item): bool =>
                            $this->tenantPermissions->allowsNetwork($user, (int) $item->network_id, $permission));
                } else {
                    $authorized = $networkId > 0
                        && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
                }
            }

            if (! $authorized) {
                throw new AuthorizationException('لا تملك الصلاحية المطلوبة لإدارة باقات هذه الشبكة.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'package_discounts.')) {
            if (in_array($permission, ['package_discounts.view', 'package_discounts.view_trashed'], true)) {
                $authorized = $this->tenantPermissions->allowsAnyNetwork($user, $permission)
                    || $this->tenantPermissions->allowsAnyPos($user, $permission);
            } else {
                $discountId = (int) $request->route('id');
                $discount = $discountId > 0 ? VoucherPackageDiscount::withTrashed()->find($discountId) : null;
                if ($permission === 'package_discounts.create') {
                    $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? 0);
                } else {
                    $networkId = (int) ($discount?->network_id ?? 0);
                }
                if ($request->has('ids')) {
                    $ids = array_values(array_unique(array_map('intval', (array) $request->input('ids', []))));
                    $discounts = VoucherPackageDiscount::withTrashed()->whereIn('discount_id', $ids)->get();
                    $authorized = $ids !== [] && $discounts->count() === count($ids)
                        && $discounts->every(fn (VoucherPackageDiscount $item): bool =>
                            $this->tenantPermissions->allowsNetwork($user, (int) $item->network_id, $permission));
                } else {
                    $authorized = $networkId > 0
                        && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
                }
            }

            if (! $authorized) {
                throw new AuthorizationException('لا تملك الصلاحية المطلوبة لإدارة خصومات هذه الشبكة.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'support_tickets.')) {
            $authorized = $this->tenantPermissions->allowsAnyOperational($user, $permission);
            if (! $authorized) {
                throw new AuthorizationException('لا تملك الصلاحية المطلوبة لتذاكر الدعم.');
            }

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

        if (in_array($permission, ['requests.create', 'requests.quote'], true)) {
            $posId = (int) ($request->input('pos_id') ?? $request->header('X-Pos-Id') ?? 0);
            if ($posId <= 0 || ! $this->tenantPermissions->allowsPos($user, $posId, $permission)) {
                throw new AuthorizationException('عذراً، لا تملك الصلاحية المحددة داخل نقطة البيع.');
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
                $authorized = in_array($permission, ['requests.approve', 'requests.reject', 'requests.preview_modification'], true)
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
