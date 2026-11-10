<?php

namespace App\Services\Billing;

use App\Enums\{BillingSubscriptionStatus, BillingInterval};
use App\Models\{BillingSubscription, BillingSubscriptionEvent};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingRenewalService
{
    public function __construct(
        private BillingInvoiceService $invoices,
        private BillingDunningService $dunning
    ) {}

    public function processDueSubscriptions(int $limit = 100): array
    {
        $ids = BillingSubscription::query()
            ->whereIn('status', [BillingSubscriptionStatus::ACTIVE, BillingSubscriptionStatus::TRIALING])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now('Asia/Aden'))
            ->orderBy('billing_subscription_id')
            ->limit(min(max($limit, 1), 500))
            ->pluck('billing_subscription_id');

        $renewed = 0;
        $cancelled = 0;

        foreach ($ids as $subscriptionId) {
            $result = DB::transaction(function () use ($subscriptionId): string {
                $subscription = BillingSubscription::query()
                    ->with(['price', 'planVersion.plan'])
                    ->lockForUpdate()
                    ->find($subscriptionId);

                if (! $subscription
                    || ! in_array($subscription->status, [BillingSubscriptionStatus::ACTIVE, BillingSubscriptionStatus::TRIALING], true)
                    || ! $subscription->current_period_end
                    || $subscription->current_period_end->gt(now('Asia/Aden'))) {
                    return 'SKIPPED';
                }

                if ($subscription->cancel_at_period_end) {
                    $oldStatus = $subscription->status->value;
                    $subscription->status = BillingSubscriptionStatus::CANCELED;
                    $subscription->cancel_at_period_end = false;
                    $subscription->canceled_at = now('Asia/Aden');
                    $subscription->ended_at = now('Asia/Aden');
                    $subscription->save();

                    $subscription->events()->create([
                        'event_type' => 'SUBSCRIPTION_CANCELED_AT_PERIOD_END',
                        'from_status' => $oldStatus,
                        'to_status' => BillingSubscriptionStatus::CANCELED->value,
                        'payload' => ['period_ended_at' => $subscription->current_period_end->toDateTimeString()],
                        'occurred_at' => now('Asia/Aden'),
                    ]);

                    return 'CANCELLED';
                }

                $start = CarbonImmutable::parse($subscription->current_period_end, 'Asia/Aden');
                $count = max(1, (int) $subscription->price->interval_count);
                $end = match ($subscription->price->billing_interval) {
                    BillingInterval::DAY => $start->addDays($count),
                    BillingInterval::WEEK => $start->addWeeks($count),
                    BillingInterval::MONTH => $start->addMonthsNoOverflow($count),
                    BillingInterval::YEAR => $start->addYearsNoOverflow($count),
                    BillingInterval::ONE_TIME => throw ValidationException::withMessages(['billing_interval' => ['لا يمكن تجديد سعر لمرة واحدة.']]),
                };

                $wasTrial = $subscription->status === BillingSubscriptionStatus::TRIALING;
                $oldStatus = $subscription->status->value;
                $invoice = $this->invoices->createForSubscription(
                    $subscription,
                    null,
                    $start->toDateString(),
                    $end->toDateString(),
                    $wasTrial
                );
                $invoice = $this->invoices->finalize((int) $invoice->invoice_id);

                $subscription->status = $wasTrial
                    ? BillingSubscriptionStatus::INCOMPLETE
                    : BillingSubscriptionStatus::PAST_DUE;
                $subscription->grace_period_ends_at = now('Asia/Aden')->addDays((int) config('billing.grace_period_days', 7));
                $subscription->save();

                $subscription->events()->create([
                    'event_type' => $wasTrial ? 'TRIAL_CONVERSION_INVOICE_ISSUED' : 'RENEWAL_INVOICE_ISSUED',
                    'from_status' => $oldStatus,
                    'to_status' => $subscription->status->value,
                    'payload' => [
                        'invoice_id' => $invoice->invoice_id,
                        'invoice_number' => $invoice->invoice_number,
                        'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(),
                        'grace_period_ends_at' => $subscription->grace_period_ends_at->toDateTimeString(),
                    ],
                    'occurred_at' => now('Asia/Aden'),
                ]);

                $this->dunning->scheduleForInvoice($invoice);

                return 'RENEWED';
            }, 3);

            if ($result === 'RENEWED') {
                $renewed++;
            } elseif ($result === 'CANCELLED') {
                $cancelled++;
            }
        }

        return ['renewal_invoices_created' => $renewed, 'subscriptions_cancelled' => $cancelled];
    }
}
