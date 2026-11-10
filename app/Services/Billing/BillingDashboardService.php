<?php

namespace App\Services\Billing;

use App\Enums\{BillingInvoiceStatus, BillingPaymentStatus, BillingSubscriptionStatus};
use App\Models\{BillingPayment, Currency};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BillingDashboardService
{
    public function overview(?int $currencyId = null): array
    {
        if ($currencyId !== null) {
            Currency::query()->findOrFail($currencyId);
        }

        $now = CarbonImmutable::now('Asia/Aden');
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();
        $today = $now->toDateString();

        $subscriptionCounts = DB::table('billing_subscriptions')
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->whereNull('deleted_at')
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $invoiceCounts = DB::table('billing_invoices')
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count): int => (int) $count)
            ->all();

        $pendingPayments = DB::table('billing_payments')
            ->select('currency_id', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(amount_minor) as amount_minor'))
            ->where('status', BillingPaymentStatus::PENDING_REVIEW->value)
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->groupBy('currency_id')
            ->get()
            ->keyBy('currency_id');

        $openInvoices = DB::table('billing_invoices')
            ->select('currency_id', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(remaining_minor) as amount_minor'))
            ->whereIn('status', [
                BillingInvoiceStatus::OPEN->value,
                BillingInvoiceStatus::PARTIALLY_PAID->value,
                BillingInvoiceStatus::PARTIALLY_CREDITED->value,
            ])
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->groupBy('currency_id')
            ->get()
            ->keyBy('currency_id');

        $overdueInvoices = DB::table('billing_invoices')
            ->select('currency_id', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(remaining_minor) as amount_minor'))
            ->whereIn('status', [
                BillingInvoiceStatus::OPEN->value,
                BillingInvoiceStatus::PARTIALLY_PAID->value,
            ])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', $today)
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->groupBy('currency_id')
            ->get()
            ->keyBy('currency_id');

        $monthlyCollections = DB::table('billing_payments')
            ->select('currency_id', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(amount_minor) as amount_minor'))
            ->where('status', BillingPaymentStatus::VERIFIED->value)
            ->whereBetween('verified_at', [$monthStart, $monthEnd])
            ->when($currencyId, fn ($query) => $query->where('currency_id', $currencyId))
            ->groupBy('currency_id')
            ->get()
            ->keyBy('currency_id');

        $currencyIds = collect($currencyId ? [$currencyId] : [])
            ->merge($pendingPayments->keys())
            ->merge($openInvoices->keys())
            ->merge($overdueInvoices->keys())
            ->merge($monthlyCollections->keys())
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values();

        $currencies = Currency::query()
            ->whereIn('currency_id', $currencyIds)
            ->orderBy('code')
            ->get()
            ->keyBy('currency_id');

        $financials = $currencies->map(function (Currency $currency) use (
            $pendingPayments,
            $openInvoices,
            $overdueInvoices,
            $monthlyCollections
        ): array {
            $pending = $pendingPayments->get($currency->currency_id);
            $open = $openInvoices->get($currency->currency_id);
            $overdue = $overdueInvoices->get($currency->currency_id);
            $collected = $monthlyCollections->get($currency->currency_id);

            return [
                'currency' => [
                    'currency_id' => (int) $currency->currency_id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimal_places' => (int) $currency->decimal_places,
                    'is_active' => (bool) $currency->is_active,
                ],
                'pending_payments' => [
                    'count' => (int) ($pending->item_count ?? 0),
                    'amount_minor' => (int) ($pending->amount_minor ?? 0),
                    'amount' => (new Money((int) ($pending->amount_minor ?? 0), (string) $currency->code))->toDecimalString((int) $currency->decimal_places),
                ],
                'open_invoices' => [
                    'count' => (int) ($open->item_count ?? 0),
                    'outstanding_minor' => (int) ($open->amount_minor ?? 0),
                    'outstanding' => (new Money((int) ($open->amount_minor ?? 0), (string) $currency->code))->toDecimalString((int) $currency->decimal_places),
                ],
                'overdue_invoices' => [
                    'count' => (int) ($overdue->item_count ?? 0),
                    'outstanding_minor' => (int) ($overdue->amount_minor ?? 0),
                    'outstanding' => (new Money((int) ($overdue->amount_minor ?? 0), (string) $currency->code))->toDecimalString((int) $currency->decimal_places),
                ],
                'verified_collections_this_month' => [
                    'count' => (int) ($collected->item_count ?? 0),
                    'amount_minor' => (int) ($collected->amount_minor ?? 0),
                    'amount' => (new Money((int) ($collected->amount_minor ?? 0), (string) $currency->code))->toDecimalString((int) $currency->decimal_places),
                ],
            ];
        })->values()->all();

        $reviewQueue = BillingPayment::query()
            ->with(['invoice.billingAccount.network', 'currency', 'paymentMethod', 'proofs'])
            ->where('status', BillingPaymentStatus::PENDING_REVIEW)
            ->when($currencyId, fn (Builder $query) => $query->where('currency_id', $currencyId))
            ->orderBy('received_at')
            ->limit(10)
            ->get();

        $activeSubscriptions = ($subscriptionCounts[BillingSubscriptionStatus::ACTIVE->value] ?? 0)
            + ($subscriptionCounts[BillingSubscriptionStatus::TRIALING->value] ?? 0);

        return [
            'period' => [
                'from' => $monthStart->toDateString(),
                'to' => $monthEnd->toDateString(),
                'as_of' => $now->toDateTimeString(),
                'timezone' => 'Asia/Aden',
            ],
            'counts' => [
                'billing_accounts' => (int) DB::table('billing_accounts')
                    ->whereNull('deleted_at')
                    ->when($currencyId, fn ($query) => $query->where('default_currency_id', $currencyId))
                    ->count(),
                'subscriptions' => [
                    'total' => array_sum($subscriptionCounts),
                    'active_and_trialing' => $activeSubscriptions,
                    'incomplete' => $subscriptionCounts[BillingSubscriptionStatus::INCOMPLETE->value] ?? 0,
                    'past_due' => $subscriptionCounts[BillingSubscriptionStatus::PAST_DUE->value] ?? 0,
                    'suspended' => $subscriptionCounts[BillingSubscriptionStatus::SUSPENDED->value] ?? 0,
                    'canceled' => $subscriptionCounts[BillingSubscriptionStatus::CANCELED->value] ?? 0,
                    'expired' => $subscriptionCounts[BillingSubscriptionStatus::EXPIRED->value] ?? 0,
                ],
                'invoices' => [
                    'total' => array_sum($invoiceCounts),
                    'draft' => $invoiceCounts[BillingInvoiceStatus::DRAFT->value] ?? 0,
                    'open' => $invoiceCounts[BillingInvoiceStatus::OPEN->value] ?? 0,
                    'partially_paid' => $invoiceCounts[BillingInvoiceStatus::PARTIALLY_PAID->value] ?? 0,
                    'partially_credited' => $invoiceCounts[BillingInvoiceStatus::PARTIALLY_CREDITED->value] ?? 0,
                    'credited' => $invoiceCounts[BillingInvoiceStatus::CREDITED->value] ?? 0,
                    'paid' => $invoiceCounts[BillingInvoiceStatus::PAID->value] ?? 0,
                    'uncollectible' => $invoiceCounts[BillingInvoiceStatus::UNCOLLECTIBLE->value] ?? 0,
                    'void' => $invoiceCounts[BillingInvoiceStatus::VOID->value] ?? 0,
                ],
            ],
            'financials_by_currency' => $financials,
            'payment_review_queue' => $reviewQueue,
        ];
    }
}
