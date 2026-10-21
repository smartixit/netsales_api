<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SessionSecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * اختبار: تسجيل الدخول يعيد رمز جلسة عشوائي آمن تشفيرياً بطول 64 حرفاً.
     */
    public function test_login_returns_64_character_cryptographically_secure_session_token(): void
    {
        $user = User::where('user_phone_number', '772957881')->first();
        if (! $user) {
            $this->markTestSkipped('User 772957881 not found');
        }

        $device = UserDevice::where('user_id', $user->user_id)->where('status', 'ACTIVE')->first();
        $deviceUuid = $device ? $device->device_uuid : 'TEST-DEVICE-UUID';

        if (! $device) {
            $device = UserDevice::create([
                'user_id' => $user->user_id,
                'device_uuid' => $deviceUuid,
                'device_name' => 'Test Phone',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'user_phone_number' => $user->user_phone_number,
            'user_password' => 'Hh$2002Hh',
            'device_uuid' => $deviceUuid,
            'fcm_token' => 'sample_fcm_token_test_123',
        ], [
            'X-Bypass-Encryption' => 'true',
        ]);

        $response->assertStatus(200);
        $sessionId = $response->json('data.session_id');

        $this->assertNotEmpty($sessionId);
        // التحقق من أن رمز الجلسة ليس مجرد رقم تسلسلي بل رمز عشوائي آمن بطول 64 حرفاً سداسياً عشرياً
        $this->assertEquals(64, strlen($sessionId));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $sessionId);

        // التحقق من استخدام رمز الجلسة في طلب محمي مع نفس الجهاز
        $meResponse = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $sessionId,
            'X-Device-Uuid' => $deviceUuid,
            'X-Bypass-Encryption' => 'true',
        ]);

        $meResponse->assertStatus(200);
        $this->assertEquals($user->user_id, $meResponse->json('data.user.user_id'));
    }

    /**
     * اختبار: محاولات تخمين الجلسات الفاشلة تفشل بـ 401، وعند تكرارها تفعل Rate Limiter وتُرجع 429.
     */
    public function test_brute_force_guessing_sessions_triggers_429_rate_limiting(): void
    {
        RateLimiter::clear('session_fail:127.0.0.1');

        // إرسال 20 محاولة تخمين فاشلة
        for ($i = 0; $i < 20; $i++) {
            $response = $this->getJson('/api/v1/auth/me', [
                'X-Session-Id' => 'fake_guess_token_'.$i,
                'X-Bypass-Encryption' => 'true',
            ]);
            $response->assertStatus(401);
        }

        // المحاولة الـ 21 يجب أن تُحظر فوراً بـ 429 Too Many Requests
        $blockedResponse = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => 'another_fake_guess',
            'X-Bypass-Encryption' => 'true',
        ]);

        $blockedResponse->assertStatus(429);
        $this->assertStringContainsString('تم حظر المحاولات مؤقتاً', $blockedResponse->json('message'));

        RateLimiter::clear('session_fail:127.0.0.1');
    }

    /**
     * اختبار: حجب واجهة Swagger تماماً (404) في بيئة الإنتاج.
     */
    public function test_swagger_documentation_is_blocked_in_production_environment(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $response = $this->get('/api/documentation');
        $response->assertStatus(404);

        // إعادة البيئة إلى testing
        $this->app['env'] = 'testing';
        config(['app.env' => 'testing']);
    }

    /**
     * اختبار: استخدام رمز الجلسة من جهاز آخر مختلف أو غير مصرح به يتم رفضه ومنعه فوراً.
     */
    public function test_using_session_token_from_a_different_device_is_rejected(): void
    {
        $user = User::where('user_phone_number', '772957881')->first();
        if (! $user) {
            $this->markTestSkipped('User 772957881 not found');
        }

        $validDeviceUuid = 'ORIGINAL-AUTHENTICATED-DEVICE-UUID';
        $userDevice = UserDevice::where('user_id', $user->user_id)->where('device_uuid', $validDeviceUuid)->first();
        if (! $userDevice) {
            UserDevice::create([
                'user_id' => $user->user_id,
                'device_uuid' => $validDeviceUuid,
                'device_name' => 'Original Valid Device',
                'device_type' => 'ANDROID',
                'is_primary' => true,
                'status' => 'ACTIVE',
            ]);
        }

        // تسجيل الدخول بالجهاز الأصلي
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'user_phone_number' => $user->user_phone_number,
            'user_password' => 'Hh$2002Hh',
            'device_uuid' => $validDeviceUuid,
            'fcm_token' => 'sample_fcm_token_test_123',
        ], [
            'X-Bypass-Encryption' => 'true',
        ]);

        $loginResponse->assertStatus(200);
        $sessionToken = $loginResponse->json('data.session_id');

        // 1. الطلب من نفس الجهاز المصرح به ينجح بنجاح
        $validRequest = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $sessionToken,
            'X-Device-Uuid' => $validDeviceUuid,
            'X-Bypass-Encryption' => 'true',
        ]);
        $validRequest->assertStatus(200);

        // 2. محاولة مهاجم استخدام نفس توكن الجلسة من جهاز مختلف يتم رفضها فوراً بـ 401
        $stolenSessionFromOtherDevice = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $sessionToken,
            'X-Device-Uuid' => 'ATTACKER-DIFFERENT-DEVICE-UUID',
            'X-Bypass-Encryption' => 'true',
        ]);
        $stolenSessionFromOtherDevice->assertStatus(401);

        // 3. محاولة استخدام نفس توكن الجلسة مع حذف ترويسة الجهاز تماماً للالتفاف يتم رفضها فوراً بـ 401
        $stolenSessionWithoutDeviceHeader = $this->getJson('/api/v1/auth/me', [
            'X-Session-Id' => $sessionToken,
            'X-Bypass-Encryption' => 'true',
        ]);
        $stolenSessionWithoutDeviceHeader->assertStatus(401);
    }

    /**
     * اختبار: كود التحقق الثنائي (2FA OTP) مقيد حتمياً بالجهاز المنشئ ولا يمكن تأكيده من جهاز مختلف.
     */
    public function test_otp_2fa_verification_is_strictly_bound_to_initiating_device(): void
    {
        $user = User::where('user_phone_number', '772957881')->first();
        if (! $user) {
            $this->markTestSkipped('User 772957881 not found');
        }

        // إزالة الأجهزة السابقة لضمان طلب 2FA كجهاز جديد
        UserDevice::where('user_id', $user->user_id)->delete();

        $initiatorDeviceUuid = 'INITIATOR-DEVICE-UUID-12345';

        // 1. طلب تسجيل الدخول من الجهاز الأصلي -> يطلب 2FA
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'user_phone_number' => $user->user_phone_number,
            'user_password' => 'Hh$2002Hh',
            'device_uuid' => $initiatorDeviceUuid,
            'device_name' => 'Original Phone',
            'device_type' => 'ANDROID',
            'fcm_token' => 'fcm_initiator_token_123',
        ], [
            'X-Bypass-Encryption' => 'true',
        ]);

        $loginResponse->assertStatus(200);
        $this->assertTrue($loginResponse->json('data.require_2fa'));

        // استخراج رمز الـ OTP المولد
        $otpRecord = \App\Models\Otp::where('user_id', $user->user_id)
            ->where('is_used', false)
            ->latest('otp_id')
            ->first();

        $this->assertNotNull($otpRecord);
        $this->assertEquals($initiatorDeviceUuid, $otpRecord->device_uuid);

        // 2. محاولة تأكيد الرمز من جهاز مختلف -> يجب أن تُرفض برمز 422
        $mismatchResponse = $this->postJson('/api/v1/auth/2fa/verify', [
            'user_phone_number' => $user->user_phone_number,
            'otp' => $otpRecord->otp_code,
            'device_uuid' => 'ATTACKER-DIFFERENT-DEVICE-999',
            'device_name' => 'Attacker Phone',
            'device_type' => 'IOS',
            'fcm_token' => 'fcm_attacker_token_999',
        ], [
            'X-Bypass-Encryption' => 'true',
        ]);

        $mismatchResponse->assertStatus(422);
        $mismatchResponse->assertJsonValidationErrors(['device_uuid']);

        // 3. تأكيد الرمز من نفس الجهاز الأصلي -> يجب أن ينجح برمز 200
        $validVerifyResponse = $this->postJson('/api/v1/auth/2fa/verify', [
            'user_phone_number' => $user->user_phone_number,
            'otp' => $otpRecord->otp_code,
            'device_uuid' => $initiatorDeviceUuid,
            'device_name' => 'Original Phone',
            'device_type' => 'ANDROID',
            'fcm_token' => 'fcm_initiator_token_123',
        ], [
            'X-Bypass-Encryption' => 'true',
        ]);

        $validVerifyResponse->assertStatus(200);
        $this->assertEquals($initiatorDeviceUuid, $validVerifyResponse->json('data.device_uuid'));
    }
}
