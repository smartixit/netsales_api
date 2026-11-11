<?php

namespace App\Services\Billing;

use App\Models\{BillingAccount, BillingCreditNote, BillingInvoice, BillingPayment, BillingRefund, BillingSubscription, Currency, User};
use App\Services\Billing\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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

    public function creditNotes(int $accountId, array $filters, User $actor): Collection|CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingCreditNote::query()
            ->with(['currency', 'invoice', 'lines', 'refunds.currency'])
            ->where('billing_account_id', $accountId);

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('credit_note_id')->get();
        }

        return $query->orderByDesc('credit_note_id')->cursorPaginate($perPage);
    }

    public function refunds(int $accountId, array $filters, User $actor): Collection|CursorPaginator
    {
        $this->accounts->ownedAccount($accountId, $actor);

        $query = BillingRefund::query()
            ->with(['currency', 'payment.invoice', 'creditNote'])
            ->whereHas('payment', fn ($payment) => $payment->where('billing_account_id', $accountId));

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('refund_id')->get();
        }

        return $query->orderByDesc('refund_id')->cursorPaginate($perPage);
    }

    public function subscriptionEvents(int $subscriptionId, User $actor): CursorPaginator
    {
        $subscription = BillingSubscription::query()
            ->with('billingAccount')
            ->findOrFail($subscriptionId);

        $this->authorization->assertBillingAccountAccess($actor, $subscription->billingAccount);

        return $subscription->events()
            ->orderByDesc('occurred_at')
            ->orderByDesc('event_id')
            ->cursorPaginate(30);
    }

    /**
     * Returns a receivables activity statement. All totals are computed on the
     * server in integer minor units and remain separated by currency.
     *
     * The statement deliberately excludes unverified payment attempts and
     * draft/void invoices. Refunds do not reduce receivables: refundable credit
     * notes are separate from the amount applied to an invoice receivable.
     */
    public function statement(int $accountId, array $filters, User $actor): array
    {
        $account = $this->accounts->ownedAccount($accountId, $actor);

        return $this->statementForAuthorizedAccount($account, $filters);
    }

    /**
     * For an already-authorized asynchronous report execution. The caller must
     * establish both account ownership and current network scope before calling.
     */
    public function statementForAuthorizedAccount(BillingAccount $account, array $filters): array
    {
        $accountId = (int) $account->billing_account_id;
        $from = CarbonImmutable::parse($filters['from'] ?? CarbonImmutable::now('Asia/Aden')->startOfMonth())
            ->startOfDay();
        $to = CarbonImmutable::parse($filters['to'] ?? CarbonImmutable::now('Asia/Aden'))
            ->endOfDay();

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'from' => ['يجب أن يكون تاريخ بداية التقرير قبل تاريخ نهايته أو مساوياً له.'],
            ]);
        }

        $currencyId = isset($filters['currency_id']) ? (int) $filters['currency_id'] : null;
        if ($currencyId !== null) {
            Currency::query()->findOrFail($currencyId);
        }

        $invoiceBase = DB::table('billing_invoices')
            ->where('billing_account_id', $accountId)
            ->whereNotNull('finalized_at')
            ->where('status', '!=', 'VOID')
            ->when($currencyId !== null, fn ($builder) => $builder->where('currency_id', $currencyId));

        $invoiceOpening = (clone $invoiceBase)->whereDate('issued_on', '<', $from->toDateString())
            ->select('currency_id', DB::raw('SUM(total_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('currency_id')->get()->keyBy('currency_id');
        $invoicePeriod = (clone $invoiceBase)->whereDate('issued_on', '>=', $from->toDateString())
            ->whereDate('issued_on', '<=', $to->toDateString())
            ->select('currency_id', DB::raw('SUM(total_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('currency_id')->get()->keyBy('currency_id');

        $paymentBase = DB::table('billing_payments')
            ->where('billing_account_id', $accountId)
            ->whereIn('status', ['VERIFIED', 'PARTIALLY_REFUNDED', 'REFUNDED'])
            ->whereNotNull('verified_at')
            ->when($currencyId !== null, fn ($builder) => $builder->where('currency_id', $currencyId));
        $paymentOpening = (clone $paymentBase)->whereDate('verified_at', '<', $from->toDateString())
            ->select('currency_id', DB::raw('SUM(amount_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('currency_id')->get()->keyBy('currency_id');
        $paymentPeriod = (clone $paymentBase)->whereDate('verified_at', '>=', $from->toDateString())
            ->whereDate('verified_at', '<=', $to->toDateString())
            ->select('currency_id', DB::raw('SUM(amount_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('currency_id')->get()->keyBy('currency_id');

        $creditBase = DB::table('billing_credit_notes')
            ->where('billing_account_id', $accountId)
            ->whereNotNull('issued_at')
            ->when($currencyId !== null, fn ($builder) => $builder->where('currency_id', $currencyId));
        $creditOpening = (clone $creditBase)->whereDate('issued_at', '<', $from->toDateString())
            ->select('currency_id', DB::raw('SUM(applied_to_receivable_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('currency_id')->get()->keyBy('currency_id');
        $creditPeriod = (clone $creditBase)->whereDate('issued_at', '>=', $from->toDateString())
            ->whereDate('issued_at', '<=', $to->toDateString())
            ->select('currency_id', DB::raw('SUM(applied_to_receivable_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('currency_id')->get()->keyBy('currency_id');

        // Refundable credit notes create a customer-credit liability.
        // Completed refunds consume that liability and are recognized by processing date.
        $refundableNoteBase = DB::table('billing_credit_notes')
            ->where('billing_account_id', $accountId)
            ->whereNotNull('issued_at')
            ->when($currencyId !== null, fn ($builder) => $builder->where('currency_id', $currencyId));
        $refundableNoteOpening = (clone $refundableNoteBase)
            ->whereDate('issued_at', '<', $from->toDateString())
            ->select('currency_id', DB::raw('SUM(refundable_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('currency_id')->get()->keyBy('currency_id');
        $refundableNotePeriod = (clone $refundableNoteBase)
            ->whereDate('issued_at', '>=', $from->toDateString())
            ->whereDate('issued_at', '<=', $to->toDateString())
            ->select('currency_id', DB::raw('SUM(refundable_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('currency_id')->get()->keyBy('currency_id');

        $refundBase = DB::table('billing_refunds')
            ->join('billing_credit_notes', 'billing_credit_notes.credit_note_id', '=', 'billing_refunds.credit_note_id')
            ->where('billing_credit_notes.billing_account_id', $accountId)
            ->where('billing_refunds.status', 'COMPLETED')
            ->whereNotNull('billing_refunds.processed_at')
            ->when($currencyId !== null, fn ($builder) => $builder->where('billing_refunds.currency_id', $currencyId));
        $refundOpening = (clone $refundBase)
            ->whereDate('billing_refunds.processed_at', '<', $from->toDateString())
            ->select('billing_refunds.currency_id', DB::raw('SUM(billing_refunds.amount_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('billing_refunds.currency_id')->get()->keyBy('currency_id');
        $refundPeriod = (clone $refundBase)
            ->whereDate('billing_refunds.processed_at', '>=', $from->toDateString())
            ->whereDate('billing_refunds.processed_at', '<=', $to->toDateString())
            ->select('billing_refunds.currency_id', DB::raw('SUM(billing_refunds.amount_minor) as amount_minor'), DB::raw('COUNT(*) as item_count'))
            ->groupBy('billing_refunds.currency_id')->get()->keyBy('currency_id');

        $currencyIds = collect()
            ->merge($invoiceOpening->keys())->merge($invoicePeriod->keys())
            ->merge($paymentOpening->keys())->merge($paymentPeriod->keys())
            ->merge($creditOpening->keys())->merge($creditPeriod->keys())
            ->merge($refundableNoteOpening->keys())->merge($refundableNotePeriod->keys())
            ->merge($refundOpening->keys())->merge($refundPeriod->keys())
            ->unique()->map(fn ($id): int => (int) $id)->values();

        $currencies = Currency::query()->whereIn('currency_id', $currencyIds)->orderBy('code')->get();
        $balances = $currencies->map(function (Currency $currency) use (
            $invoiceOpening, $invoicePeriod, $paymentOpening, $paymentPeriod,
            $creditOpening, $creditPeriod, $refundableNoteOpening,
            $refundableNotePeriod, $refundOpening, $refundPeriod
        ): array {
            $id = $currency->currency_id;
            $openingNet = (int) ($invoiceOpening->get($id)->amount_minor ?? 0)
                - (int) ($paymentOpening->get($id)->amount_minor ?? 0)
                - (int) ($creditOpening->get($id)->amount_minor ?? 0);
            $billed = (int) ($invoicePeriod->get($id)->amount_minor ?? 0);
            $paid = (int) ($paymentPeriod->get($id)->amount_minor ?? 0);
            $credited = (int) ($creditPeriod->get($id)->amount_minor ?? 0);
            $closingNet = $openingNet + $billed - $paid - $credited;

            // A negative net receivable is a customer credit balance, not a
            // negative accounts-receivable figure. Keep both concepts explicit.
            $openingReceivable = max(0, $openingNet);
            $openingOverpaymentCredit = max(0, -$openingNet);
            $closingReceivable = max(0, $closingNet);
            $closingOverpaymentCredit = max(0, -$closingNet);

            $openingRefundableCredit = (int) ($refundableNoteOpening->get($id)->amount_minor ?? 0)
                - (int) ($refundOpening->get($id)->amount_minor ?? 0);
            $periodRefundableCredit = (int) ($refundableNotePeriod->get($id)->amount_minor ?? 0)
                - (int) ($refundPeriod->get($id)->amount_minor ?? 0);
            $closingRefundableCredit = max(0, $openingRefundableCredit + $periodRefundableCredit);

            $openingCustomerCredit = $openingOverpaymentCredit + max(0, $openingRefundableCredit);
            $closingCustomerCredit = $closingOverpaymentCredit + $closingRefundableCredit;

            $money = fn (int $minor): string => (new Money($minor, (string) $currency->code))
                ->toDecimalString((int) $currency->decimal_places);

            return [
                'currency' => [
                    'currency_id' => (int) $id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimal_places' => (int) $currency->decimal_places,
                ],
                'opening_receivable_minor' => $openingReceivable,
                'opening_receivable' => $money($openingReceivable),
                'opening_customer_credit_minor' => $openingCustomerCredit,
                'opening_customer_credit' => $money($openingCustomerCredit),
                'invoiced_minor' => $billed,
                'invoiced' => $money($billed),
                'verified_payments_minor' => $paid,
                'verified_payments' => $money($paid),
                'credits_applied_minor' => $credited,
                'credits_applied' => $money($credited),
                'closing_receivable_minor' => $closingReceivable,
                'closing_receivable' => $money($closingReceivable),
                'closing_customer_credit_minor' => $closingCustomerCredit,
                'closing_customer_credit' => $money($closingCustomerCredit),
                'counts' => [
                    'opening_invoices' => (int) ($invoiceOpening->get($id)->item_count ?? 0),
                    'invoices_issued' => (int) ($invoicePeriod->get($id)->item_count ?? 0),
                    'payments_verified' => (int) ($paymentPeriod->get($id)->item_count ?? 0),
                    'credit_notes_issued' => (int) ($creditPeriod->get($id)->item_count ?? 0),
                ],
            ];
        })->values()->all();

        return [
            'billing_account_id' => (int) $account->billing_account_id,
            'account_number' => (string) $account->account_number,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'timezone' => 'Asia/Aden',
            ],
            'balances_by_currency' => $balances,
            'currency_aggregation' => 'SEPARATE_ONLY_NO_CROSS_CURRENCY_TOTAL',
        ];
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
