<?php

namespace App\Services\Billing;

use App\Models\{BillingAccount, BillingAccountContact, BillingCreditNote, BillingInvoice, BillingPayment, BillingRefund, BillingSubscription, BillingUsageAggregate, User};
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

    public function updateAccount(int $accountId, array $data, User $actor): BillingAccount
    {
        return $this->accounts->updateDetails($accountId, $data, $actor);
    }

    public function createContact(int $accountId, array $data, User $actor): BillingAccountContact
    {
        return $this->accounts->createContact($accountId, $data, $actor);
    }

    public function updateContact(int $accountId, int $contactId, array $data, User $actor): BillingAccountContact
    {
        return $this->accounts->updateContact($accountId, $contactId, $data, $actor);
    }

    public function deleteContact(int $accountId, int $contactId, User $actor): void
    {
        $this->accounts->deleteContact($accountId, $contactId, $actor);
    }

    public function plans(int $accountId, array $filters, User $actor): CursorPaginator
    {
        $account = $this->accounts->ownedAccount($accountId, $actor);

        return $this->catalog->publicPlansForCurrency((int) $account->default_currency_id, $filters);
    }

    public function subscriptions(int $accountId, array $filters, User $actor): CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingSubscription::query()
            ->with(['currency', 'plan', 'planVersion', 'price'])
            ->where('billing_account_id', $accountId);

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($matching) use ($like): void {
                $matching->where('status', 'like', $like)
                    ->orWhereRaw('CAST(billing_subscription_id AS CHAR) LIKE ?', [$like])
                    ->orWhereHas('plan', fn ($plan) => $plan
                        ->where('name', 'like', $like)
                        ->orWhere('plan_key', 'like', $like));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        return $query->orderByDesc('billing_subscription_id')->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    public function invoices(int $accountId, array $filters, User $actor): CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingInvoice::query()
            ->with(['currency', 'lines.currency', 'subscription.plan'])
            ->where('billing_account_id', $accountId);

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($matching) use ($like): void {
                $matching->where('invoice_number', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereRaw('CAST(invoice_id AS CHAR) LIKE ?', [$like])
                    ->orWhereHas('subscription.plan', fn ($plan) => $plan
                        ->where('name', 'like', $like)
                        ->orWhere('plan_key', 'like', $like));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        return $query->orderByDesc('invoice_id')->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    public function invoice(int $invoiceId, User $actor): BillingInvoice
    {
        $invoice = BillingInvoice::query()
            ->with(['billingAccount', 'currency', 'lines.currency', 'subscription.plan'])
            ->findOrFail($invoiceId);

        $this->authorization->assertBillingAccountAccess($actor, $invoice->billingAccount);

        return $invoice;
    }

    public function payments(int $accountId, array $filters, User $actor): CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingPayment::query()
            ->with(['currency', 'paymentMethod', 'proofs', 'invoice'])
            ->where('billing_account_id', $accountId);

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($matching) use ($like): void {
                $matching->where('payment_reference', 'like', $like)
                    ->orWhere('external_reference', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereRaw('CAST(payment_id AS CHAR) LIKE ?', [$like])
                    ->orWhereHas('invoice', fn ($invoice) => $invoice
                        ->where('invoice_number', 'like', $like));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        return $query->orderByDesc('payment_id')->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    public function creditNotes(int $accountId, array $filters, User $actor): CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingCreditNote::query()
            ->with(['currency', 'invoice', 'lines', 'refunds.currency'])
            ->where('billing_account_id', $accountId);

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($matching) use ($like): void {
                $matching->where('credit_note_number', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereRaw('CAST(credit_note_id AS CHAR) LIKE ?', [$like])
                    ->orWhereHas('invoice', fn ($invoice) => $invoice
                        ->where('invoice_number', 'like', $like));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        return $query->orderByDesc('credit_note_id')->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    public function refunds(int $accountId, array $filters, User $actor): CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingRefund::query()
            ->with(['currency', 'payment.invoice', 'creditNote'])
            ->whereHas('payment', fn ($payment) => $payment->where('billing_account_id', $accountId));

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($matching) use ($like): void {
                $matching->where('refund_reference', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereRaw('CAST(refund_id AS CHAR) LIKE ?', [$like])
                    ->orWhereHas('payment', fn ($payment) => $payment
                        ->where('payment_reference', 'like', $like)
                        ->orWhere('external_reference', 'like', $like));
            });
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        return $query->orderByDesc('refund_id')->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    public function subscription(int $subscriptionId, User $actor): BillingSubscription
    {
        $subscription = BillingSubscription::query()->with([
            'billingAccount.network', 'currency', 'plan', 'planVersion.features', 'price',
            'trialRedemption.policyVersion.policy', 'trialRedemption.adjustments',
            'items.currency',
        ])->findOrFail($subscriptionId);
        $this->authorization->assertBillingAccountAccess($actor, $subscription->billingAccount);
        return $subscription;
    }

    public function usage(int $subscriptionId, array $filters, User $actor): CursorPaginator
    {
        $subscription = BillingSubscription::query()->with('billingAccount')->findOrFail($subscriptionId);
        $this->authorization->assertBillingAccountAccess($actor, $subscription->billingAccount);
        $query = BillingUsageAggregate::query()->with(['meter', 'currency'])
            ->where('billing_subscription_id', $subscriptionId);
        if (! empty($filters['from_date'])) $query->where('period_end', '>=', $filters['from_date']);
        if (! empty($filters['to_date'])) $query->where('period_start', '<=', $filters['to_date']);
        return $query->orderByDesc('period_start')->orderByDesc('usage_aggregate_id')
            ->cursorPaginate(
                min(max((int) ($filters['per_page'] ?? 25), 1), 50),
                ['*'],
                'cursor',
                $filters['cursor'] ?? null
            );
    }

    public function subscriptionEvents(int $subscriptionId, array $filters, User $actor): CursorPaginator
    {
        $subscription = BillingSubscription::query()
            ->with('billingAccount')
            ->findOrFail($subscriptionId);

        $this->authorization->assertBillingAccountAccess($actor, $subscription->billingAccount);

        return $subscription->events()
            ->orderByDesc('occurred_at')
            ->orderByDesc('event_id')
            ->cursorPaginate(
                min(max((int) ($filters['per_page'] ?? 30), 1), 50),
                ['*'],
                'cursor',
                $filters['cursor'] ?? null
            );
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
