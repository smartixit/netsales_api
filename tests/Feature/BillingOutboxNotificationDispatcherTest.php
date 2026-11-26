<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Jobs\SendFcmNotificationJob;
use App\Models\Notification;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Services\Billing\BillingOutboxNotificationDispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingOutboxNotificationDispatcherTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lifecycle_event_uses_send_direct_and_is_idempotent(): void
    {
        Queue::fake();

        $user = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Billing Outbox Notification Recipient',
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_email' => 'billing-outbox-'.Str::lower(Str::random(10)).'@example.test',
            'user_password' => bcrypt('Secret@123'),
            'status' => UserStatus::ACTIVE,
        ]);
        $message = OutboxMessage::query()->create([
            'message_uuid' => (string) Str::uuid(),
            'aggregate_type' => 'BILLING_INVOICE',
            'aggregate_id' => 77,
            'event_type' => 'billing.lifecycle',
            'payload' => [
                'user_id' => (int) $user->user_id,
                'notification_type' => NotificationType::BILLING_INVOICE_ISSUED->value,
                'title' => '🧾 تم إصدار فاتورة اشتراك جديدة',
                'message' => '🧾 تم إصدار الفاتورة INV-TEST-77 ويمكنك مراجعة تفاصيلها.',
                'entity_type' => 'BILLING_INVOICE',
                'entity_id' => 77,
                'extra_data' => ['invoice_id' => 77],
            ],
            'status' => 'PROCESSING',
            'retry_count' => 0,
            'max_retries' => 5,
            'created_at' => now('Asia/Aden'),
            'updated_at' => now('Asia/Aden'),
        ]);

        $dispatcher = app(BillingOutboxNotificationDispatcher::class);

        $this->assertTrue($dispatcher->dispatch($message));
        $this->assertTrue($dispatcher->dispatch($message));

        $notification = Notification::query()
            ->where('user_id', $user->user_id)
            ->where('notification_type', NotificationType::BILLING_INVOICE_ISSUED->value)
            ->where('notification_title', '🧾 تم إصدار فاتورة اشتراك جديدة')
            ->firstOrFail();

        $this->assertStringStartsWith('🧾', (string) $notification->notification_message);

        $this->assertSame(1, Notification::query()
            ->where('user_id', $user->user_id)
            ->where('notification_type', NotificationType::BILLING_INVOICE_ISSUED->value)
            ->where('notification_title', '🧾 تم إصدار فاتورة اشتراك جديدة')
            ->count());

        $this->assertSame(1, \DB::table('outbox_notification_deliveries')
            ->where('message_uuid', $message->message_uuid)
            ->count());

        $this->assertSame($notification->notification_id, (int) \DB::table('outbox_notification_deliveries')
            ->where('message_uuid', $message->message_uuid)
            ->value('notification_id'));

        Queue::assertPushed(SendFcmNotificationJob::class, 1);
    }

    public function test_invalid_lifecycle_payload_is_not_delivered(): void
    {
        Queue::fake();

        $message = OutboxMessage::query()->create([
            'message_uuid' => (string) Str::uuid(),
            'aggregate_type' => 'BILLING_INVOICE',
            'aggregate_id' => 78,
            'event_type' => 'billing.lifecycle',
            'payload' => [
                'user_id' => 0,
                'notification_type' => NotificationType::BILLING_INVOICE_ISSUED->value,
                'title' => '🧾 فاتورة جديدة',
                'message' => 'رسالة اختبار',
                'entity_type' => 'BILLING_INVOICE',
                'entity_id' => 78,
            ],
            'status' => 'PROCESSING',
            'retry_count' => 0,
            'max_retries' => 5,
            'created_at' => now('Asia/Aden'),
            'updated_at' => now('Asia/Aden'),
        ]);

        $this->assertFalse(app(BillingOutboxNotificationDispatcher::class)->dispatch($message));
        $this->assertDatabaseMissing('outbox_notification_deliveries', [
            'message_uuid' => $message->message_uuid,
        ]);
    }
}
