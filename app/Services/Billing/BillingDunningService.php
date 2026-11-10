<?php

namespace App\Services\Billing;

use App\Enums\{BillingInvoiceStatus, BillingSubscriptionStatus};
use App\Models\{BillingDunningAttempt, BillingInvoice, BillingSubscription, BillingSubscriptionEvent};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingDunningService
{
    public function scheduleForInvoice(BillingInvoice $invoice): void
    {
        foreach ((array) config('billing.dunning_days', [0, 1, 3, 7]) as $index => $day) {
            BillingDunningAttempt::query()->firstOrCreate(
                [
                    'invoice_id' => $invoice->invoice_id,
                    'attempt_number' => $index + 1,
                ],
                [
                    'status' => 'PENDING',
                    'channel' => 'OUTBOX',
                    'scheduled_at' => now('Asia/Aden')->addDays((int) $day),
                ]
            );
        }
    }

    public function processDueAttempts(int $limit = 100): int
    {
        $attemptIds = BillingDunningAttempt::query()
            ->where('status', 'PENDING')
            ->where('scheduled_at', '<=', now('Asia/Aden'))
            ->orderBy('scheduled_at')
            ->limit(min(max($limit, 1), 500))
            ->pluck('dunning_attempt_id');

        $processed = 0;

        foreach ($attemptIds as $attemptId) {
            $done = DB::transaction(function () use ($attemptId): bool {
                $attempt = BillingDunningAttempt::query()
                    ->lockForUpdate()
                    ->find($attemptId);

                if (! $attempt || $attempt->status !== 'PENDING') {
                    return false;
                }

                $invoice = BillingInvoice::query()
                    ->lockForUpdate()
                    ->find($attempt->invoice_id);

                if (! $invoice || in_array($invoice->status, [
                    BillingInvoiceStatus::PAID,
                    BillingInvoiceStatus::VOID,
                    BillingInvoiceStatus::UNCOLLECTIBLE,
                ], true)) {
                    $attempt->status = 'CANCELLED';
                    $attempt->attempted_at = now('Asia/Aden');
                    $attempt->result_message = 'تم تجاوز التذكير لأن الفاتورة لم تعد مستحقة.';
                    $attempt->save();

                    return false;
                }

                $messageUuid = (string) Str::uuid();
                DB::table('outbox_messages')->insert([
                    'message_uuid' => $messageUuid,
                    'aggregate_type' => 'BILLING_INVOICE',
                    'aggregate_id' => $invoice->invoice_id,
                    'event_type' => 'billing.invoice.payment_reminder',
                    'payload' => json_encode([
                        'invoice_id' => $invoice->invoice_id,
                        'invoice_number' => $invoice->invoice_number,
                        'billing_account_id' => $invoice->billing_account_id,
                        'attempt_number' => $attempt->attempt_number,
                        'currency_id' => $invoice->currency_id,
                        'remaining_minor' => $invoice->remaining_minor,
                    ], JSON_THROW_ON_ERROR),
                    'status' => 'PENDING',
                    'retry_count' => 0,
                    'max_retries' => 5,
                    'created_at' => now('Asia/Aden'),
                    'updated_at' => now('Asia/Aden'),
                ]);

                $attempt->status = 'QUEUED';
                $attempt->attempted_at = now('Asia/Aden');
                $attempt->result_message = 'تم إدراج إشعار التذكير في صندوق الإرسال.';
                $attempt->save();

                return true;
            }, 3);

            if ($done) {
                $processed++;
            }
        }

        return $processed;
    }

    public function suspendExpiredGracePeriods(int $limit = 100): int
    {
        $subscriptions = BillingSubscription::query()
            ->whereIn('status', [BillingSubscriptionStatus::PAST_DUE, BillingSubscriptionStatus::INCOMPLETE])
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now('Asia/Aden'))
            ->orderBy('billing_subscription_id')
            ->limit(min(max($limit, 1), 500))
            ->pluck('billing_subscription_id');

        $suspended = 0;

        foreach ($subscriptions as $subscriptionId) {
            $changed = DB::transaction(function () use ($subscriptionId): bool {
                $subscription = BillingSubscription::query()
                    ->lockForUpdate()
                    ->find($subscriptionId);

                if (! $subscription
                    || ! in_array($subscription->status, [BillingSubscriptionStatus::PAST_DUE, BillingSubscriptionStatus::INCOMPLETE], true)
                    || ! $subscription->grace_period_ends_at
                    || $subscription->grace_period_ends_at->gt(now('Asia/Aden'))) {
                    return false;
                }

                $oldStatus = $subscription->status->value;
                $subscription->status = BillingSubscriptionStatus::SUSPENDED;
                $subscription->paused_at = now('Asia/Aden');
                $subscription->save();

                BillingSubscriptionEvent::query()->create([
                    'billing_subscription_id' => $subscription->billing_subscription_id,
                    'event_type' => 'SUBSCRIPTION_SUSPENDED_FOR_NON_PAYMENT',
                    'from_status' => $oldStatus,
                    'to_status' => BillingSubscriptionStatus::SUSPENDED->value,
                    'payload' => ['grace_period_ended_at' => $subscription->grace_period_ends_at?->toDateTimeString()],
                    'occurred_at' => now('Asia/Aden'),
                ]);

                return true;
            }, 3);

            if ($changed) {
                $suspended++;
            }
        }

        return $suspended;
    }

    public function process(int $limit = 100): array
    {
        $queued = $this->processDueAttempts($limit);
        $suspended = $this->suspendExpiredGracePeriods($limit);

        return ['reminders_queued' => $queued, 'subscriptions_suspended' => $suspended];
    }
}
