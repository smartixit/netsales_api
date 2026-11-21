<?php

namespace App\Policies;

use App\Models\SaleInvoice;
use App\Models\User;
use App\Services\TenantPermissionService;
use Illuminate\Auth\Access\HandlesAuthorization;

class SaleInvoicePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return app(TenantPermissionService::class)->allowsAnyOperational($user, 'sales.view');
    }

    public function view(User $user, SaleInvoice $invoice): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsNetwork($user, (int) $invoice->network_id, 'sales.view')
            || $permissions->allowsPos($user, (int) $invoice->pos_id, 'sales.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SaleInvoice $invoice): bool
    {
        $permissions = app(TenantPermissionService::class);

        return $permissions->allowsNetwork($user, (int) $invoice->network_id, 'sales.edit')
            || $permissions->allowsPos($user, (int) $invoice->pos_id, 'sales.edit');
    }

    public function delete(User $user, SaleInvoice $invoice): bool
    {
        return false;
    }
}
