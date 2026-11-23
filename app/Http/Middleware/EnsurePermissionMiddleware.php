<?php

namespace App\Http\Middleware;

use App\Models\Expense;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkPosLinkingChallenge;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\Request as RequestModel;
use App\Models\SaleInvoice;
use App\Models\SupportTicket;
use App\Services\SupportTicketAuthorizationService;
use App\Models\User;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
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

        if ($this->tenantPermissions->allowsPlatform($user, $permission)) {
            return $next($request);
        }

        if (str_starts_with($permission, 'expenses.')) {
            $routeName = (string) ($request->route()?->getName() ?? '');
            $expenseId = (int) ($request->route('id') ?? 0);
            $expense = $expenseId > 0 ? Expense::withTrashed()->find($expenseId) : null;

            if (str_contains($routeName, 'batch_') && $request->has('ids')) {
                $ids = $this->positiveIds($request->input('ids', []));
                $records = Expense::withTrashed()->whereIn('expense_id', $ids)->get();

                $authorized = $ids !== [] && $records->count() === count($ids)
                    && $records->every(fn (Expense $record): bool =>
                        $this->tenantPermissions->allowsNetwork($user, (int) $record->network_id, $permission));
            } elseif ($expense !== null) {
                // A resource ID always wins over any client-supplied tenant ID.
                $authorized = $this->tenantPermissions->allowsNetwork(
                    $user,
                    (int) $expense->network_id,
                    $permission
                );
            } else {
                $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? 0);

                $authorized = $networkId > 0
                    ? $this->tenantPermissions->allowsNetwork($user, $networkId, $permission)
                    : (in_array($permission, ['expenses.view', 'expenses.report'], true)
                        && $this->tenantPermissions->allowsAnyNetwork($user, $permission));
            }

            if (! $authorized) {
                throw new AuthorizationException('عذراً، لا تملك هذه الصلاحية داخل الشبكة الفعلية المرتبطة بالسجل.');
            }

            $request->attributes->set('tenant_permission', $permission);

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
            $routeName = (string) ($request->route()?->getName() ?? '');
            $requestId = (int) ($request->route('id') ?? 0);
            $record = $requestId > 0 ? RequestModel::withTrashed()->find($requestId) : null;
            $networkOnly = in_array($permission, [
                'requests.approve',
                'requests.reject',
                'requests.preview_modification',
            ], true);

            if (str_contains($routeName, 'batch_') && $request->has('ids')) {
                $ids = $this->positiveIds($request->input('ids', []));
                $records = RequestModel::withTrashed()->whereIn('request_id', $ids)->get();

                $authorized = $ids !== [] && $records->count() === count($ids)
                    && $records->every(function (RequestModel $item) use ($user, $permission, $networkOnly): bool {
                        $networkAllowed = $this->tenantPermissions->allowsNetwork(
                            $user,
                            (int) $item->network_id,
                            $permission
                        );

                        return $networkOnly
                            ? $networkAllowed
                            : ($networkAllowed || $this->tenantPermissions->allowsPos(
                                $user,
                                (int) $item->pos_id,
                                $permission
                            ));
                    });
            } elseif ($record !== null) {
                // Never authorize a stored request using IDs supplied in its body.
                $networkAllowed = $this->tenantPermissions->allowsNetwork(
                    $user,
                    (int) $record->network_id,
                    $permission
                );
                $posAllowed = ! $networkOnly && $this->tenantPermissions->allowsPos(
                    $user,
                    (int) $record->pos_id,
                    $permission
                );

                $authorized = $networkAllowed || $posAllowed;
            } else {
                $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? 0);
                $posId = (int) ($request->input('pos_id') ?? $request->query('pos_id') ?? 0);

                if ($networkId > 0 && $posId > 0) {
                    $networkAllowed = $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
                    $posAllowed = ! $networkOnly && $this->tenantPermissions->allowsPos($user, $posId, $permission);
                    $authorized = $networkAllowed || $posAllowed;
                } elseif ($networkId > 0) {
                    $authorized = $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
                } elseif ($posId > 0 && ! $networkOnly) {
                    $authorized = in_array($permission, ['requests.view', 'requests.report'], true)
                        ? $this->tenantPermissions->allowsPosThroughPosOrLinkedNetwork($user, $posId, $permission)
                        : $this->tenantPermissions->allowsPos($user, $posId, $permission);
                } else {
                    $authorized = in_array($permission, ['requests.view', 'requests.report'], true)
                        && $this->tenantPermissions->allowsAnyOperational($user, $permission);
                }
            }

            if (! $authorized) {
                throw new AuthorizationException('عذراً، لا تملك الصلاحية المطلوبة في مساحة العمل الفعلية المرتبطة بالطلب.');
            }

            return $next($request);
        }

        if (str_starts_with($permission, 'sales.')) {
            $routeName = (string) ($request->route()?->getName() ?? '');
            $invoiceId = (int) ($request->route('id') ?? 0);
            $invoice = $invoiceId > 0 ? SaleInvoice::withTrashed()->find($invoiceId) : null;

            if (str_contains($routeName, 'batch_') && $request->has('ids')) {
                $ids = $this->positiveIds($request->input('ids', []));
                $invoices = SaleInvoice::withTrashed()->whereIn('invoice_id', $ids)->get();

                $authorized = $ids !== [] && $invoices->count() === count($ids)
                    && $invoices->every(fn (SaleInvoice $record): bool =>
                        $this->tenantPermissions->allowsNetwork($user, (int) $record->network_id, $permission)
                        || $this->tenantPermissions->allowsPos($user, (int) $record->pos_id, $permission));
            } elseif ($invoice !== null) {
                // Resource ownership is resolved from the persisted invoice, not request parameters.
                $authorized = $this->tenantPermissions->allowsNetwork(
                    $user,
                    (int) $invoice->network_id,
                    $permission
                ) || $this->tenantPermissions->allowsPos(
                    $user,
                    (int) $invoice->pos_id,
                    $permission
                );
            } else {
                $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? 0);
                $posId = (int) ($request->input('pos_id') ?? $request->query('pos_id') ?? 0);

                if ($networkId > 0 || $posId > 0) {
                    $authorized = ($networkId > 0 && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission))
                        || ($posId > 0 && $this->tenantPermissions->allowsPos($user, $posId, $permission));
                } else {
                    $authorized = in_array($permission, ['sales.view', 'sales.report'], true)
                        && $this->tenantPermissions->allowsAnyOperational($user, $permission);
                }
            }

            if (! $authorized) {
                throw new AuthorizationException('عذراً، لا تملك هذه الصلاحية في مساحة العمل الفعلية المرتبطة بالفاتورة.');
            }

            return $next($request);
        }

        $scopedAuthorization = $this->authorizeScopedPermission($request, $user, $permission);
        if ($scopedAuthorization !== null) {
            if (! $scopedAuthorization) {
                $message = str_starts_with($permission, 'network_wallets.')
                    || str_starts_with($permission, 'pos_wallets.')
                    ? 'غير مصرح لك بالوصول إلى هذه المحفظة.'
                    : (str_starts_with($permission, 'sales.')
                        ? 'غير مصرح لك بالوصول إلى هذه الفاتورة.'
                        : 'عذراً، لا تملك هذه الصلاحية داخل مساحة العمل أو الكيان المحدد.');

                throw new AuthorizationException($message);
            }

            return $next($request);
        }

        if (! $user->hasPermission($permission)) {
            throw new AuthorizationException('عذراً، ليس لديك الصلاحية الكافية للقيام بهذا الإجراء.');
        }

        return $next($request);
    }

    private function authorizeScopedPermission(Request $request, User $user, string $permission): ?bool
    {
        if (str_starts_with($permission, 'support.')) {
            return $this->authorizeSupportPermission($request, $user, $permission);
        }

        if (str_starts_with($permission, 'networks.')) {
            return $this->authorizeNetworkPermission($request, $user, $permission);
        }

        if (str_starts_with($permission, 'pos.')) {
            return $this->authorizePosPermission($request, $user, $permission);
        }

        if (str_starts_with($permission, 'network_wallets.')) {
            return $this->authorizeNetworkWalletPermission($request, $user, $permission);
        }

        if (str_starts_with($permission, 'pos_wallets.')) {
            return $this->authorizePosWalletPermission($request, $user, $permission);
        }

        if (str_starts_with($permission, 'voucher_packages.')
            || str_starts_with($permission, 'voucher_package_discounts.')
        ) {
            return $this->authorizeVoucherPackagePermission($request, $user, $permission);
        }

        return null;
    }

    private function authorizeSupportPermission(Request $request, User $user, string $permission): bool
    {
        if ($user->role_scope === \App\Enums\RoleScope::PLATFORM->value) {
            return $this->tenantPermissions->allowsPlatform($user, $permission);
        }

        $authorization = app(SupportTicketAuthorizationService::class);
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (str_contains($routeName, '.batch_') && $request->has('ids')) {
            $withTrashed = in_array($permission, ['support.restore', 'support.force_delete'], true);
            $authorization->assertBatch(
                $user,
                $this->positiveIds($request->input('ids', [])),
                $permission,
                $withTrashed
            );

            return true;
        }

        $ticketId = (int) ($request->route('id') ?? 0);
        if ($ticketId > 0) {
            $ticket = SupportTicket::withTrashed()->find($ticketId);
            if (! $ticket) {
                return $this->tenantPermissions->allowsAnyOperational($user, $permission);
            }

            $authorization->assertTicket($user, $ticket, $permission);

            return true;
        }

        if ($permission === 'support.create') {
            $networkId = (int) ($request->input('network_id') ?? 0);
            $posValue = $request->input('pos_id');
            if ($networkId <= 0 || $posValue === null || $posValue === '') {
                // Let the FormRequest return the canonical 422 field errors.
                return $this->tenantPermissions->allowsAnyOperational($user, $permission);
            }

            $authorization->assertTarget($user, $networkId, (int) $posValue, $permission);

            return true;
        }

        // Collection routes are scoped by SupportTicketAuthorizationService
        // before data is returned. A client filter may only narrow that scope.
        return $this->tenantPermissions->allowsAnyOperational($user, $permission);
    }

    private function authorizeNetworkPermission(Request $request, User $user, string $permission): bool
    {
        $networkIds = [];
        $posIds = [];
        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName === 'api.v1.network_pos.challenges.revoke') {
            return $this->tenantPermissions->allowsAnyNetwork($user, $permission);
        }

        if ($routeName === 'api.v1.network_pos.challenges.status') {
            $challengeUuid = (string) ($request->route('uuid') ?? '');
            $challenge = NetworkPosLinkingChallenge::query()
                ->where('challenge_uuid', $challengeUuid)
                ->first();

            if (! $challenge) {
                return $this->tenantPermissions->allowsAnyOperational($user, $permission);
            }

            return $this->tenantPermissions->allowsNetwork($user, (int) $challenge->network_id, $permission)
                || $this->tenantPermissions->allowsPos($user, (int) $challenge->pos_id, $permission);
        }

        if ($request->route('networkId') !== null) {
            $networkIds[] = (int) $request->route('networkId');
        }

        $inputNetworkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? 0);
        if ($inputNetworkId > 0) {
            $networkIds[] = $inputNetworkId;
        }

        if (str_starts_with($routeName, 'api.v1.network_pos.')) {
            $challengeUuid = (string) ($request->route('uuid') ?? '');
            if ($challengeUuid !== '') {
                $challenge = NetworkPosLinkingChallenge::query()
                    ->where('challenge_uuid', $challengeUuid)
                    ->first();

                if ($challenge) {
                    $networkIds[] = (int) $challenge->network_id;
                    if ($permission === 'networks.view') {
                        $posIds[] = (int) $challenge->pos_id;
                    }
                }
            }

            $linkId = (int) ($request->route('id') ?? 0);
            if ($linkId > 0) {
                $link = NetworkPos::query()->find($linkId);
                if ($link) {
                    $networkIds[] = (int) $link->network_id;
                    if ($permission === 'networks.view') {
                        $posIds[] = (int) $link->pos_id;
                    }
                } else {
                    return false;
                }
            }

            if ($request->has('ids') && str_contains($routeName, 'batch_')) {
                $ids = $this->positiveIds($request->input('ids', []));
                $links = NetworkPos::query()->whereIn('network_pos_id', $ids)->get();
                if ($ids === [] || $links->count() !== count($ids)) {
                    return false;
                }

                foreach ($links as $link) {
                    $networkIds[] = (int) $link->network_id;
                    if ($permission === 'networks.view') {
                        $posIds[] = (int) $link->pos_id;
                    }
                }
            }
        } elseif (str_starts_with($routeName, 'api.v1.networks.')) {
            $routeId = (int) ($request->route('id') ?? 0);
            if ($routeId > 0 && ! str_contains($routeName, '.batch_')) {
                $networkIds[] = $routeId;
            }

            if ($request->has('ids') && str_contains($routeName, '.batch_')) {
                $ids = $this->positiveIds($request->input('ids', []));
                $networks = Network::withTrashed()->whereIn('network_id', $ids)->get();
                if ($ids === [] || $networks->count() !== count($ids)) {
                    return false;
                }

                foreach ($networks as $network) {
                    $networkIds[] = (int) $network->network_id;
                }
            }
        }

        $networkIds = $this->positiveIds($networkIds);
        $posIds = $this->positiveIds($posIds);

        if ($networkIds === [] && $posIds === []) {
            if ($permission !== 'networks.view') {
                return false;
            }

            if (str_contains($routeName, 'network_pos.index') || str_contains($routeName, 'networks.index')) {
                return $this->tenantPermissions->allowsAnyOperational($user, $permission);
            }

            return $this->tenantPermissions->allowsAnyNetwork($user, $permission);
        }

        foreach ($networkIds as $networkId) {
            $networkAllowed = $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);

            if ($networkAllowed) {
                continue;
            }

            if ($permission !== 'networks.view') {
                return false;
            }

            $authorizedPosIds = $this->tenantPermissions->authorizedPosIds($user, 'networks.view');
            if ($authorizedPosIds === [] || ! NetworkPos::query()
                ->where('network_id', $networkId)
                ->whereIn('pos_id', $authorizedPosIds)
                ->exists()) {
                return false;
            }
        }

        foreach ($posIds as $posId) {
            if (! $this->tenantPermissions->allowsPos($user, $posId, $permission)) {
                return false;
            }
        }

        return true;
    }

    private function authorizePosPermission(Request $request, User $user, string $permission): bool
    {
        $posIds = [];
        $routeName = (string) ($request->route()?->getName() ?? '');
        if (in_array($routeName, [
            'api.v1.network_pos.challenges.preview',
            'api.v1.network_pos.challenges.confirm',
        ], true)) {
            // The FormRequest must validate payload/UUID first. The challenge
            // service then authorizes the exact POS target before executing.
            return $this->tenantPermissions->mayAttemptPosPermission($user, $permission);
        }

        $posUuid = (string) ($request->input('pos_uuid') ?? $request->query('pos_uuid') ?? '');

        if ($posUuid !== '') {
            $pos = Pos::query()->where('pos_uuid', $posUuid)->first();
            if (! $pos) {
                return false;
            }

            $posIds[] = (int) $pos->pos_id;
        }

        $routePosId = (int) ($request->route('posId') ?? 0);
        if ($routePosId > 0) {
            $posIds[] = $routePosId;
        }

        if (str_starts_with($routeName, 'api.v1.pos.')) {
            $routeId = (int) ($request->route('id') ?? 0);
            if ($routeId > 0 && ! str_contains($routeName, '.batch_')) {
                $posIds[] = $routeId;
            }

            if ($request->has('ids') && str_contains($routeName, '.batch_')) {
                $ids = $this->positiveIds($request->input('ids', []));
                $records = Pos::withTrashed()->whereIn('pos_id', $ids)->get();
                if ($ids === [] || $records->count() !== count($ids)) {
                    return false;
                }

                foreach ($records as $record) {
                    $posIds[] = (int) $record->pos_id;
                }
            }
        }

        $posIds = $this->positiveIds($posIds);

        if ($posIds === []) {
            return $permission === 'pos.view'
                && $this->tenantPermissions->allowsAnyOperational($user, $permission);
        }

        foreach ($posIds as $posId) {
            if (! $this->tenantPermissions->allowsPos($user, $posId, $permission)) {
                return false;
            }
        }

        return true;
    }

    private function authorizeNetworkWalletPermission(Request $request, User $user, string $permission): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        // Collection endpoints apply the exact allowed tenant IDs in the
        // service/repository layer. Client filters may only narrow that set,
        // so a foreign network filter returns an empty list rather than 403.
        if (in_array($routeName, ['api.v1.network_wallets.index', 'api.v1.network_wallets.trashed'], true)) {
            return $permission === 'network_wallets.view'
                && $this->tenantPermissions->allowsAnyNetwork($user, $permission);
        }

        $networkIds = [];
        $routeId = (int) ($request->route('id') ?? 0);

        if ($routeId > 0) {
            $wallet = NetworkWallet::withTrashed()->find($routeId);
            if (! $wallet) {
                return false;
            }
            $networkIds[] = (int) $wallet->network_id;
        }

        $networkId = (int) ($request->route('networkId') ?? $request->input('network_id') ?? $request->query('network_id') ?? 0);
        if ($networkId > 0) {
            $networkIds[] = $networkId;
        }

        if ($request->has('ids') && str_contains((string) ($request->route()?->getName() ?? ''), 'batch_')) {
            $ids = $this->positiveIds($request->input('ids', []));
            $wallets = NetworkWallet::withTrashed()->whereIn('wallet_id', $ids)->get();
            if ($ids === [] || $wallets->count() !== count($ids)) {
                return false;
            }
            foreach ($wallets as $wallet) {
                $networkIds[] = (int) $wallet->network_id;
            }
        }

        $networkIds = $this->positiveIds($networkIds);

        if ($networkIds === []) {
            return $permission === 'network_wallets.view'
                && $this->tenantPermissions->allowsAnyNetwork($user, $permission);
        }

        foreach ($networkIds as $networkId) {
            if (! $this->tenantPermissions->allowsNetwork($user, $networkId, $permission)) {
                return false;
            }
        }

        return true;
    }

    private function authorizePosWalletPermission(Request $request, User $user, string $permission): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        // The list query is scoped by permission-bearing network/POS IDs in
        // PosWalletService. Treat a client filter as narrowing, not as an
        // authorization target which can turn an empty result into a 403.
        if (in_array($routeName, ['api.v1.pos_wallets.index', 'api.v1.pos_wallets.trashed'], true)) {
            return $permission === 'pos_wallets.view'
                && $this->tenantPermissions->allowsAnyOperational($user, $permission);
        }

        $targets = [];
        $routeId = (int) ($request->route('id') ?? 0);

        if ($routeId > 0) {
            $wallet = PosWallet::withTrashed()->find($routeId);
            if (! $wallet) {
                return false;
            }
            $targets[] = ['network_id' => (int) $wallet->network_id, 'pos_id' => (int) $wallet->pos_id];
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if (str_contains($routeName, 'batch_') && $request->has('ids')) {
            $ids = $this->positiveIds($request->input('ids', []));
            $wallets = PosWallet::withTrashed()->whereIn('wallet_id', $ids)->get();
            if ($ids === [] || $wallets->count() !== count($ids)) {
                return false;
            }

            foreach ($wallets as $wallet) {
                $targets[] = ['network_id' => (int) $wallet->network_id, 'pos_id' => (int) $wallet->pos_id];
            }
        }

        $networkId = (int) ($request->route('networkId') ?? $request->input('network_id') ?? $request->query('network_id') ?? 0);
        $posId = (int) ($request->route('posId') ?? $request->input('pos_id') ?? $request->query('pos_id') ?? 0);

        if ($networkId > 0 && $posId > 0) {
            // The wallet row itself is the authoritative network/POS pair.
            // A POS owner may read its own wallet even when the separate link
            // record is inactive or was not created by legacy wallet imports.
            $targets[] = ['network_id' => $networkId, 'pos_id' => $posId];
        } elseif ($networkId > 0) {
            $targets[] = ['network_id' => $networkId, 'pos_id' => 0];
        } elseif ($posId > 0 && $targets === []) {
            $targets[] = ['network_id' => 0, 'pos_id' => $posId];
        }

        if ($targets === []) {
            return $permission === 'pos_wallets.view'
                && $this->tenantPermissions->allowsAnyOperational($user, $permission);
        }

        foreach ($targets as $target) {
            $networkId = $target['network_id'];
            $posId = $target['pos_id'];

            $networkAllowed = $networkId > 0
                && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);

            $posAllowed = $permission === 'pos_wallets.view'
                && $posId > 0
                && $this->tenantPermissions->allowsPos($user, $posId, $permission);

            if (! $networkAllowed && ! $posAllowed) {
                return false;
            }
        }

        return true;
    }

    private function authorizeVoucherPackagePermission(Request $request, User $user, string $permission): bool
    {
        $networkIds = [];
        $posTargets = [];
        $routeName = (string) ($request->route()?->getName() ?? '');
        $viewPermission = str_starts_with($routeName, 'api.v1.voucher_package_discounts.')
            ? 'voucher_package_discounts.view'
            : 'voucher_packages.view';

        if ($routeName === 'api.v1.networks.voucher_packages') {
            $networkId = (int) ($request->route('id') ?? 0);
            if ($networkId > 0) {
                $networkIds[] = $networkId;
            }
        } elseif (str_starts_with($routeName, 'api.v1.voucher_package_discounts.')) {
            $discountId = (int) ($request->route('id') ?? 0);
            if ($discountId > 0) {
                $discount = VoucherPackageDiscount::withTrashed()->find($discountId);
                if (! $discount) {
                    return false;
                }
                $networkIds[] = (int) $discount->network_id;
                if ($discount->pos_id !== null) {
                    $posTargets[] = [
                        'network_id' => (int) $discount->network_id,
                        'pos_id' => (int) $discount->pos_id,
                    ];
                }
            }
        } elseif (str_starts_with($routeName, 'api.v1.voucher_packages.')) {
            $packageId = (int) ($request->route('id') ?? 0);
            if ($packageId > 0) {
                $package = VoucherPackage::withTrashed()->find($packageId);
                if (! $package) {
                    return false;
                }
                $networkIds[] = (int) $package->network_id;
            }
        }

        $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? 0);
        $posId = (int) ($request->input('pos_id') ?? $request->query('pos_id') ?? 0);
        if ($networkId > 0) {
            $networkIds[] = $networkId;
        }
        if ($posId > 0) {
            if ($networkId > 0) {
                $posTargets[] = ['network_id' => $networkId, 'pos_id' => $posId];
            } elseif ($posTargets === []) {
                $posTargets[] = ['network_id' => 0, 'pos_id' => $posId];
            }
        }

        $ids = $this->positiveIds($request->input('ids', $request->input('package_ids', [])));
        if ($ids !== [] && str_contains($routeName, 'batch_')) {
            if (str_starts_with($routeName, 'api.v1.voucher_package_discounts.')) {
                $records = VoucherPackageDiscount::withTrashed()->whereIn('discount_id', $ids)->get();
                if ($records->count() !== count($ids)) {
                    return false;
                }
                foreach ($records as $record) {
                    $networkIds[] = (int) $record->network_id;
                    if ($record->pos_id !== null) {
                        $posTargets[] = [
                            'network_id' => (int) $record->network_id,
                            'pos_id' => (int) $record->pos_id,
                        ];
                    }
                }
            } else {
                $records = VoucherPackage::withTrashed()->whereIn('package_id', $ids)->get();
                if ($records->count() !== count($ids)) {
                    return false;
                }
                foreach ($records as $record) {
                    $networkIds[] = (int) $record->network_id;
                }
            }
        }

        $networkIds = $this->positiveIds($networkIds);

        if ($networkIds === [] && $posTargets === []) {
            return $permission === $viewPermission
                && $this->tenantPermissions->allowsAnyOperational($user, $permission);
        }

        foreach ($networkIds as $networkId) {
            if ($permission === $viewPermission
                && $this->tenantPermissions->allowsNetworkThroughNetworkOrLinkedPos($user, $networkId, $permission)
            ) {
                continue;
            }

            if ($this->tenantPermissions->allowsNetwork($user, $networkId, $permission)) {
                continue;
            }

            $linkedAuthorizedPos = $permission === $viewPermission
                && collect($posTargets)->contains(function (array $target) use ($user, $networkId, $permission): bool {
                    return (int) $target['pos_id'] > 0
                        && $this->tenantPermissions->allowsPos($user, (int) $target['pos_id'], $permission)
                        && (int) $target['network_id'] === $networkId
                        && \App\Models\NetworkPos::query()
                            ->where('network_id', $networkId)
                            ->where('pos_id', (int) $target['pos_id'])
                            ->exists();
                });

            if (! $linkedAuthorizedPos) {
                return false;
            }
        }

        foreach ($posTargets as $target) {
            if ((int) $target['network_id'] > 0) {
                if (! \App\Models\NetworkPos::query()
                    ->where('network_id', (int) $target['network_id'])
                    ->where('pos_id', (int) $target['pos_id'])
                    ->exists()) {
                    return false;
                }

                if ($permission === $viewPermission
                    && ! $this->tenantPermissions->allowsPos($user, (int) $target['pos_id'], $permission)
                    && ! $this->tenantPermissions->allowsNetwork($user, (int) $target['network_id'], $permission)
                ) {
                    return false;
                }
            } elseif (! $this->tenantPermissions->allowsPos($user, (int) $target['pos_id'], $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $ids
     * @return array<int, int>
     */
    private function positiveIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            $ids = [$ids];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
    }
}
