<?php

namespace App\Services\Billing;

use App\Enums\BillingPaymentStatus;
use App\Models\{Currency, TreasuryAccount};
use Illuminate\Support\Facades\DB;

class BillingReconciliationService
{
    private const CONTROL_ACCOUNTS = [
        '1100' => ['key' => 'accounts_receivable', 'label' => 'الذمم المدينة', 'normal' => 'DEBIT'],
        '2100' => ['key' => 'deferred_revenue', 'label' => 'الإيرادات المؤجلة', 'normal' => 'CREDIT'],
        '2200' => ['key' => 'customer_credit_liability', 'label' => 'أرصدة العملاء الدائنة', 'normal' => 'CREDIT'],
        '2300' => ['key' => 'tax_payable', 'label' => 'الضرائب المستحقة', 'normal' => 'CREDIT'],
    ];

    public function report(?int $currencyId = null): array
    {
        $currencies = Currency::query()
            ->when($currencyId !== null, fn ($query) => $query->where('currency_id', $currencyId))
            ->orderBy('code')
            ->get();

        return [
            'generated_at' => now('Asia/Aden')->toIso8601String(),
            'timezone' => 'Asia/Aden',
            'currency_filter' => $currencyId,
            'currencies' => $currencies
                ->map(fn (Currency $currency): array => $this->reconcileCurrency($currency))
                ->values()
                ->all(),
        ];
    }

    private function reconcileCurrency(Currency $currency): array
    {
        $ledgerBalances = $this->controlLedgerBalances((int) $currency->currency_id);
        $subledgerBalances = [
            'accounts_receivable' => (int) DB::table('billing_invoices')
                ->where('currency_id', $currency->currency_id)
                ->whereNotNull('finalized_at')
                ->where('status', '!=', 'VOID')
                ->sum('remaining_minor'),
            'deferred_revenue' => (int) DB::table('billing_revenue_recognition_schedules')
                ->where('currency_id', $currency->currency_id)
                ->whereIn('status', ['HOLD', 'PENDING', 'ACTIVE'])
                ->selectRaw('COALESCE(SUM(total_minor - recognized_minor), 0) AS balance_minor')
                ->value('balance_minor'),
            'customer_credit_liability' => (int) DB::table('billing_credit_notes')
                ->where('currency_id', $currency->currency_id)
                ->whereIn('status', ['ISSUED', 'PARTIALLY_REFUNDED'])
                ->selectRaw('COALESCE(SUM(refundable_minor - refunded_minor), 0) AS balance_minor')
                ->value('balance_minor'),
        ];

        $issuedTaxMinor = (int) DB::table('billing_invoices')
            ->where('currency_id', $currency->currency_id)
            ->whereNotNull('finalized_at')
            ->where('status', '!=', 'VOID')
            ->sum('tax_minor');
        $creditedTaxMinor = (int) DB::table('billing_credit_notes')
            ->where('currency_id', $currency->currency_id)
            ->whereIn('status', ['ISSUED', 'PARTIALLY_REFUNDED', 'REFUNDED'])
            ->sum('tax_minor');
        $subledgerBalances['tax_payable'] = $issuedTaxMinor - $creditedTaxMinor;

        $checks = [];
        foreach (self::CONTROL_ACCOUNTS as $code => $definition) {
            $glBalance = (int) ($ledgerBalances[$code] ?? 0);
            $subledger = (int) ($subledgerBalances[$definition['key']] ?? 0);
            $checks[] = $this->check(
                $definition['key'],
                $definition['label'],
                $code,
                $glBalance,
                $subledger
            );
        }

        $treasury = $this->reconcileTreasuryAccounts((int) $currency->currency_id);
        array_push($checks, ...$treasury['checks']);

        $trialBalance = $this->trialBalance((int) $currency->currency_id);
        $checks[] = $this->check(
            'trial_balance',
            'توازن ميزان المراجعة',
            null,
            $trialBalance['debit_minor'],
            $trialBalance['credit_minor']
        );

        $hasMismatch = collect($checks)->contains(fn (array $check): bool => $check['status'] !== 'MATCH');
        $hasWarnings = $treasury['unassigned_payment_minor'] > 0
            || $treasury['unassigned_refund_minor'] > 0
            || $treasury['missing_ledger_account_count'] > 0;

        return [
            'currency' => [
                'currency_id' => (int) $currency->currency_id,
                'code' => $currency->code,
                'name' => $currency->name,
                'decimal_places' => (int) $currency->decimal_places,
            ],
            'status' => $hasMismatch || $hasWarnings ? 'MISMATCH' : 'MATCH',
            'checks' => $checks,
            'treasury_accounts' => $treasury['accounts'],
            'warnings' => [
                'verified_payments_without_treasury_minor' => $treasury['unassigned_payment_minor'],
                'completed_refunds_without_treasury_minor' => $treasury['unassigned_refund_minor'],
                'treasury_accounts_without_ledger_link' => $treasury['missing_ledger_account_count'],
            ],
        ];
    }

    private function controlLedgerBalances(int $currencyId): array
    {
        $rows = DB::table('ledger_journal_lines as lines')
            ->join('ledger_journal_entries as entries', 'entries.journal_entry_id', '=', 'lines.journal_entry_id')
            ->join('ledger_accounts as accounts', 'accounts.ledger_account_id', '=', 'lines.ledger_account_id')
            ->where('entries.status', 'POSTED')
            ->where('lines.currency_id', $currencyId)
            ->whereIn('accounts.account_code', array_keys(self::CONTROL_ACCOUNTS))
            ->groupBy('accounts.account_code', 'accounts.normal_balance')
            ->selectRaw('accounts.account_code, accounts.normal_balance, SUM(lines.debit_minor) AS debit_minor, SUM(lines.credit_minor) AS credit_minor')
            ->get();

        $balances = [];
        foreach ($rows as $row) {
            $debit = (int) $row->debit_minor;
            $credit = (int) $row->credit_minor;
            $balances[$row->account_code] = $row->normal_balance === 'DEBIT'
                ? $debit - $credit
                : $credit - $debit;
        }

        return $balances;
    }

