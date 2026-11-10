<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;

use App\Enums\{BillingInvoiceStatus, BillingSubscriptionStatus};
use App\Models\{BillingDunningAttempt, BillingInvoice, BillingSubscription, BillingSubscriptionAlert, BillingSubscriptionEvent};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingDunningService
{
    public function scheduleForInvoice(BillingInvoice $invoice): void
    {
        $invoice->loadMissing('subscription');
        $schedule = (array) ($invoice->subscription?->dunning_policy_snapshot['reminder_schedule_days'] ?? [0, 1, 3, 7]);
        $dueDate = $invoice->due_on?->toDateString() ?? now('Asia/Aden')->toDateString();
        $reminderBase = CarbonImmutable::parse($dueDate, 'Asia/Aden')->setTime(9, 0);
        foreach ($schedule as $index => $day) {
            BillingDunningAttempt::query()->firstOrCreate(
                [
                    'invoice_id' => $invoice->invoice_id,
                    'attempt_number' => $index + 1,
                ],
                [
                    'status' => 'PENDING',
                    'channel' => 'OUTBOX',
                    'scheduled_at' => $reminderBase->addDays(max(0, (int) $day)),
                ]
            );
        }
    }

    public function scheduleSubscriptionAlerts(BillingSubscription $subscription): void
    {
        if (! $subscription->current_period_end || ! $subscription->dunning_policy_version_id) return;
        foreach ((array) ($subscription->dunning_policy_snapshot['pre_renewal_alert_days'] ?? []) as $days) {
            BillingSubscriptionAlert::query()->firstOrCreate([
                'billing_subscription_id' => $subscription->billing_subscription_id,
                'period_end' => $subscription->current_period_end,
                'alert_days' => (int) $days,
            ], [
                'dunning_policy_version_id' => $subscription->dunning_policy_version_id,
                'scheduled_at' => $subscription->current_period_end->copy()->subDays((int) $days),
                'status' => 'PENDING',
            ]);
        }
    }

    public function processDueAlerts(int $limit = 100): int
    {
        $ids = BillingSubscriptionAlert::query()->where('status', 'PENDING')->where('scheduled_at', '<=', now('Asia/Aden'))
            ->orderBy('scheduled_at')->limit(min(max($limit, 1), 500))->pluck('subscription_alert_id');
        $processed = 0;
        foreach ($ids as $id) {
            $done = DB::transaction(function () use ($id): bool {
                $alert = BillingSubscriptionAlert::query()->lockForUpdate()->find($id);
                if (! $alert || $alert->status !== 'PENDING') return false;
                $subscription = BillingSubscription::query()->with('billingAccount.network')->lockForUpdate()->find($alert->billing_subscription_id);
                if (! $subscription
                    || ! in_array($subscription->status, [BillingSubscriptionStatus::ACTIVE, BillingSubscriptionStatus::TRIALING], true)
                    || $subscription->cancel_at_period_end
                    || ! $subscription->current_period_end
                    || $subscription->current_period_end->lte(now('Asia/Aden'))
                    || ! $subscription->current_period_end->equalTo($alert->period_end)) {
                    $alert->status = 'CANCELLED'; $alert->save(); return false;
                }
                $uuid = (string) Str::uuid();
                DB::table('outbox_messages')->insert([
                    'message_uuid' => $uuid, 'aggregate_type' => 'BILLING_SUBSCRIPTION',
                    'aggregate_id' => $subscription->billing_subscription_id,
                    'event_type' => 'billing.subscription.renewal_reminder',
                    'payload' => json_encode([
                        'billing_subscription_id' => $subscription->billing_subscription_id,
                        'network_id' => $subscription->billingAccount->network_id,
                        'owner_id' => $subscription->billingAccount->network->owner_id,
                        'period_end' => $alert->period_end->toDateTimeString(),
                        'days_remaining' => $alert->alert_days,
                    ], JSON_THROW_ON_ERROR),
                    'status' => 'PENDING', 'retry_count' => 0, 'max_retries' => 5,
                    'created_at' => now('Asia/Aden'), 'updated_at' => now('Asia/Aden'),
                ]);
                $alert->status = 'QUEUED'; $alert->queued_at = now('Asia/Aden'); $alert->message_uuid = $uuid; $alert->save();
                return true;
            }, 3);
            if ($done) $processed++;
        }
        return $processed;
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
                    BillingInvoiceStatus::CREDITED,
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

                $policy = (array) $subscription->dunning_policy_snapshot;
                if (! ($policy['auto_suspend_after_grace'] ?? true)) {
                    return false;
                }

                $oldStatus = $subscription->status->value;
                $isIncomplete = $subscription->status === BillingSubscriptionStatus::INCOMPLETE;
                $targetStatus = $isIncomplete
                    ? BillingSubscriptionStatus::EXPIRED
                    : BillingSubscriptionStatus::SUSPENDED;

                $subscription->status = $targetStatus;
                if ($isIncomplete) {
                    $subscription->ended_at = now('Asia/Aden');
                } else {
                    $subscription->paused_at = now('Asia/Aden');
                }
                $subscription->save();

                BillingSubscriptionEvent::query()->create([
                    'billing_subscription_id' => $subscription->billing_subscription_id,
                    'event_type' => $isIncomplete
                        ? 'INITIAL_SUBSCRIPTION_EXPIRED_FOR_NON_PAYMENT'
                        : 'SUBSCRIPTION_SUSPENDED_FOR_NON_PAYMENT',
                    'from_status' => $oldStatus,
                    'to_status' => $targetStatus->value,
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
        $alerts = $this->processDueAlerts($limit);
        $suspended = $this->suspendExpiredGracePeriods($limit);

        return ['reminders_queued' => $queued, 'renewal_alerts_queued' => $alerts, 'subscriptions_suspended' => $suspended];
    }
}
