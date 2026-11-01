<?php

namespace App\Policies;

use App\Models\SaleInvoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SaleInvoicePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SaleInvoice $invoice): bool
    {
        // 1. Creator of the invoice
        if ((int) $invoice->created_by === (int) $user->user_id) {
            return true;
        }

        // 2. Network ownership / membership
        if ($user->networks()->where('networks.network_id', $invoice->network_id)->exists()) {
            return true;
        }
        if (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $invoice->network_id)->exists()) {
            return true;
        }

        // 3. POS ownership / membership
        if ($user->pos && (int) $user->pos->pos_id === (int) $invoice->pos_id) {
            return true;
        }
        if (method_exists($user, 'posUsers') && $user->posUsers()->where('pos_id', $invoice->pos_id)->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SaleInvoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function delete(User $user, SaleInvoice $invoice): bool
    {
        return false;
    }
}

