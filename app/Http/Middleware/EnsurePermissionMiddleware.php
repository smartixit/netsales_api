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

        if ($user->isSuperAdmin() || $this->tenantPermissions->allowsPlatform($user, $permission)) {
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
                $ids = $this->positiveIds($request->input('ids', []));
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

                $authorized = in_array($permission, ['requests.approve', 'requests.reject'], true)
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
                $ids = $this->positiveIds($request->input('ids', []));
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

            // Detail reads are authorized by SaleInvoicePolicy in the controller.
            if ($invoice !== null && $permission === 'sales.view') {
                return $next($request);
            }

            $networkId = (int) ($request->input('network_id') ?? $request->query('network_id') ?? $invoice?->network_id ?? 0);
            $posId = (int) ($request->input('pos_id') ?? $request->query('pos_id') ?? $invoice?->pos_id ?? 0);

            if ($invoice === null && $request->has('ids')) {
                $ids = $this->positiveIds($request->input('ids', []));
                $invoices = SaleInvoice::withTrashed()->whereIn('invoice_id', $ids)->get();

                $authorized = $ids !== [] && $invoices->count() === count($ids)
                    && $invoices->every(fn (SaleInvoice $record): bool =>
                        $this->tenantPermissions->allowsNetwork($user, (int) $record->network_id, $permission)
                        || $this->tenantPermissions->allowsPos($user, (int) $record->pos_id, $permission));
            } else {
                $authorized = ($networkId > 0 && $this->tenantPermissions->allowsNetwork($user, $networkId, $permission))
                    || ($posId > 0 && $this->tenantPermissions->allowsPos($user, $posId, $permission));

                if ($networkId <= 0 && $posId <= 0 && $invoice === null && $permission === 'sales.view') {
                    $authorized = $this->tenantPermissions->allowsAnyOperational($user, $permission);
                }
            }

            if (! $authorized) {
                throw new AuthorizationException('عذراً، لا تملك هذه الصلاحية داخل الشبكة أو نقطة البيع المحددة.');
            }

            return $next($request);
        }

        $scopedAuthorization = $this->authorizeScopedPermission($request, $user, $permission);
        if ($scopedAuthorization !== null) {
            if (! $scopedAuthorization) {
                throw new AuthorizationException('عذراً، لا تملك هذه الصلاحية داخل مساحة العمل أو الكيان المحدد.');
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

        if (str_starts_with($permission, 'voucher_packages.')) {
            return $this->authorizeVoucherPackagePermission($request, $user, $permission);
        }

        return null;
    }

    private function authorizeNetworkPermission(Request $request, User $user, string $permission): bool
    {
        $networkIds = [];
        $posIds = [];
        $routeName = (string) ($request->route()?->getName() ?? '');

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
                    $posIds[] = (int) $challenge->pos_id;
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
        $networkIds = [];
        $posIds = [];
        $routeId = (int) ($request->route('id') ?? 0);

        if ($routeId > 0) {
            $wallet = PosWallet::withTrashed()->find($routeId);
            if (! $wallet) {
                return false;
            }
            $networkIds[] = (int) $wallet->network_id;
            $posIds[] = (int) $wallet->pos_id;
        }

        $networkId = (int) ($request->route('networkId') ?? $request->input('network_id') ?? $request->query('network_id') ?? 0);
        $posId = (int) ($request->route('posId') ?? $request->input('pos_id') ?? $request->query('pos_id') ?? 0);
        if ($networkId > 0) {
            $networkIds[] = $networkId;
        }
        if ($posId > 0) {
            $posIds[] = $posId;
        }

        if ($request->has('ids') && str_contains((string) ($request->route()?->getName() ?? ''), 'batch_')) {
            $ids = $this->positiveIds($request->input('ids', []));
            $wallets = PosWallet::withTrashed()->whereIn('wallet_id', $ids)->get();
            if ($ids === [] || $wallets->count() !== count($ids)) {
                return false;
            }
            foreach ($wallets as $wallet) {
                $networkIds[] = (int) $wallet->network_id;
                $posIds[] = (int) $wallet->pos_id;
            }
        }

        $networkIds = $this->positiveIds($networkIds);
        $posIds = $this->positiveIds($posIds);

        if ($networkIds === [] && $posIds === []) {
            return $permission === 'pos_wallets.view'
                && $this->tenantPermissions->allowsAnyOperational($user, $permission);
        }

        foreach ($networkIds as $networkId) {
            $networkAllowed = $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
            $posAllowed = in_array($permission, ['pos_wallets.view'], true)
                && $posIds !== []
                && collect($posIds)->contains(fn (int $id): bool =>
                    $this->tenantPermissions->allowsPos($user, $id, $permission));

            if (! $networkAllowed && ! $posAllowed) {
                return false;
            }
        }

        return true;
    }

    private function authorizeVoucherPackagePermission(Request $request, User $user, string $permission): bool
    {
        $networkIds = [];
        $posIds = [];
        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName === 'api.v1.networks.voucher_packages') {
            $routeNetworkId = (int) ($request->route('id') ?? 0);
            if ($routeNetworkId > 0) {
                $networkIds[] = $routeNetworkId;
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
                    $posIds[] = (int) $discount->pos_id;
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
            $posIds[] = $posId;
        }

        $idsField = $request->input('ids', $request->input('package_ids', []));
        $ids = $this->positiveIds($idsField);
        if ($ids !== [] && str_contains($routeName, 'batch_')) {
            if (str_starts_with($routeName, 'api.v1.voucher_package_discounts.')) {
                $records = VoucherPackageDiscount::withTrashed()->whereIn('discount_id', $ids)->get();
                if ($records->count() !== count($ids)) {
                    return false;
                }
                foreach ($records as $record) {
                    $networkIds[] = (int) $record->network_id;
                    if ($record->pos_id !== null) {
                        $posIds[] = (int) $record->pos_id;
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
        $posIds = $this->positiveIds($posIds);

        if ($networkIds === [] && $posIds === []) {
            return $permission === 'voucher_packages.view'
                && $this->tenantPermissions->allowsAnyOperational($user, $permission);
        }

        foreach ($networkIds as $networkId) {
            $networkAllowed = $this->tenantPermissions->allowsNetwork($user, $networkId, $permission);
            $posAllowed = $permission === 'voucher_packages.view'
                && $posIds !== []
                && collect($posIds)->contains(fn (int $id): bool =>
                    $this->tenantPermissions->allowsPos($user, $id, $permission));

            if (! $networkAllowed && ! $posAllowed) {
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
