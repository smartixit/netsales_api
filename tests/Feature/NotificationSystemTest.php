<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\SmsMessageType;
use App\Jobs\SendEmailNotificationJob;
use App\Jobs\SendFcmNotificationJob;
use App\Jobs\SendSmsNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserSession;
use App\Services\NotificationService;
use App\Services\Support\SMSGateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Bypass-Encryption' => 'true',
        ];
    }

    public function test_can_create_notification_and_list(): void
    {
        $payload = [
            'user_id' => $this->user->user_id,
            'notification_type' => NotificationType::GENERAL_INFO->value,
            'notification_title' => 'إشعار اختبار جديد',
            'notification_message' => 'هذا محتوى الإشعار التجريبي للتأكد من المحرك.',
        ];

        $response = $this->postJson('/api/v1/notifications', $payload, $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notification_title', 'إشعار اختبار جديد');

        $notificationId = $response->json('data.notification_id');

        // Test List API
        $listResponse = $this->getJson('/api/v1/notifications', $this->headers);
        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Test Show API
        $showResponse = $this->getJson("/api/v1/notifications/{$notificationId}", $this->headers);
        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notification_id', $notificationId);
    }

    public function test_can_get_my_notifications_and_unread_count(): void
    {
        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        $service->sendDirect(
            (int) $this->user->user_id,
            NotificationType::GENERAL_INFO,
            'تنبيه رصيد',
            'تم إيداع مبلغ تجريبي في محفظتك.'
        );

        $service->sendDirect(
            (int) $this->user->user_id,
            NotificationType::GENERAL_INFO,
            'تنبيه أمان',
            'تم تسجيل دخول جديد من جهاز غير معروف.'
        );

        // Check My notifications
        $myResponse = $this->getJson('/api/v1/notifications/my', $this->headers);
        $myResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(2, count($myResponse->json('data')));

        // Check unread count
        $countResponse = $this->getJson('/api/v1/notifications/unread-count', $this->headers);
        $countResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(2, $countResponse->json('data.unread_count'));
    }

    public function test_can_mark_single_and_all_notifications_as_read(): void
    {
        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        $notif1 = $service->sendDirect(
            (int) $this->user->user_id,
            NotificationType::GENERAL_INFO,
            'إشعار مقروء فردي',
            'محتوى الإشعار الفردي.'
        );

        $notif2 = $service->sendDirect(
            (int) $this->user->user_id,
            NotificationType::GENERAL_INFO,
            'إشعار مقروء جماعي',
            'محتوى الإشعار الثاني.'
        );

        // Mark single as read
        $readResponse = $this->postJson("/api/v1/notifications/{$notif1->notification_id}/read", [], $this->headers);
        $readResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue(Notification::find($notif1->notification_id)->is_read);

        // Mark all as read
        $allReadResponse = $this->postJson('/api/v1/notifications/mark-all-read', [], $this->headers);
        $allReadResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue(Notification::find($notif2->notification_id)->is_read);
    }

    public function test_can_update_notification(): void
    {
        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        $notif = $service->sendDirect(
            (int) $this->user->user_id,
            NotificationType::GENERAL_INFO,
            'العنوان الأصلي',
            'النص الأصلي للإشعار.'
        );

        $updatePayload = [
            'notification_title' => 'العنوان المحدث',
            'notification_message' => 'النص المحدث للإشعار.',
            'notification_type' => NotificationType::GENERAL_INFO->value,
        ];

        $response = $this->putJson("/api/v1/notifications/{$notif->notification_id}", $updatePayload, $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.notification_title', 'العنوان المحدث')
            ->assertJsonPath('data.notification_type', NotificationType::GENERAL_INFO->value);
    }

    public function test_can_perform_soft_delete_restore_and_batch_operations(): void
    {
        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        $notif1 = $service->sendDirect((int) $this->user->user_id, NotificationType::GENERAL_INFO, 'إشعار 1', 'محتوى 1');
        $notif2 = $service->sendDirect((int) $this->user->user_id, NotificationType::GENERAL_INFO, 'إشعار 2', 'محتوى 2');
        $notif3 = $service->sendDirect((int) $this->user->user_id, NotificationType::GENERAL_INFO, 'إشعار 3', 'محتوى 3');

        // 1. Single delete & restore
        $deleteResponse = $this->deleteJson("/api/v1/notifications/{$notif1->notification_id}", [], $this->headers);
        $deleteResponse->assertStatus(200);
        $this->assertSoftDeleted('notifications', ['notification_id' => $notif1->notification_id]);

        $restoreResponse = $this->postJson("/api/v1/notifications/{$notif1->notification_id}/restore", [], $this->headers);
        $restoreResponse->assertStatus(200);
        $this->assertNotSoftDeleted('notifications', ['notification_id' => $notif1->notification_id]);

        // 2. Batch Delete
        $batchDeleteResponse = $this->postJson('/api/v1/notifications/batch-delete', [
            'ids' => [$notif1->notification_id, $notif2->notification_id],
        ], $this->headers);

        $batchDeleteResponse->assertStatus(200)
            ->assertJsonPath('data.deleted_count', 2);

        $this->assertSoftDeleted('notifications', ['notification_id' => $notif1->notification_id]);
        $this->assertSoftDeleted('notifications', ['notification_id' => $notif2->notification_id]);

        // 3. Trashed List
        $trashedResponse = $this->getJson('/api/v1/notifications/trashed', $this->headers);
        $trashedResponse->assertStatus(200);

        // 4. Batch Restore
        $batchRestoreResponse = $this->postJson('/api/v1/notifications/batch-restore', [
            'ids' => [$notif1->notification_id, $notif2->notification_id],
        ], $this->headers);

        $batchRestoreResponse->assertStatus(200)
            ->assertJsonPath('data.restored_count', 2);

        // 5. Batch Force Delete
        $batchForceResponse = $this->postJson('/api/v1/notifications/batch-force-delete', [
            'ids' => [$notif1->notification_id, $notif2->notification_id, $notif3->notification_id],
        ], $this->headers);

        $batchForceResponse->assertStatus(200)
            ->assertJsonPath('data.force_deleted_count', 3);

        $this->assertDatabaseMissing('notifications', ['notification_id' => $notif1->notification_id]);
    }

    public function test_omnichannel_send_direct_dispatches_configured_channels(): void
    {
        Queue::fake();

        /** @var NotificationService $service */
        $service = app(NotificationService::class);

        $notif = $service->sendDirect(
            userId: (int) $this->user->user_id,
            type: NotificationType::GENERAL_INFO,
            title: 'إشعار شامل لكافة القنوات',
            message: 'نص الإشعار التجريبي لجميع القنوات المفعلة',
            inApp: true,
            push: true,
            sms: true,
            email: true,
            smsMessageType: SmsMessageType::ADMIN_BROADCAST,
            emailSubject: 'عنوان البريد المخصص'
        );

        $this->assertNotNull($notif);
        $this->assertEquals('إشعار شامل لكافة القنوات', $notif->notification_title);

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) {
            return $job->userId === (int) $this->user->user_id && $job->title === 'إشعار شامل لكافة القنوات';
        });

        Queue::assertPushed(SendSmsNotificationJob::class, function (SendSmsNotificationJob $job) {
            return $job->messageType === SmsMessageType::ADMIN_BROADCAST;
        });

        Queue::assertPushed(SendEmailNotificationJob::class, function (SendEmailNotificationJob $job) {
            return $job->subject === 'عنوان البريد المخصص';
        });

        // Test with only SMS enabled, no in-app record
        Queue::fake();
        $notif2 = $service->sendDirect(
            userId: (int) $this->user->user_id,
            type: NotificationType::GENERAL_INFO,
            title: 'SMS فقط',
            message: 'رسالة نصية فقط دون إشعار داخلي',
            inApp: false,
            push: false,
            sms: true,
            email: false,
            smsMessageType: SmsMessageType::ADMIN_BROADCAST
        );

        $this->assertNull($notif2);
        Queue::assertNotPushed(SendFcmNotificationJob::class);
        Queue::assertNotPushed(SendEmailNotificationJob::class);
        Queue::assertPushed(SendSmsNotificationJob::class);
    }

    public function test_sms_gate_service_strictly_blocks_null_message_type(): void
    {
        /** @var SMSGateService $smsGateService */
        $smsGateService = app(SMSGateService::class);

        // When messageType is null, resolveGatewaySettings MUST block
        [$config, $ownerType] = $smsGateService->resolveGatewaySettings(null, messageType: null);
        $this->assertNull($config);
        $this->assertEquals('none', $ownerType);

        // Even with networkId, null messageType is NOT allowed
        [$netConfig, $netOwner] = $smsGateService->resolveGatewaySettings(1, messageType: null);
        $this->assertNull($netConfig);
        $this->assertEquals('none', $netOwner);

        // smsAllowedFor must return false for null messageType
        $this->assertFalse($smsGateService->smsAllowedFor(null, messageType: null));
        $this->assertFalse($smsGateService->smsAllowedFor(1, messageType: null));
    }
}