    private function reconcileTreasuryAccounts(int $currencyId): array
    {
        $payments = DB::table('billing_payments')
            ->where('currency_id', $currencyId)
            ->whereIn('status', [
                BillingPaymentStatus::VERIFIED->value,
                BillingPaymentStatus::PARTIALLY_REFUNDED->value,
                BillingPaymentStatus::REFUNDED->value,
            ])
            ->selectRaw('treasury_account_id, SUM(amount_minor) AS amount_minor')
            ->groupBy('treasury_account_id')
            ->get()
            ->keyBy('treasury_account_id');

        $refunds = DB::table('billing_refunds')
            ->where('currency_id', $currencyId)
            ->where('status', 'COMPLETED')
            ->selectRaw('treasury_account_id, SUM(amount_minor) AS amount_minor')
            ->groupBy('treasury_account_id')
            ->get()
            ->keyBy('treasury_account_id');

        $unassignedPayments = (int) DB::table('billing_payments')
            ->where('currency_id', $currencyId)
            ->whereNull('treasury_account_id')
            ->whereIn('status', [
                BillingPaymentStatus::VERIFIED->value,
                BillingPaymentStatus::PARTIALLY_REFUNDED->value,
                BillingPaymentStatus::REFUNDED->value,
            ])
            ->sum('amount_minor');

        $unassignedRefunds = (int) DB::table('billing_refunds')
            ->where('currency_id', $currencyId)
            ->whereNull('treasury_account_id')
            ->where('status', 'COMPLETED')
            ->sum('amount_minor');

        $accounts = TreasuryAccount::query()
            ->with(['currency', 'ledgerAccount'])
            ->where('currency_id', $currencyId)
            ->orderBy('treasury_account_id')
            ->get();

        $checks = [];
        $items = [];
        $missingLedgerAccountCount = 0;

        foreach ($accounts as $account) {
            $accountId = (int) $account->treasury_account_id;
            $collected = (int) ($payments->get($accountId)?->amount_minor ?? 0);
            $refunded = (int) ($refunds->get($accountId)?->amount_minor ?? 0);
            $subledger = $collected - $refunded;
            $ledger = 0;

            if ($account->ledger_account_id) {
                $ledger = (int) DB::table('ledger_journal_lines as lines')
                    ->join('ledger_journal_entries as entries', 'entries.journal_entry_id', '=', 'lines.journal_entry_id')
                    ->where('entries.status', 'POSTED')
                    ->where('lines.ledger_account_id', $account->ledger_account_id)
                    ->where('lines.currency_id', $currencyId)
                    ->selectRaw('COALESCE(SUM(CAST(lines.debit_minor AS SIGNED) - CAST(lines.credit_minor AS SIGNED)), 0) AS balance_minor')
                    ->value('balance_minor');
            } else {
                $missingLedgerAccountCount++;
            }

            $key = 'treasury_account_'.$accountId;
            $check = $this->check(
                $key,
                'مطابقة الخزينة: '.$account->name,
                $account->ledgerAccount?->account_code,
                $ledger,
                $subledger
            );
            $checks[] = $check;
            $items[] = [
                'treasury_account_id' => $accountId,
                'name' => $account->name,
                'account_code' => $account->account_code,
                'status' => $account->status,
                'currency_id' => $currencyId,
                'ledger_balance_minor' => $ledger,
                'verified_payments_minor' => $collected,
                'completed_refunds_minor' => $refunded,
                'subledger_balance_minor' => $subledger,
                'difference_minor' => $check['difference_minor'],
                'reconciliation_status' => $check['status'],
            ];
        }

        return [
            'checks' => $checks,
            'accounts' => $items,
            'unassigned_payment_minor' => $unassignedPayments,
            'unassigned_refund_minor' => $unassignedRefunds,
            'missing_ledger_account_count' => $missingLedgerAccountCount,
        ];
    }

    private function trialBalance(int $currencyId): array
    {
        $row = DB::table('ledger_journal_lines as lines')
            ->join('ledger_journal_entries as entries', 'entries.journal_entry_id', '=', 'lines.journal_entry_id')
            ->where('entries.status', 'POSTED')
            ->where('lines.currency_id', $currencyId)
            ->selectRaw('COALESCE(SUM(lines.debit_minor), 0) AS debit_minor, COALESCE(SUM(lines.credit_minor), 0) AS credit_minor')
            ->first();

        return [
            'debit_minor' => (int) ($row->debit_minor ?? 0),
            'credit_minor' => (int) ($row->credit_minor ?? 0),
        ];
    }

    private function check(
        string $key,
        string $label,
        ?string $accountCode,
        int $ledgerBalance,
        int $subledgerBalance
    ): array {
        $difference = $ledgerBalance - $subledgerBalance;

        return [
            'key' => $key,
            'label' => $label,
            'account_code' => $accountCode,
            'ledger_balance_minor' => $ledgerBalance,
            'subledger_balance_minor' => $subledgerBalance,
            'difference_minor' => $difference,
            'status' => $difference === 0 ? 'MATCH' : 'MISMATCH',
        ];
    }
}
