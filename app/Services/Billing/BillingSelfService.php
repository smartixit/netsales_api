<?php

namespace App\Services\Billing;

use App\Models\{BillingAccount, BillingInvoice, BillingPayment, BillingSubscription, User};
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class BillingSelfService
{
    public function __construct(
        private BillingAccountService $accounts,
        private BillingCatalogService $catalog,
        private BillingAuthorizationService $authorization
    ) {}

    public function accounts(int $networkId, User $actor): Collection
    {
        return $this->accounts->accountsForNetwork($networkId, $actor);
    }

    public function createAccount(int $networkId, int $currencyId, User $actor): BillingAccount
    {
        return $this->accounts->createForNetwork($networkId, $currencyId, $actor);
    }

    public function account(int $accountId, User $actor): BillingAccount
    {
        return $this->accounts->ownedAccount($accountId, $actor);
    }

    public function plans(int $accountId, array $filters, User $actor): Collection|CursorPaginator
    {
        $account = $this->accounts->ownedAccount($accountId, $actor);

        return $this->catalog->publicPlansForCurrency((int) $account->default_currency_id, $filters);
    }

    public function subscriptions(int $accountId, array $filters, User $actor): Collection|CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingSubscription::query()
            ->with(['currency', 'plan', 'planVersion', 'price'])
            ->where('billing_account_id', $accountId);

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('billing_subscription_id')->get();
        }

        return $query->orderByDesc('billing_subscription_id')->cursorPaginate($perPage);
    }

    public function invoices(int $accountId, array $filters, User $actor): Collection|CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingInvoice::query()
            ->with(['currency', 'lines.currency', 'subscription.plan'])
            ->where('billing_account_id', $accountId);

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('invoice_id')->get();
        }

        return $query->orderByDesc('invoice_id')->cursorPaginate($perPage);
    }

    public function invoice(int $invoiceId, User $actor): BillingInvoice
    {
        $invoice = BillingInvoice::query()
            ->with(['billingAccount', 'currency', 'lines.currency', 'subscription.plan'])
            ->findOrFail($invoiceId);

        $this->authorization->assertBillingAccountAccess($actor, $invoice->billingAccount);

        return $invoice;
    }

    public function payments(int $accountId, array $filters, User $actor): Collection|CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingPayment::query()
            ->with(['currency', 'paymentMethod', 'proofs', 'invoice'])
            ->where('billing_account_id', $accountId);

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('payment_id')->get();
        }

        return $query->orderByDesc('payment_id')->cursorPaginate($perPage);
    }

    public function assertInvoiceAccess(int $invoiceId, User $actor): BillingInvoice
    {
        $invoice = BillingInvoice::query()
            ->with('billingAccount')
            ->findOrFail($invoiceId);

        $this->authorization->assertBillingAccountAccess($actor, $invoice->billingAccount);

        return $invoice;
    }

    public function assertAccountAccess(int $accountId, User $actor): BillingAccount
    {
        return $this->accounts->ownedAccount($accountId, $actor);
    }
}
