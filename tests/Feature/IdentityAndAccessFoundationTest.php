<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class IdentityAndAccessFoundationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * اختبار: رفض الطلبات غير المصادق عليها برمز 401 صريح.
     */
    public function test_unauthenticated_requests_are_strictly_rejected(): void
    {
        // 1. فحص الملف الشخصي دون ترويسة جلسة
        $responseMe = $this->getJson('/api/v1/auth/me', [
            'X-Bypass-Encryption' => 'true',
        ]);
        $responseMe->assertStatus(401);

        // 2. تسجيل الخروج دون ترويسة جلسة
        $responseLogout = $this->postJson('/api/v1/auth/logout', [], [
            'X-Bypass-Encryption' => 'true',
        ]);
        $responseLogout->assertStatus(401);

        // 3. استعراض الأجهزة دون ترويسة جلسة
        $responseDevices = $this->getJson('/api/v1/auth/devices', [
            'X-Bypass-Encryption' => 'true',
        ]);
        $responseDevices->assertStatus(401);

        // 4. إنهاء الجلسات الأخرى دون ترويسة جلسة
        $responseOther = $this->postJson('/api/v1/auth/logout-other-devices', [], [
            'X-Bypass-Encryption' => 'true',
        ]);
        $responseOther->assertStatus(401);
    }

    /**
     * اختبار: لا يمكن للمستخدم فصل أو إلغاء اقتران جهاز تابع لمستخدم آخر (Tenant/Identity Isolation).
     */
    public function test_user_cannot_disconnect_another_users_device(): void
    {
        // مستخدم 1
        $user1 = User::first();
        $this->assertNotNull($user1, 'No user found in database');

        $device1 = UserDevice::firstOrCreate(
            ['user_id' => $user1->user_id, 'device_uuid' => 'DEVICE-USER-1-UUID'],
            [
                'device_name' => 'User 1 Phone',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );

        $session1 = UserSession::create([
            'user_id' => $user1->user_id,
            'device_uuid' => $device1->device_uuid,
            'session_token' => bin2hex(random_bytes(32)),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'NetSales/TestAgent',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
        ]);

        // مستخدم 2 مع جهاز مستقل
        $user2 = User::where('user_id', '!=', $user1->user_id)->first();
        if (! $user2) {
            $user2 = User::create([
                'user_full_name' => 'User Two Test',
                'user_phone_number' => '779998877',
                'user_password' => bcrypt('Password@123'),
                'role_id' => $user1->role_id,
                'status' => 'ACTIVE',
            ]);
        }

        $device2 = UserDevice::create([
            'user_id' => $user2->user_id,
            'device_uuid' => 'DEVICE-USER-2-UUID',
            'device_name' => 'User 2 Phone',
            'device_type' => 'IOS',
            'is_primary' => true,
            'status' => 'ACTIVE',
        ]);

        // محاولة المستخدم 1 حذف جهاز المستخدم 2
        $response = $this->deleteJson("/api/v1/auth/devices/{$device2->device_id}", [], [
            'X-Session-Id' => $session1->session_token,
            'X-Device-Uuid' => $device1->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);

        // يجب أن يُرفض بـ 404 لأن الجهاز لا ينتمي للمستخدم المصادق عليه
        $response->assertStatus(404);
        $this->assertDatabaseHas('user_devices', [
            'device_id' => $device2->device_id,
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * اختبار: تسجيل الخروج يبطل الجلسة فورياً في قاعدة البيانات وأي طلب لاحق بها يُرفض بـ 401.
     */
    public function test_logout_revokes_session_and_subsequent_request_fails(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $device = UserDevice::firstOrCreate(
            ['user_id' => $user->user_id, 'device_uuid' => 'LOGOUT-TEST-DEVICE-UUID'],
            [
                'device_name' => 'Logout Test Device',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );

        $session = UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $device->device_uuid,
            'session_token' => bin2hex(random_bytes(32)),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'NetSales/TestAgent',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
        ]);

        // تأكيد نجاح الطلب بالجلسة قبل تسجيل الخروج
        $preCheck = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $session->session_token,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $preCheck->assertStatus(200);

        // تنفيذ تسجيل الخروج الرسمي
        $logoutResponse = $this->postJson('/api/v1/auth/logout', [
            'device_uuid' => $device->device_uuid,
        ], [
            'X-Session-Id' => $session->session_token,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $logoutResponse->assertStatus(200);

        // محاولة استخدام الجلسة بعد تسجيل الخروج يجب أن تفشل بـ 401
        $postCheck = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $session->session_token,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $postCheck->assertStatus(401);
    }

    /**
     * اختبار: تسجيل الخروج من كافة الأجهزة الأخرى يبطل الجلسات الأخرى ويبقي الجلسة الحالية نشطة.
     */
    public function test_logout_other_devices_terminates_all_other_sessions(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $deviceCurrent = UserDevice::create([
            'user_id' => $user->user_id,
            'device_uuid' => 'CURRENT-DEV-UUID-'.uniqid(),
            'device_name' => 'Current Session Device',
            'device_type' => 'ANDROID',
            'is_primary' => true,
            'status' => 'ACTIVE',
        ]);

        $sessionCurrent = UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $deviceCurrent->device_uuid,
            'session_token' => bin2hex(random_bytes(32)),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'NetSales/TestAgent',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
        ]);

        $deviceOther = UserDevice::create([
            'user_id' => $user->user_id,
            'device_uuid' => 'OTHER-DEV-UUID-'.uniqid(),
            'device_name' => 'Other Session Device',
            'device_type' => 'WINDOWS',
            'is_primary' => false,
            'status' => 'ACTIVE',
        ]);

        $sessionOther = UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $deviceOther->device_uuid,
            'session_token' => bin2hex(random_bytes(32)),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'NetSales/OtherAgent',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
        ]);

        // إنهاء الجلسات الأخرى من الجلسة الحالية
        $logoutOtherResp = $this->postJson('/api/v1/auth/logout-other-devices', [], [
            'X-Session-Id' => $sessionCurrent->session_token,
            'X-Device-Uuid' => $deviceCurrent->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $logoutOtherResp->assertStatus(200);

        // الجلسة الحالية ما تزال نشطة
        $checkCurrent = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $sessionCurrent->session_token,
            'X-Device-Uuid' => $deviceCurrent->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $checkCurrent->assertStatus(200);

        // الجلسة الأخرى أصبحت معطلة / 401
        $checkOther = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $sessionOther->session_token,
            'X-Device-Uuid' => $deviceOther->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $checkOther->assertStatus(401);
    }
}

