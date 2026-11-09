<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\OtpType;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Models\Otp;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TwoFactorAuthenticationLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected UserDevice $device;
    protected string $sessionId;
    protected string $deviceUuid;
    protected string $password = 'SecureP@ss123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceUuid = 'TEST-2FA-DEVICE-UUID-' . bin2hex(random_bytes(6));

        $this->user = User::create([
            'role_id' => null,
            'user_full_name' => '2FA Test User',
            'user_phone_number' => '77' . random_int(1000000, 9999999),
            'user_email' => 'user2fa_' . uniqid() . '@example.com',
            'user_password' => Hash::make($this->password),
            'status' => UserStatus::ACTIVE,
            'user_2fa_enabled' => false,
            'must_change_password' => false,
        ]);

        $this->device = UserDevice::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => $this->deviceUuid,
            'device_name' => 'Primary Test Device',
            'device_type' => DeviceType::ANDROID,
            'is_primary' => true,
            'status' => UserDeviceStatus::ACTIVE,
        ]);

        // Login to acquire authenticated session
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'user_phone_number' => $this->user->user_phone_number,
            'user_password' => $this->password,
            'device_uuid' => $this->deviceUuid,
            'fcm_token' => 'fcm_token_2fa_test',
        ], [
            'X-Bypass-Encryption' => 'true',
        ]);

        $loginRes->assertStatus(200);
        $this->sessionId = (string) $loginRes->json('data.session_id');
    }

    protected function authHeaders(array $extra = []): array
    {
        return array_merge([
            'X-Bypass-Encryption' => 'true',
            'X-Session-Id' => $this->sessionId,
            'X-Device-Uuid' => $this->deviceUuid,
        ], $extra);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $resReq = $this->postJson('/api/v1/auth/2fa/request-otp', [], [
            'X-Bypass-Encryption' => 'true',
        ]);
        $resReq->assertStatus(401);

        $resToggle = $this->postJson('/api/v1/auth/toggle-2fa', ['enable' => true], [
            'X-Bypass-Encryption' => 'true',
        ]);
        $resToggle->assertStatus(401);
    }

    public function test_request_2fa_otp_generates_valid_otp_bound_to_device(): void
    {
        $res = $this->postJson('/api/v1/auth/2fa/request-otp', [], $this->authHeaders());

        $res->assertStatus(200);
        $res->assertJsonFragment(['success' => true]);

        $otpRecord = Otp::where('user_id', $this->user->user_id)
            ->where('otp_type', OtpType::OTP_2FA)
            ->where('is_used', false)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($otpRecord);
        $this->assertEquals($this->deviceUuid, $otpRecord->device_uuid);
        $this->assertEquals(6, strlen($otpRecord->otp_code));
    }

    public function test_cannot_enable_2fa_without_otp(): void
    {
        $res = $this->postJson('/api/v1/auth/toggle-2fa', [
            'enable' => true,
        ], $this->authHeaders());

        $res->assertStatus(422);
        $this->assertFalse((bool) $this->user->fresh()->user_2fa_enabled);
    }

    public function test_cannot_enable_2fa_with_invalid_otp(): void
    {
        // Generate valid OTP
        $this->postJson('/api/v1/auth/2fa/request-otp', [], $this->authHeaders());

        $res = $this->postJson('/api/v1/auth/toggle-2fa', [
            'enable' => true,
            'otp' => '000000',
        ], $this->authHeaders());

        $res->assertStatus(422);
        $this->assertFalse((bool) $this->user->fresh()->user_2fa_enabled);
    }

    public function test_successfully_enable_2fa_with_valid_otp(): void
    {
        $this->postJson('/api/v1/auth/2fa/request-otp', [], $this->authHeaders());

        $otpRecord = Otp::where('user_id', $this->user->user_id)
            ->where('otp_type', OtpType::OTP_2FA)
            ->where('is_used', false)
            ->latest('created_at')
            ->firstOrFail();

        $res = $this->postJson('/api/v1/auth/toggle-2fa', [
            'enable' => true,
            'otp' => $otpRecord->otp_code,
        ], $this->authHeaders());

        $res->assertStatus(200);
        $this->assertTrue((bool) $this->user->fresh()->user_2fa_enabled);
        $this->assertTrue((bool) $otpRecord->fresh()->is_used);
    }

    public function test_cannot_disable_2fa_without_password_or_otp(): void
    {
        // First enable 2FA
        $this->user->update(['user_2fa_enabled' => true]);

        $res = $this->postJson('/api/v1/auth/toggle-2fa', [
            'enable' => false,
        ], $this->authHeaders());

        $res->assertStatus(422);
        $this->assertTrue((bool) $this->user->fresh()->user_2fa_enabled);
    }

    public function test_cannot_disable_2fa_with_incorrect_password(): void
    {
        $this->user->update(['user_2fa_enabled' => true]);

        $res = $this->postJson('/api/v1/auth/toggle-2fa', [
            'enable' => false,
            'current_password' => 'WrongPassword!99',
        ], $this->authHeaders());

        $res->assertStatus(422);
        $this->assertTrue((bool) $this->user->fresh()->user_2fa_enabled);
    }

    public function test_successfully_disable_2fa_with_correct_current_password(): void
    {
        $this->user->update(['user_2fa_enabled' => true]);

        $res = $this->postJson('/api/v1/auth/toggle-2fa', [
            'enable' => false,
            'current_password' => $this->password,
        ], $this->authHeaders());

        $res->assertStatus(200);
        $this->assertFalse((bool) $this->user->fresh()->user_2fa_enabled);
    }

    public function test_successfully_disable_2fa_with_valid_otp(): void
    {
        $this->user->update(['user_2fa_enabled' => true]);

        $this->postJson('/api/v1/auth/2fa/request-otp', [], $this->authHeaders());

        $otpRecord = Otp::where('user_id', $this->user->user_id)
            ->where('otp_type', OtpType::OTP_2FA)
            ->where('is_used', false)
            ->latest('created_at')
            ->firstOrFail();

        $res = $this->postJson('/api/v1/auth/toggle-2fa', [
            'enable' => false,
            'otp' => $otpRecord->otp_code,
        ], $this->authHeaders());

        $res->assertStatus(200);
        $this->assertFalse((bool) $this->user->fresh()->user_2fa_enabled);
        $this->assertTrue((bool) $otpRecord->fresh()->is_used);
    }

    public function test_secondary_device_login_challenges_2fa_when_enabled(): void
    {
        // User has 2FA enabled
        $this->user->update(['user_2fa_enabled' => true]);

        $secondaryDeviceUuid = 'SECONDARY-DEV-' . bin2hex(random_bytes(6));
        UserDevice::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => $secondaryDeviceUuid,
            'device_name' => 'Secondary Device',
            'device_type' => DeviceType::ANDROID,
            'is_primary' => false,
            'status' => UserDeviceStatus::ACTIVE,
        ]);

        // Attempt login from secondary device
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'user_phone_number' => $this->user->user_phone_number,
            'user_password' => $this->password,
            'device_uuid' => $secondaryDeviceUuid,
            'fcm_token' => 'fcm_sec',
        ], [
            'X-Bypass-Encryption' => 'true',
        ]);

        $loginRes->assertStatus(200);
        $this->assertTrue((bool) $loginRes->json('data.require_2fa'));

        // Retrieve generated OTP
        $otp = Otp::where('user_id', $this->user->user_id)
            ->where('otp_type', OtpType::OTP_2FA)
            ->where('is_used', false)
            ->latest('created_at')
            ->firstOrFail();

        // Complete 2FA verification
        $verifyRes = $this->postJson('/api/v1/auth/2fa/verify', [
            'user_phone_number' => $this->user->user_phone_number,
            'otp' => $otp->otp_code,
            'device_uuid' => $secondaryDeviceUuid,
            'fcm_token' => 'fcm_sec',
        ], [
            'X-Bypass-Encryption' => 'true',
        ]);

        $verifyRes->assertStatus(200);
        $this->assertNotEmpty($verifyRes->json('data.session_id'));
    }
}
