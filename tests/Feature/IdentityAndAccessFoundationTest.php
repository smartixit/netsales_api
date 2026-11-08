<?php

namespace Tests\Feature;

use App\Interfaces\UserSessionRepositoryInterface;
use App\Models\Network;
use App\Models\NetworkPos;
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
     * Create a self-contained tenant fixture. Tests must not rely on seeded
     * networks or wallets because DatabaseSeeder intentionally creates only
     * platform foundation data.
     *
     * @return array{owner: User, network: Network, pos: Pos, networkWallet: NetworkWallet, posWallet: PosWallet}
     */
    private function createWalletTenant(string $label): array
    {
        $suffix = $label.'-'.bin2hex(random_bytes(4));

        $owner = User::create([
            'role_id' => null,
            'user_full_name' => 'Wallet Tenant '.$suffix,
            'user_phone_number' => '77'.random_int(1000000, 9999999),
            'user_password' => bcrypt('Password@123'),
            'status' => 'ACTIVE',
        ]);

        $network = Network::create([
            'owner_id' => $owner->user_id,
            'network_name' => 'Network '.$suffix,
            'network_phone_number' => '71'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        $pos = Pos::create([
            'owner_id' => $owner->user_id,
            'pos_name' => 'POS '.$suffix,
            'pos_phone_number' => '73'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        NetworkPos::create([
            'network_id' => $network->network_id,
            'pos_id' => $pos->pos_id,
            'status' => true,
        ]);

        $networkWallet = NetworkWallet::create([
            'network_id' => $network->network_id,
            'wallet_uuid' => 'NET-WALLET-'.$suffix,
            'wallet_balance' => 0,
            'status' => true,
        ]);

        $posWallet = PosWallet::create([
            'network_id' => $network->network_id,
            'pos_id' => $pos->pos_id,
            'wallet_uuid' => 'POS-WALLET-'.$suffix,
            'wallet_balance' => 0,
            'credit_limit' => 100000,
            'status' => true,
        ]);

        return compact('owner', 'network', 'pos', 'networkWallet', 'posWallet');
    }

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

        // 6. التحقق من إنشاء الجلسة عبر المستودع وعدم حدوث تجزئة مزدوجة (Single SHA-256 Hashing Invariant)
        $sessionRepo = app(UserSessionRepositoryInterface::class);
        $repoSession = $sessionRepo->createSession($user->user_id, $device->device_uuid);
        $this->assertNotNull($repoSession->plain_token);
        $this->assertEquals(64, strlen($repoSession->plain_token));
        $storedHash = $repoSession->fresh()->session_token;
        $this->assertEquals(hash('sha256', $repoSession->plain_token), $storedHash, 'Stored token must be exactly single SHA-256 hash');
        $this->assertNotEquals(hash('sha256', $storedHash), $storedHash, 'Stored token must not be double hashed');

        // 7. التحقق من دوال البحث في المستودع: البحث بالرمز الخام وبالمعرف الرقمي
        $foundByToken = $sessionRepo->findActiveSession($repoSession->plain_token);
        $this->assertNotNull($foundByToken);
        $this->assertEquals($repoSession->session_id, $foundByToken->session_id);

        $foundById = $sessionRepo->findByTokenOrId($repoSession->session_id);
        $this->assertNotNull($foundById);
        $this->assertEquals($repoSession->session_id, $foundById->session_id);
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
        // إنشاء نقطتي بيع مستقلتين داخل الاختبار بدل الاعتماد على بيانات Seed.
        $tenant1 = $this->createWalletTenant('POS-A');
        $tenant2 = $this->createWalletTenant('POS-B');

        $posWallet1 = $tenant1['posWallet'];
        $pos1 = $tenant1['pos'];
        $userPos1 = $tenant1['owner'];
        $posWallet2 = $tenant2['posWallet'];

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

        // 2. مستخدم نقطة البيع 1 يُمنع تماماً من محفظة نقطة البيع الثانية.
        $crossTenantResp = $this->getJson("/api/v1/pos-wallets/{$posWallet2->wallet_id}", [
            'X-Session-Id' => $rawTokenPos1,
            'X-Device-Uuid' => $devicePos1->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $crossTenantResp->assertStatus(403);
        $this->assertStringContainsString('غير مصرح لك بالوصول إلى هذه المحفظة', $crossTenantResp->json('message'));
    }

    /**
     * اختبار: عزل عضويات الشبكات ومنع وصول مستخدمي نقاط البيع أو الشبكات الأخرى لموارد شبكة لا ينتمون إليها.
     */
    public function test_cross_tenant_network_membership_and_wallet_access_isolation(): void
    {
        // إنشاء شبكتين مستقلتين حتى يكون اختبار المستأجر الآخر حتميًا.
        $networkTenant = $this->createWalletTenant('NETWORK-A');
        $posTenant = $this->createWalletTenant('NETWORK-B');

        $networkWallet1 = $networkTenant['networkWallet'];
        $network1 = $networkTenant['network'];
        $pos = $posTenant['pos'];
        $userPos1 = $posTenant['owner'];

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
     * اختبار: عزل الشبكات ضد بعضها البعض (Network-to-Network Tenant Isolation)
     * مالك الشبكة أ يمكنه عرض محفظة شبكته، ويُمنع بـ 403 تماماً من عرض محفظة الشبكة ب أو سجلاتها.
     */
    public function test_network_owner_a_cannot_access_network_b_resources(): void
    {
        $tenantA = $this->createWalletTenant('OWNER-A');
        $tenantB = $this->createWalletTenant('OWNER-B');

        $networkWalletA = $tenantA['networkWallet'];
        $networkA = $tenantA['network'];
        $userA = $tenantA['owner'];
        $networkWalletB = $tenantB['networkWallet'];
        $networkB = $tenantB['network'];

        $deviceA = UserDevice::firstOrCreate(
            ['user_id' => $userA->user_id, 'device_uuid' => 'NET-A-OWNER-DEVICE-UUID'],
            [
                'device_name' => 'Net A Owner Phone',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]
        );

        $rawTokenA = bin2hex(random_bytes(32));
        UserSession::create([
            'user_id' => $userA->user_id,
            'device_uuid' => $deviceA->device_uuid,
            'session_token' => $rawTokenA,
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(1),
            'status' => 'ACTIVE',
        ]);

        // 1. المستخدم أ يمكنه استعراض محفظة شبكته أ بنجاح (200)
        $respOwn = $this->getJson("/api/v1/network-wallets/{$networkWalletA->wallet_id}", [
            'X-Session-Id' => $rawTokenA,
            'X-Device-Uuid' => $deviceA->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $respOwn->assertStatus(200);

        // 2. المستخدم أ يُمنع بـ 403 عند محاولة استعراض محفظة الشبكة ب (Network-to-Network Isolation)
        $respOther = $this->getJson("/api/v1/network-wallets/{$networkWalletB->wallet_id}", [
            'X-Session-Id' => $rawTokenA,
            'X-Device-Uuid' => $deviceA->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $respOther->assertStatus(403);
        $this->assertStringContainsString('غير مصرح لك بالوصول إلى هذه المحفظة', $respOther->json('message'));

        // 3. المستخدم أ يُمنع بـ 403 عند محاولة استعراض محفظة الشبكة ب عبر معرف الشبكة
        $respByNetwork = $this->getJson("/api/v1/network-wallets/network/{$networkB->network_id}", [
            'X-Session-Id' => $rawTokenA,
            'X-Device-Uuid' => $deviceA->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $respByNetwork->assertStatus(403);

        // 4. المستخدم أ يُمنع بـ 422 عند محاولة استعراض أعضاء الشبكة ب
        $respMembers = $this->getJson("/api/v1/networks/{$networkB->network_id}/members", [
            'X-Session-Id' => $rawTokenA,
            'X-Device-Uuid' => $deviceA->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $respMembers->assertStatus(422);
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
