<?php

namespace App\Services\Billing;

use App\Enums\BillingPlanVersionStatus;
use App\Models\{BillingAccount, BillingInvoice, BillingPayment, BillingPaymentMethod, BillingPlan, BillingSubscription, CurrencyConversion, CurrencyExchangeRate, LedgerJournalEntry};
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class BillingPlatformReadService
{
    public function accounts(array $filters = []): Collection|CursorPaginator
    {
        $query = BillingAccount::query()->with(['network', 'defaultCurrency', 'contacts']);

        if (! empty($filters['network_id'])) {
            $query->where('network_id', (int) $filters['network_id']);
        }
        if (! empty($filters['currency_id'])) {
            $query->where('default_currency_id', (int) $filters['currency_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        return $this->paginate($query, $filters, 'billing_account_id');
    }

    public function subscriptions(array $filters = []): Collection|CursorPaginator
    {
        $query = BillingSubscription::query()->with([
            'billingAccount.network',
            'currency',
            'plan',
            'planVersion',
            'price',
        ]);

        if (! empty($filters['network_id'])) {
            $query->whereHas('billingAccount', fn (Builder $builder) => $builder->where('network_id', (int) $filters['network_id']));
        }
        if (! empty($filters['billing_account_id'])) {
            $query->where('billing_account_id', (int) $filters['billing_account_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', (int) $filters['currency_id']);
        }
        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($term): void {
                if (ctype_digit($term)) {
                    $builder->orWhere('billing_subscription_id', (int) $term)
                        ->orWhere('billing_account_id', (int) $term);
                }
                $builder->orWhereHas('plan', fn (Builder $plan) => $plan
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('plan_key', 'like', "%{$term}%"))
                    ->orWhereHas('billingAccount', fn (Builder $account) => $account
                        ->where('account_number', 'like', "%{$term}%")
                        ->orWhere('legal_name', 'like', "%{$term}%")
                        ->orWhereHas('network', fn (Builder $network) => $network
                            ->where('network_name', 'like', "%{$term}%")));
            });
        }

        return $this->paginate($query, $filters, 'billing_subscription_id');
    }

    public function invoices(array $filters = []): Collection|CursorPaginator
    {
        $query = BillingInvoice::query()->with(['billingAccount.network', 'currency', 'subscription.plan', 'lines', 'creditNotes.currency']);

        if (! empty($filters['network_id'])) {
            $query->whereHas('billingAccount', fn (Builder $builder) => $builder->where('network_id', (int) $filters['network_id']));
        }
        if (! empty($filters['billing_account_id'])) {
            $query->where('billing_account_id', (int) $filters['billing_account_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', (int) $filters['currency_id']);
        }

        return $this->paginate($query, $filters, 'invoice_id');
    }

    public function payments(array $filters = []): Collection|CursorPaginator
    {
        $query = BillingPayment::query()->with([
            'invoice.billingAccount.network',
            'currency',
            'paymentMethod',
            'treasuryAccount',
            'proofs',
        ]);

        if (! empty($filters['network_id'])) {
            $query->whereHas('billingAccount', fn (Builder $builder) => $builder->where('network_id', (int) $filters['network_id']));
        }
        if (! empty($filters['billing_account_id'])) {
            $query->where('billing_account_id', (int) $filters['billing_account_id']);
        }
        if (! empty($filters['invoice_id'])) {
            $query->where('invoice_id', (int) $filters['invoice_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }
        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', (int) $filters['currency_id']);
        }

        return $this->paginate($query, $filters, 'payment_id');
    }

    public function journalEntries(array $filters = []): Collection|CursorPaginator
    {
        $query = LedgerJournalEntry::query()->with(['currency', 'lines.account']);

        if (! empty($filters['currency_id'])) {
            $query->where('currency_id', (int) $filters['currency_id']);
        }
        if (! empty($filters['source_type'])) {
            $query->where('source_type', strtoupper((string) $filters['source_type']));
        }
        if (! empty($filters['from_date'])) {
            $query->whereDate('entry_date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('entry_date', '<=', $filters['to_date']);
        }

        return $this->paginate($query, $filters, 'journal_entry_id');
    }

    public function paymentMethods(?int $currencyId = null): Collection
    {
        return BillingPaymentMethod::query()
            ->with(['currencies' => fn ($query) => $query->wherePivot('is_active', true)])
            ->where('is_active', true)
            ->when($currencyId, fn (Builder $query) => $query->whereHas('currencies', fn ($currencies) => $currencies
                ->where('currencies.currency_id', $currencyId)
                ->where('billing_payment_method_currencies.is_active', true)))
            ->orderBy('payment_method_id')
            ->get();
    }

    public function exchangeRates(array $filters = []): Collection|CursorPaginator
    {
        $query = CurrencyExchangeRate::query()->with(['baseCurrency', 'quoteCurrency']);

        if (! empty($filters['base_currency_id'])) {
            $query->where('base_currency_id', (int) $filters['base_currency_id']);
        }
        if (! empty($filters['quote_currency_id'])) {
            $query->where('quote_currency_id', (int) $filters['quote_currency_id']);
        }

        return $this->paginate($query, $filters, 'exchange_rate_id');
    }

    public function conversions(array $filters = []): Collection|CursorPaginator
    {
        $query = CurrencyConversion::query()->with(['fromCurrency', 'toCurrency', 'exchangeRate']);

        if (! empty($filters['from_currency_id'])) {
            $query->where('from_currency_id', (int) $filters['from_currency_id']);
        }
        if (! empty($filters['to_currency_id'])) {
            $query->where('to_currency_id', (int) $filters['to_currency_id']);
        }

        return $this->paginate($query, $filters, 'conversion_id');
    }

    private function paginate(Builder $query, array $filters, string $key): Collection|CursorPaginator
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($key === 'invoice_id') {
                $query->where(fn (Builder $builder) => $builder
                    ->where('invoice_number', 'like', "%{$search}%"));
            } elseif ($key === 'payment_id') {
                $query->where(fn (Builder $builder) => $builder
                    ->where('payment_reference', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%"));
            }
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy($key)->get();
        }

        return $query->orderByDesc($key)->cursorPaginate($perPage);
    }
}
