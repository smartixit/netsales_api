<?php

namespace App\Services\Billing;

use App\Enums\NotificationType;
use App\Models\{BillingInvoice, BillingSubscription, OutboxMessage};
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingOutboxNotificationDispatcher
{
    public function __construct(private NotificationService $notifications) {}

    public function dispatch(OutboxMessage $message): bool
    {
        $payload = (array) $message->payload;
        [$userId, $type, $title, $body, $extra] = match ($message->event_type) {
            'billing.invoice.payment_reminder' => $this->invoiceReminder($payload),
            'billing.subscription.renewal_reminder' => $this->renewalReminder($payload),
            default => [null, null, null, null, []],
        };

        if (! $userId) {
            return false;
        }

        return DB::transaction(function () use ($message, $userId, $type, $title, $body, $extra): bool {
            $inserted = DB::table('outbox_notification_deliveries')->insertOrIgnore([
                'message_uuid' => $message->message_uuid,
                'created_at' => now('Asia/Aden'),
                'updated_at' => now('Asia/Aden'),
            ]);

            if ($inserted === 0) {
                return true;
            }

            $notification = $this->notifications->sendDirect(
                userId: (int) $userId,
                type: $type,
                title: $title,
                message: $body,
                inApp: true,
                push: true,
                sms: false,
                email: false,
                extraData: $extra,
            );

            if (! $notification) {
                throw new RuntimeException('تعذر إنشاء إشعار الفوترة للمستلم المحدد.');
            }

            DB::table('outbox_notification_deliveries')
                ->where('message_uuid', $message->message_uuid)
                ->update([
                    'notification_id' => $notification->notification_id,
                    'delivered_at' => now('Asia/Aden'),
                    'updated_at' => now('Asia/Aden'),
                ]);

            return true;
        }, 3);
    }

    private function invoiceReminder(array $payload): array
    {
        $invoice = BillingInvoice::query()
            ->with('billingAccount.network.owner')
            ->find((int) ($payload['invoice_id'] ?? 0));

        $ownerId = $invoice?->billingAccount?->network?->owner_id;
        if (! $invoice || ! $ownerId || $invoice->status->value === 'PAID') {
            return [null, null, null, null, []];
        }

        return [
            (int) $ownerId,
            NotificationType::BILLING_INVOICE_PAYMENT_REMINDER,
            'تذكير بسداد فاتورة الاشتراك',
            'الفاتورة '.$invoice->invoice_number.' ما زالت مستحقة. يرجى مراجعة تفاصيل الفاتورة وإتمام السداد.',
            ['entity_type' => 'BILLING_INVOICE', 'entity_id' => $invoice->invoice_id, 'invoice_id' => $invoice->invoice_id],
        ];
    }

    private function renewalReminder(array $payload): array
    {
        $subscription = BillingSubscription::query()
            ->with('billingAccount.network.owner')
            ->find((int) ($payload['billing_subscription_id'] ?? 0));

        $ownerId = $subscription?->billingAccount?->network?->owner_id;
        if (! $subscription || ! $ownerId || ! in_array($subscription->status->value, ['ACTIVE', 'TRIALING'], true)) {
            return [null, null, null, null, []];
        }

        $days = (int) ($payload['days_remaining'] ?? 0);
        return [
            (int) $ownerId,
            NotificationType::BILLING_SUBSCRIPTION_RENEWAL_REMINDER,
            'اقتراب موعد تجديد الاشتراك',
            $days === 0
                ? 'تنتهي فترة اشتراكك اليوم. يرجى مراجعة خيارات التجديد.'
                : 'يتبقى '.$days.' يوم على انتهاء فترة اشتراكك.',
            ['entity_type' => 'BILLING_SUBSCRIPTION', 'entity_id' => $subscription->billing_subscription_id, 'billing_subscription_id' => $subscription->billing_subscription_id],
        ];
    }
}
