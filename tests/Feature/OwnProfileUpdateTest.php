<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnProfileUpdateTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return array{0: User, 1: string, 2: array<string, string>}
     */
    private function authenticatedUser(): array
    {
        $user = User::create([
            'user_full_name' => 'مستخدم ملف شخصي',
            'user_phone_number' => '77'.random_int(1000000, 9999999),
            'user_email' => null,
            'user_password' => Hash::make('Secret@123'),
            'status' => UserStatus::ACTIVE->value,
        ]);

        $token = bin2hex(random_bytes(32));
        $session = new UserSession;
        $session->user_id = $user->user_id;
        $session->device_uuid = 'PROFILE-TEST-DEVICE';
        $session->ip_address = '127.0.0.1';
        $session->user_agent = 'PHPUnit';
        $session->logged_at = now('Asia/Aden');
        $session->last_activity_at = now('Asia/Aden');
        $session->expires_at = now('Asia/Aden')->addHours(12);
        $session->setRawToken($token);
        $session->save();

        return [$user, $token, [
            'X-Session-Id' => $token,
            'X-Device-Uuid' => 'PROFILE-TEST-DEVICE',
            'X-Bypass-Encryption' => 'true',
        ]];
    }

    public function test_authenticated_user_can_update_own_profile_without_platform_permissions(): void
    {
        [$user, , $headers] = $this->authenticatedUser();

        $response = $this->putJson('/api/v1/auth/profile', [
            'user_full_name' => 'اسم محدث',
            'user_phone_number' => '78'.random_int(1000000, 9999999),
            'user_email' => 'profile-'.uniqid().'@example.test',
        ], $headers);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.user_full_name', 'اسم محدث');

        $this->assertSame('اسم محدث', $user->fresh()->user_full_name);
    }

    public function test_self_service_profile_update_ignores_administrative_and_security_fields(): void
    {
        [$user, , $headers] = $this->authenticatedUser();
        $originalPassword = $user->user_password;
        $originalRoleId = $user->role_id;
        $originalStatus = $user->status;

        $response = $this->putJson('/api/v1/auth/profile', [
            'user_full_name' => 'اسم آمن',
            'role_id' => 999999,
            'status' => UserStatus::BANNED->value,
            'user_password' => 'Attacker@123',
            'user_2fa_enabled' => true,
        ], $headers);

        $response->assertOk();
        $fresh = $user->fresh();

        $this->assertSame('اسم آمن', $fresh->user_full_name);
        $this->assertSame($originalRoleId, $fresh->role_id);
        $this->assertSame($originalStatus, $fresh->status);
        $this->assertSame($originalPassword, $fresh->user_password);
        $this->assertFalse((bool) $fresh->user_2fa_enabled);
    }

    public function test_self_service_profile_update_rejects_duplicate_phone_number(): void
    {
        [$user, , $headers] = $this->authenticatedUser();
        $anotherUser = User::create([
            'user_full_name' => 'مستخدم آخر',
            'user_phone_number' => '79'.random_int(1000000, 9999999),
            'user_password' => Hash::make('Secret@123'),
            'status' => UserStatus::ACTIVE->value,
        ]);

        $this->putJson('/api/v1/auth/profile', [
            'user_phone_number' => $anotherUser->user_phone_number,
        ], $headers)->assertUnprocessable();

        $this->assertNotSame($anotherUser->user_phone_number, $user->fresh()->user_phone_number);
    }
}
