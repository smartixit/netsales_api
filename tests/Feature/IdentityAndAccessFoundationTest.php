<?php

namespace Tests\Feature;

use App\Models\Network;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use Carbon\Carbon;
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
            'X-Session-Id' => $session1->plain_token,
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
            'X-Session-Id' => $session->plain_token,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $preCheck->assertStatus(200);

        // تنفيذ تسجيل الخروج الرسمي
        $logoutResponse = $this->postJson('/api/v1/auth/logout', [
            'device_uuid' => $device->device_uuid,
        ], [
            'X-Session-Id' => $session->plain_token,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $logoutResponse->assertStatus(200);

        // محاولة استخدام الجلسة بعد تسجيل الخروج يجب أن تفشل بـ 401
        $postCheck = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $session->plain_token,
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
            'X-Session-Id' => $sessionCurrent->plain_token,
            'X-Device-Uuid' => $deviceCurrent->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $logoutOtherResp->assertStatus(200);

        // الجلسة الحالية ما تزال نشطة
        $checkCurrent = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $sessionCurrent->plain_token,
            'X-Device-Uuid' => $deviceCurrent->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $checkCurrent->assertStatus(200);

        // الجلسة الأخرى أصبحت معطلة / 401
        $checkOther = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $sessionOther->plain_token,
            'X-Device-Uuid' => $deviceOther->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $checkOther->assertStatus(401);
    }

    /**
     * اختبار: تشفير وتجزئة رموز الجلسات بـ SHA-256 في قاعدة البيانات (Session Tokens Hashed at Rest).
     */
    public function test_newly_issued_session_token_is_hashed_with_sha256_at_rest_and_authenticates_correctly(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $device = UserDevice::firstOrCreate(
            ['user_id' => $user->user_id, 'device_uuid' => 'HASH-TEST-DEVICE-UUID'],
            [
                'device_name' => 'Hash Test Device',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );

        $rawToken = bin2hex(random_bytes(32));
        $session = UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $device->device_uuid,
            'session_token' => $rawToken,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'NetSales/TestAgent',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(1),
            'status' => 'ACTIVE',
        ]);

        // 1. القيمة الخام تختلف عن القيمة المخزنة في قاعدة البيانات (حظر النص الصريح)
        $dbSession = UserSession::find($session->session_id);
        $this->assertNotNull($dbSession);
        $this->assertNotEquals($rawToken, $dbSession->session_token, 'Session token MUST NOT be stored in plaintext');

        // 2. القيمة المخزنة تطابق تجزئة SHA-256 الحتمية
        $expectedHash = hash('sha256', $rawToken);
        $this->assertEquals($expectedHash, $dbSession->session_token, 'Stored session_token must match SHA-256 digest');
        $this->assertEquals(64, strlen($dbSession->session_token));

        // 3. العميل ينجح في المصادقة باستخدام الرمز الخام الأصلي
        $authResponse = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $rawToken,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $authResponse->assertStatus(200);
        $this->assertEquals($user->user_id, $authResponse->json('data.user.user_id'));

        // 4. رفض الرمز الخاطئ أو المعدل بـ 401
        $tamperedToken = substr($rawToken, 0, -2).'ff';
        $tamperedResponse = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $tamperedToken,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $tamperedResponse->assertStatus(401);

        // 5. محاولة إرسال الـ Hash المباشر من قاعدة البيانات تفشل بـ 401 (منع هجمات تمرير الهاش)
        $hashReplayResponse = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $expectedHash,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $hashReplayResponse->assertStatus(401);
    }

    /**
     * اختبار: رفض الجلسات المنتهية زمنياً أو الملغاة برمز 401 صريح.
     */
    public function test_expired_and_revoked_session_tokens_are_strictly_rejected(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $device = UserDevice::firstOrCreate(
            ['user_id' => $user->user_id, 'device_uuid' => 'EXPIRY-REVOKE-DEVICE-UUID'],
            [
                'device_name' => 'Expiry Device',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );

        // جلسة منتهية الصلاحية
        $rawExpired = bin2hex(random_bytes(32));
        UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $device->device_uuid,
            'session_token' => $rawExpired,
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now()->subDays(2),
            'expires_at' => now()->subHours(2),
            'status' => 'ACTIVE',
        ]);

        $expiredResp = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $rawExpired,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $expiredResp->assertStatus(401);

        // جلسة ملغاة (Revoked)
        $rawRevoked = bin2hex(random_bytes(32));
        UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $device->device_uuid,
            'session_token' => $rawRevoked,
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(1),
            'revoked_at' => now()->subHour(),
            'status' => 'ACTIVE',
        ]);

        $revokedResp = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $rawRevoked,
            'X-Device-Uuid' => $device->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $revokedResp->assertStatus(401);
    }

    /**
     * اختبار: التحقق من إلزامية ترويسة الجهاز ومنع إعادة استخدام التوكن من جهاز مختلف.
     */
    public function test_device_validation_enforces_uuid_matching_and_rejects_missing_or_mismatched_device(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $deviceA = UserDevice::firstOrCreate(
            ['user_id' => $user->user_id, 'device_uuid' => 'DEVICE-A-LEGIT-UUID'],
            [
                'device_name' => 'Device A',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );

        $rawToken = bin2hex(random_bytes(32));
        UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $deviceA->device_uuid,
            'session_token' => $rawToken,
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(1),
            'status' => 'ACTIVE',
        ]);

        // 1. الطلب بدون ترويسة الجهاز يُرفض بـ 401
        $missingDeviceResp = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $rawToken,
            'X-Bypass-Encryption' => 'true',
        ]);
        $missingDeviceResp->assertStatus(401);
        $this->assertStringContainsString('ترويسة معرف الجهاز', $missingDeviceResp->json('message'));

        // 2. الطلب بجهاز مختلف (Replay Attack) يُرفض بـ 401
        $mismatchedDeviceResp = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $rawToken,
            'X-Device-Uuid' => 'DEVICE-B-ATTACKER-UUID',
            'X-Bypass-Encryption' => 'true',
        ]);
        $mismatchedDeviceResp->assertStatus(401);

        // 3. الطلب بالجهاز الصحيح ينجح بـ 200
        $legitResp = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $rawToken,
            'X-Device-Uuid' => $deviceA->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $legitResp->assertStatus(200);
    }

    /**
     * اختبار: عزل عضويات نقاط البيع ومحافظها ومنع اختراق الحدود بين المستأجرين (POS Multi-Tenant Isolation).
     */
    public function test_cross_tenant_pos_membership_and_wallet_access_isolation(): void
    {
        // نقطة بيع 1 ومحفظتها
        $posWallet1 = PosWallet::first();
        $this->assertNotNull($posWallet1);
        $pos1 = Pos::find($posWallet1->pos_id);
        $this->assertNotNull($pos1);

        $userPos1 = User::find($pos1->owner_id);
        $this->assertNotNull($userPos1);

        $devicePos1 = UserDevice::firstOrCreate(
            ['user_id' => $userPos1->user_id, 'device_uuid' => 'POS-1-OWNER-DEVICE-UUID'],
            [
                'device_name' => 'POS 1 Device',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );

        $rawTokenPos1 = bin2hex(random_bytes(32));
        UserSession::create([
            'user_id' => $userPos1->user_id,
            'device_uuid' => $devicePos1->device_uuid,
            'session_token' => $rawTokenPos1,
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(1),
            'status' => 'ACTIVE',
        ]);

        // 1. مستخدم نقطة البيع 1 يمكنه عرض محفظة نقطة البيع 1 التابعة له
        $allowedResp = $this->getJson("/api/v1/pos-wallets/{$posWallet1->wallet_id}", [
            'X-Session-Id' => $rawTokenPos1,
            'X-Device-Uuid' => $devicePos1->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $allowedResp->assertStatus(200);

        // 2. مستخدم نقطة البيع 1 يُمنع تماماً بـ 403 عند محاولة الوصول لمحفظة نقطة بيع أخرى (مستأجر آخر)
        $posWallet2 = PosWallet::where('pos_id', '!=', $pos1->pos_id)->first();
        if ($posWallet2) {
            $crossTenantResp = $this->getJson("/api/v1/pos-wallets/{$posWallet2->wallet_id}", [
                'X-Session-Id' => $rawTokenPos1,
                'X-Device-Uuid' => $devicePos1->device_uuid,
                'X-Bypass-Encryption' => 'true',
            ]);
            $crossTenantResp->assertStatus(403);
            $this->assertStringContainsString('غير مصرح لك بالوصول إلى هذه المحفظة', $crossTenantResp->json('message'));
        }
    }

    /**
     * اختبار: عزل عضويات الشبكات ومنع وصول مستخدمي نقاط البيع أو الشبكات الأخرى لموارد شبكة لا ينتمون إليها.
     */
    public function test_cross_tenant_network_membership_and_wallet_access_isolation(): void
    {
        // شبكة 1 ومحفظتها
        $networkWallet1 = NetworkWallet::first();
        $this->assertNotNull($networkWallet1);
        $network1 = Network::find($networkWallet1->network_id);
        $this->assertNotNull($network1);

        // مستخدم نقطة بيع لا يملك ولا ينتمي للشبكة 1
        $pos = Pos::where('owner_id', '!=', $network1->owner_id)->first();
        $this->assertNotNull($pos);
        $userPos1 = User::find($pos->owner_id);
        $this->assertNotNull($userPos1);

        $devicePos1 = UserDevice::firstOrCreate(
            ['user_id' => $userPos1->user_id, 'device_uuid' => 'POS-1-TO-NET-DEVICE-UUID'],
            [
                'device_name' => 'POS 1 Phone',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );

        $rawTokenPos1 = bin2hex(random_bytes(32));
        UserSession::create([
            'user_id' => $userPos1->user_id,
            'device_uuid' => $devicePos1->device_uuid,
            'session_token' => $rawTokenPos1,
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(1),
            'status' => 'ACTIVE',
        ]);

        // 1. محاولة مستخدم نقطة البيع الوصول لمحفظة الشبكة 1 تُرفض بـ 403
        $networkWalletResp = $this->getJson("/api/v1/network-wallets/{$networkWallet1->wallet_id}", [
            'X-Session-Id' => $rawTokenPos1,
            'X-Device-Uuid' => $devicePos1->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $networkWalletResp->assertStatus(403);

        // 2. محاولة مستخدم نقطة البيع استعراض أعضاء الشبكة 1 تُرفض بـ 422 لعدم امتلاك صلاحية مالك الشبكة
        $membersResp = $this->getJson("/api/v1/networks/{$network1->network_id}/members", [
            'X-Session-Id' => $rawTokenPos1,
            'X-Device-Uuid' => $devicePos1->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $membersResp->assertStatus(422);
        $this->assertStringContainsString('غير مصرح لك بإدارة موظفي وعضويات هذه الشبكة', $membersResp->json('message'));
    }

    /**
     * اختبار: سيمانتك انتهاء صلاحية الجلسة مطلقة (Absolute Expiry) وتحديث النشاط لا يمدد تاريخ الانتهاء.
     */
    public function test_session_expiration_semantics_are_absolute_from_creation(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        $fixedExpiry = Carbon::now('Asia/Aden')->addHours(24);
        $initialActivity = Carbon::now('Asia/Aden')->subHours(2);

        $session = UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => 'ABSOLUTE-EXPIRY-DEVICE',
            'session_token' => bin2hex(random_bytes(32)),
            'ip_address' => '127.0.0.1',
            'logged_at' => $initialActivity,
            'last_activity_at' => $initialActivity,
            'expires_at' => $fixedExpiry,
            'status' => 'ACTIVE',
        ]);

        // استدعاء تحديث النشاط
        $session->touchActivity();
        $refreshed = $session->fresh();

        // تاريخ الانتهاء يجب أن يبقى ثابتاً كما أُنشئ ولا يمتد (Absolute, Not Sliding)
        $this->assertEquals(
            $fixedExpiry->format('Y-m-d H:i'),
            $refreshed->expires_at->format('Y-m-d H:i'),
            'expires_at MUST NOT be extended by user activity'
        );

        // وقت آخر نشاط يجب أن يتم تحديثه إلى الوقت الحالي
        $this->assertTrue(
            $refreshed->last_activity_at->gt($initialActivity),
            'last_activity_at must be updated on activity'
        );
    }
}
