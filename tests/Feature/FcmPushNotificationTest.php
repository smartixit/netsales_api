<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\NotificationType;
use App\Enums\UserDeviceStatus;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Jobs\SendFcmNotificationJob;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Services\NotificationService;
use App\Services\Support\FcmService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FcmPushNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();

       $auth = $this->createAuthenticatedSession($this->user);
        $this->session = $auth['session'];
        $this->headers = $auth['headers'];
    }

    public function test_can_register_and_update_fcm_token_via_api(): void
    {
        $payload = [
            'device_uuid' => 'test-device-uuid-12345',
            'fcm_token' => 'fcm-sample-token-abc-xyz-78901234567890',
            'device_name' => 'Galaxy S25 Ultra',
            'device_type' => 'ANDROID',
        ];

        $response = $this->postJson('/api/v1/notifications/fcm-token', $payload, $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->user_id,
            'device_uuid' => 'test-device-uuid-12345',
            'fcm_token' => 'fcm-sample-token-abc-xyz-78901234567890',
        ]);
    }

    public function test_repository_get_active_fcm_tokens_returns_only_active_tokens(): void
    {
        $repo = app(UserDeviceRepositoryInterface::class);

        // إنشاء جهازين: واحد نشط برمز والآخر بدون رمز
        UserDevice::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => 'test-active-uuid-1',
            'device_name' => 'Device 1',
            'device_type' => DeviceType::ANDROID,
            'fcm_token' => 'token-active-1-1234567890',
            'status' => UserDeviceStatus::ACTIVE,
            'is_primary' => true,
        ]);

        UserDevice::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => 'test-inactive-uuid-2',
            'device_name' => 'Device 2',
            'device_type' => DeviceType::ANDROID,
            'fcm_token' => null,
            'status' => UserDeviceStatus::ACTIVE,
            'is_primary' => false,
        ]);

        $tokens = $repo->getActiveFcmTokensForUser($this->user->user_id);

        $this->assertContains('token-active-1-1234567890', $tokens);
        $this->assertNotContains(null, $tokens);
    }

    public function test_repository_can_invalidate_dead_fcm_token(): void
    {
        $repo = app(UserDeviceRepositoryInterface::class);

        $device = UserDevice::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => 'test-dead-token-uuid',
            'device_name' => 'Dead Token Device',
            'device_type' => DeviceType::ANDROID,
            'fcm_token' => 'expired-token-to-invalidate',
            'status' => UserDeviceStatus::ACTIVE,
            'is_primary' => false,
        ]);

        $updatedCount = $repo->invalidateFcmToken('expired-token-to-invalidate');

        $this->assertGreaterThanOrEqual(1, $updatedCount);
        $this->assertNull($device->fresh()->fcm_token);
    }

    public function test_notification_service_send_direct_dispatches_fcm_job(): void
    {
        Queue::fake();

        $service = app(NotificationService::class);

        $notification = $service->sendDirect(
            userId: $this->user->user_id,
            type: NotificationType::GENERAL_INFO,
            title: 'إشعار اختبار فوري',
            message: 'محتوى تجريبي للإشعار الفوري الموجه'
        );

        $this->assertNotNull($notification);
        $this->assertEquals('إشعار اختبار فوري', $notification->notification_title);

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) {
            return $job->userId === $this->user->user_id
                && $job->title === 'إشعار اختبار فوري'
                && $job->body === 'محتوى تجريبي للإشعار الفوري الموجه';
        });
    }

    public function test_fcm_service_send_to_user_handles_calls_gracefully(): void
    {
        // تسجيل جهاز بمحدد FCM
        UserDevice::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => 'test-fcm-send-uuid',
            'device_name' => 'Active Mobile',
            'device_type' => DeviceType::ANDROID,
            'fcm_token' => 'mock-valid-fcm-token-12345',
            'status' => UserDeviceStatus::ACTIVE,
            'is_primary' => true,
        ]);

        $fcmService = app(FcmService::class);

        $result = $fcmService->sendToUser(
            userId: $this->user->user_id,
            title: 'عنوان تجريبي',
            body: 'نص تجريبي للإشعار الفوري',
            data: ['sample_key' => 'sample_value']
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertGreaterThanOrEqual(1, $result['total']);
    }

    public function test_admin_can_send_direct_fcm_notification_to_user(): void
    {
        Queue::fake();

        $payload = [
            'user_id' => $this->user->user_id,
            'title' => 'تنبيه إداري مباشر',
            'body' => 'يرجى مراجعة بيانات الحساب والتحقق من كلمة المرور.',
            'channels' => ['in_app', 'fcm'],
        ];

        $response = $this->postJson('/api/v1/admin/notifications/user', $payload, $this->headers);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        Queue::assertPushed(SendFcmNotificationJob::class, function (SendFcmNotificationJob $job) {
            return $job->userId === $this->user->user_id
                && $job->title === 'تنبيه إداري مباشر';
        });
    }
}
