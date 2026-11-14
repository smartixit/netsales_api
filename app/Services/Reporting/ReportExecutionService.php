<?php

namespace App\Services\Reporting;

use App\Http\Resources\API\V1\Expense\ExpenseResource;
use App\Http\Resources\API\V1\SaleInvoice\SaleInvoiceResource;
use App\Http\Resources\API\V1\Billing\{BillingCreditNoteResource, BillingInvoiceResource, BillingPaymentResource, BillingRefundResource, BillingSubscriptionResource, LedgerJournalEntryResource};
use App\Models\{BillingAccount, BillingCreditNote, BillingInvoice, BillingPayment, BillingRefund, BillingSubscription, Expense, LedgerJournalEntry, ReportRun, SaleInvoice, User};
use App\Services\Billing\{BillingDashboardService, BillingSelfService};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ReportExecutionService
{
    public function __construct(
        private ReportDefinitionRegistry $definitions,
        private BillingSelfService $billingSelfService,
        private BillingDashboardService $billingDashboard,
        private ReportAuthorizationService $authorization
    ) {}

    /**
     * Build a bounded, immutable data snapshot. This service does not derive
     * financial aggregates; it serializes domain-service or model/resource data.
     *
     * @return array{payload: array<string, mixed>, records_count: int, is_truncated: bool}
     */
    public function execute(ReportRun $run): array
    {
        $definition = $this->definitions->get($run->scope_type, $run->report_key);
        $actor = User::query()->findOrFail((int) $run->requested_by);
        $this->authorization->authorizeRun($actor, $run);
        $filters = $run->filters ?? [];
        $limit = (int) $definition['max_records'];

        if ($run->scope_type === 'NETWORK') {
            return $this->executeNetwork($run, $definition, $filters, $limit);
        }

        return $this->executePlatform($run, $definition, $filters, $limit);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $filters
     * @return array{payload: array<string, mixed>, records_count: int, is_truncated: bool}
     */
    private function executeNetwork(
        ReportRun $run,
        array $definition,
        array $filters,
        int $limit
    ): array {
        $networkId = (int) $run->network_id;
        $request = Request::create('/api/v1/reports/snapshot', 'GET');

        return match ($run->report_key) {
            'sales-invoices' => $this->collectionSnapshot(
                $run,
                $definition,
                SaleInvoice::query()
                    ->with(['network', 'pos', 'request', 'createdBy', 'items'])
                    ->where('network_id', $networkId)
                    ->when(! empty($filters['from']), fn (Builder $query) => $query->whereDate('invoice_date', '>=', $filters['from']))
                    ->when(! empty($filters['to']), fn (Builder $query) => $query->whereDate('invoice_date', '<=', $filters['to']))
                    ->when(! empty($filters['payment_status']), fn (Builder $query) => $query->where('payment_status', strtoupper((string) $filters['payment_status'])))
                    ->when(! empty($filters['sale_type']), fn (Builder $query) => $query->where('sale_type', strtoupper((string) $filters['sale_type'])))
                    ->orderBy('invoice_id'),
                $limit,
                fn ($model): array => (new SaleInvoiceResource($model))->toArray($request),
                [
                    'financial_currency_context' => 'UNSPECIFIED_BY_SALE_INVOICE_API',
                    'financial_aggregation' => 'NOT_PERFORMED',
                ]
            ),
            'operating-expenses' => $this->collectionSnapshot(
                $run,
                $definition,
                Expense::query()
                    ->with(['network', 'createdBy', 'items', 'attachments'])
                    ->where('network_id', $networkId)
                    ->when(! empty($filters['from']), fn (Builder $query) => $query->whereDate('expense_date', '>=', $filters['from']))
                    ->when(! empty($filters['to']), fn (Builder $query) => $query->whereDate('expense_date', '<=', $filters['to']))
                    ->orderBy('expense_id'),
                $limit,
                fn ($model): array => (new ExpenseResource($model))->toArray($request),
                [
                    'financial_currency_context' => 'UNSPECIFIED_BY_EXPENSE_API',
                    'financial_aggregation' => 'NOT_PERFORMED',
                ]
            ),
            'billing-account-statement' => $this->networkBillingStatement(
                $run,
                $definition,
                $filters
            ),
            default => throw new \InvalidArgumentException('Unsupported network report definition.'),
        };
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $filters
     * @return array{payload: array<string, mixed>, records_count: int, is_truncated: bool}
     */
    private function networkBillingStatement(
        ReportRun $run,
        array $definition,
        array $filters
    ): array {
        $accountId = (int) $filters['billing_account_id'];
        $account = BillingAccount::query()
            ->where('billing_account_id', $accountId)
            ->where('network_id', (int) $run->network_id)
            ->firstOrFail();

        // The report worker re-authorizes the requesting actor against the
        // persisted run scope before reaching this method.
        $statement = $this->billingSelfService->statementForAuthorizedAccount($account, $filters);

        return $this->snapshot($run, $definition, $statement, count($statement['balances_by_currency'] ?? []), false, [
            'currency_aggregation' => 'SEPARATE_ONLY_NO_CROSS_CURRENCY_TOTAL',
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $filters
     * @return array{payload: array<string, mixed>, records_count: int, is_truncated: bool}
     */
    private function executePlatform(
        ReportRun $run,
        array $definition,
        array $filters,
        int $limit
    ): array {
        $request = Request::create('/api/v1/platform/reports/snapshot', 'GET');

        return match ($run->report_key) {
            'billing-overview' => $this->platformOverviewSnapshot(
                $run,
                $definition,
                $filters,
                $request
            ),
            'billing-subscriptions' => $this->collectionSnapshot(
                $run,
                $definition,
                BillingSubscription::query()
                    ->with(['billingAccount.network', 'currency', 'plan', 'planVersion', 'price'])
                    ->when(! empty($filters['network_id']), fn (Builder $query) => $query->whereHas('billingAccount', fn (Builder $account) => $account->where('network_id', (int) $filters['network_id']))
                    )
                    ->when(! empty($filters['billing_account_id']), fn (Builder $query) => $query->where('billing_account_id', (int) $filters['billing_account_id']))
                    ->when(! empty($filters['currency_id']), fn (Builder $query) => $query->where('currency_id', (int) $filters['currency_id']))
                    ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', strtoupper((string) $filters['status'])))
                    ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                        $term = trim((string) $filters['search']);
                        $query->whereHas('billingAccount', fn (Builder $account) => $account
                            ->where('account_number', 'like', "%{$term}%")
                            ->orWhere('legal_name', 'like', "%{$term}%"));
                    })
                    ->orderBy('billing_subscription_id'),
                $limit,
                fn ($model): array => (new BillingSubscriptionResource($model))->toArray($request),
                ['currency_aggregation' => 'NOT_PERFORMED']
            ),
            'billing-invoices' => $this->collectionSnapshot(
                $run,
                $definition,
                BillingInvoice::query()
                    ->with(['billingAccount.network', 'currency', 'subscription.plan', 'lines.currency', 'creditNotes.currency'])
                    ->when(! empty($filters['network_id']), fn (Builder $query) => $query->whereHas('billingAccount', fn (Builder $account) => $account->where('network_id', (int) $filters['network_id']))
                    )
                    ->when(! empty($filters['billing_account_id']), fn (Builder $query) => $query->where('billing_account_id', (int) $filters['billing_account_id']))
                    ->when(! empty($filters['currency_id']), fn (Builder $query) => $query->where('currency_id', (int) $filters['currency_id']))
                    ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', strtoupper((string) $filters['status'])))
                    ->when(! empty($filters['from']), fn (Builder $query) => $query->whereDate('issued_on', '>=', $filters['from']))
                    ->when(! empty($filters['to']), fn (Builder $query) => $query->whereDate('issued_on', '<=', $filters['to']))
                    ->when(! empty($filters['search']), fn (Builder $query) => $query->where('invoice_number', 'like', '%'.trim((string) $filters['search']).'%'))
                    ->orderBy('invoice_id'),
                $limit,
                fn ($model): array => (new BillingInvoiceResource($model))->toArray($request),
                ['currency_aggregation' => 'SEPARATE_PER_RECORD']
            ),
            'billing-payments' => $this->collectionSnapshot(
                $run,
                $definition,
                BillingPayment::query()
                    ->with(['invoice.billingAccount.network', 'currency', 'paymentMethod', 'treasuryAccount', 'proofs'])
                    ->when(! empty($filters['network_id']), fn (Builder $query) => $query->whereIn('billing_account_id', function ($subquery) use ($filters): void {
                        $subquery->select('billing_account_id')->from('billing_accounts')->where('network_id', (int) $filters['network_id']);
                    }))
                    ->when(! empty($filters['billing_account_id']), fn (Builder $query) => $query->where('billing_account_id', (int) $filters['billing_account_id']))
                    ->when(! empty($filters['invoice_id']), fn (Builder $query) => $query->where('invoice_id', (int) $filters['invoice_id']))
                    ->when(! empty($filters['currency_id']), fn (Builder $query) => $query->where('currency_id', (int) $filters['currency_id']))
                    ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', strtoupper((string) $filters['status'])))
                    ->when(! empty($filters['from']), fn (Builder $query) => $query->whereDate('received_at', '>=', $filters['from']))
                    ->when(! empty($filters['to']), fn (Builder $query) => $query->whereDate('received_at', '<=', $filters['to']))
                    ->orderBy('payment_id'),
                $limit,
                fn ($model): array => (new BillingPaymentResource($model))->toArray($request),
                ['currency_aggregation' => 'SEPARATE_PER_RECORD']
            ),
            'billing-credit-notes' => $this->collectionSnapshot(
                $run,
                $definition,
                BillingCreditNote::query()
                    ->with(['billingAccount.network', 'currency', 'invoice', 'lines', 'refunds.currency'])
                    ->when(! empty($filters['network_id']), fn (Builder $query) => $query->whereHas('billingAccount', fn (Builder $account) => $account->where('network_id', (int) $filters['network_id'])))
                    ->when(! empty($filters['billing_account_id']), fn (Builder $query) => $query->where('billing_account_id', (int) $filters['billing_account_id']))
                    ->when(! empty($filters['currency_id']), fn (Builder $query) => $query->where('currency_id', (int) $filters['currency_id']))
                    ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', strtoupper((string) $filters['status'])))
                    ->orderBy('credit_note_id'),
                $limit,
                fn ($model): array => (new BillingCreditNoteResource($model))->toArray($request),
                ['currency_aggregation' => 'SEPARATE_PER_RECORD']
            ),
            'billing-refunds' => $this->collectionSnapshot(
                $run,
                $definition,
                BillingRefund::query()
                    ->with(['currency', 'payment.invoice.billingAccount.network', 'creditNote'])
                    ->when(! empty($filters['network_id']), fn (Builder $query) => $query->whereHas('payment.billingAccount', fn (Builder $account) => $account->where('network_id', (int) $filters['network_id'])))
                    ->when(! empty($filters['billing_account_id']), fn (Builder $query) => $query->whereHas('payment', fn (Builder $payment) => $payment->where('billing_account_id', (int) $filters['billing_account_id'])))
                    ->when(! empty($filters['currency_id']), fn (Builder $query) => $query->where('currency_id', (int) $filters['currency_id']))
                    ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', strtoupper((string) $filters['status'])))
                    ->orderBy('refund_id'),
                $limit,
                fn ($model): array => (new BillingRefundResource($model))->toArray($request),
                ['currency_aggregation' => 'SEPARATE_PER_RECORD']
            ),
            'billing-journal-entries' => $this->collectionSnapshot(
                $run,
                $definition,
                LedgerJournalEntry::query()
                    ->with(['currency', 'lines.account'])
                    ->when(! empty($filters['currency_id']), fn (Builder $query) => $query->where('currency_id', (int) $filters['currency_id']))
                    ->when(! empty($filters['source_type']), fn (Builder $query) => $query->where('source_type', strtoupper((string) $filters['source_type'])))
                    ->when(! empty($filters['from_date']), fn (Builder $query) => $query->whereDate('entry_date', '>=', $filters['from_date']))
                    ->when(! empty($filters['to_date']), fn (Builder $query) => $query->whereDate('entry_date', '<=', $filters['to_date']))
                    ->orderBy('journal_entry_id'),
                $limit,
                fn ($model): array => (new LedgerJournalEntryResource($model))->toArray($request),
                ['currency_aggregation' => 'SEPARATE_PER_ENTRY']
            ),
            default => throw new \InvalidArgumentException('Unsupported platform report definition.'),
        };
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $filters
     * @return array{payload: array<string, mixed>, records_count: int, is_truncated: bool}
     */
    private function platformOverviewSnapshot(
        ReportRun $run,
        array $definition,
        array $filters,
        Request $request
    ): array {
        $data = $this->billingDashboard->overview(
            isset($filters['currency_id']) ? (int) $filters['currency_id'] : null
        );
        /** @var list<BillingPayment> $queue */
        $queue = array_values((array) ($data['payment_review_queue'] ?? []));
        $data['payment_review_queue'] = array_map(
            static fn (BillingPayment $payment): array => (new BillingPaymentResource($payment))->toArray($request),
            $queue
        );

        return $this->snapshot(
            $run,
            $definition,
            $data,
            1,
            false,
            ['currency_aggregation' => 'SEPARATE_BY_CURRENCY']
        );
    }

    /**
     * @template TModel of Model
     * @param array<string, mixed> $definition
     * @param Builder<TModel> $query
     * @param callable(TModel): array<string, mixed> $serialize
     * @param array<string, mixed> $extraMetadata
     * @return array{payload: array<string, mixed>, records_count: int, is_truncated: bool}
     */
    private function collectionSnapshot(
        ReportRun $run,
        array $definition,
        Builder $query,
        int $limit,
        callable $serialize,
        array $extraMetadata = []
    ): array {
        $models = $query->limit($limit + 1)->get();
        $truncated = $models->count() > $limit;
        $rows = $models->take($limit)->map($serialize)->values()->all();

        return $this->snapshot(
            $run,
            $definition,
            $rows,
            count($rows),
            $truncated,
            $extraMetadata
        );
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $extraMetadata
     * @return array{payload: array<string, mixed>, records_count: int, is_truncated: bool}
     */
    private function snapshot(
        ReportRun $run,
        array $definition,
        mixed $data,
        int $recordsCount,
        bool $truncated,
        array $extraMetadata = []
    ): array {
        return [
            'payload' => [
                'schema_version' => 1,
                'report' => [
                    'key' => $definition['key'],
                    'title' => $definition['title'],
                    'scope' => $definition['scope'],
                    'definition_version' => $definition['definition_version'],
                ],
                'run' => [
                    'uuid' => $run->report_run_uuid,
                    'requested_at' => $run->queued_at->toDateTimeString(),
                    'generated_at' => CarbonImmutable::now('Asia/Aden')->toDateTimeString(),
                    'timezone' => 'Asia/Aden',
                ],
                'criteria' => $run->filters ?? [],
                'metadata' => [
                    'records_count' => $recordsCount,
                    'is_truncated' => $truncated,
                    'max_records' => (int) $definition['max_records'],
                    ...$extraMetadata,
                ],
                'data' => $data,
            ],
            'records_count' => $recordsCount,
            'is_truncated' => $truncated,
        ];
    }
}
