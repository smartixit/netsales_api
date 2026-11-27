<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\UserDeviceStatus;
use App\Enums\UserSessionStatus;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSessionsTest extends TestCase
{
    use DatabaseTransactions;

    private function createUserWithSessions(): array
    {
        $user = User::create([
            'user_full_name' => 'مستخدم جلسات اختباري',
            'user_phone_number' => '77'.random_int(1000000, 9999999),
            'user_password' => Hash::make('Secret@123'),
            'status' => UserStatus::ACTIVE->value,
        ]);

        UserDevice::create([
            'user_id' => $user->user_id,
            'device_uuid' => 'DEV-CURRENT-001',
            'device_name' => 'Test Android Device',
            'device_type' => DeviceType::ANDROID,
            'is_primary' => true,
            'status' => UserDeviceStatus::ACTIVE,
        ]);

        $currentToken = bin2hex(random_bytes(32));
        $current = new UserSession;
        $current->user_id = $user->user_id;
        $current->device_uuid = 'DEV-CURRENT-001';
        $current->ip_address = '192.168.1.10';
        $current->user_agent = 'NetSales/Android';
        $current->logged_at = now('Asia/Aden')->subMinutes(10);
        $current->last_activity_at = now('Asia/Aden');
        $current->expires_at = now('Asia/Aden')->addHours(24);
        $current->setRawToken($currentToken);
        $current->save();

        $other = new UserSession;
        $other->user_id = $user->user_id;
        $other->device_uuid = 'DEV-OTHER-002';
        $other->ip_address = '10.0.0.55';
        $other->user_agent = 'Chrome/Windows';
        $other->logged_at = now('Asia/Aden')->subHours(2);
        $other->last_activity_at = now('Asia/Aden')->subMinutes(30);
        $other->expires_at = now('Asia/Aden')->addHours(24);
        $other->setRawToken(bin2hex(random_bytes(32)));
        $other->save();

        $revoked = new UserSession;
        $revoked->user_id = $user->user_id;
        $revoked->device_uuid = 'DEV-REVOKED-003';
        $revoked->ip_address = '172.16.0.4';
        $revoked->user_agent = 'Safari/iOS';
        $revoked->logged_at = now('Asia/Aden')->subDays(2);
        $revoked->last_activity_at = now('Asia/Aden')->subDays(2);
        $revoked->expires_at = now('Asia/Aden')->subDay();
        $revoked->revoked_at = now('Asia/Aden')->subDay();
        $revoked->setRawToken(bin2hex(random_bytes(32)));
        $revoked->save();

        return [$user, $currentToken, $current, $other, $revoked];
    }

    public function test_authenticated_user_can_list_and_filter_own_sessions(): void
    {
        [$user, $token, $current, $other] = $this->createUserWithSessions();

        $headers = [
            'X-Session-Id' => $token,
            'X-Device-Uuid' => 'DEV-CURRENT-001',
            'X-Bypass-Encryption' => 'true',
        ];

        $response = $this->getJson('/api/v1/auth/sessions', $headers);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        $activeOnly = $this->getJson('/api/v1/auth/sessions?status='.UserSessionStatus::ACTIVE->value, $headers);
        $activeOnly->assertOk()->assertJsonCount(2, 'data');

        $search = $this->getJson('/api/v1/auth/sessions?search=10.0.0.55', $headers);
        $search->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.session_id', $other->session_id);
    }

    public function test_user_can_terminate_other_session_but_cannot_terminate_current_session(): void
    {
        [$user, $token, $current, $other] = $this->createUserWithSessions();

        $headers = [
            'X-Session-Id' => $token,
            'X-Device-Uuid' => 'DEV-CURRENT-001',
            'X-Bypass-Encryption' => 'true',
        ];

        $selfAttempt = $this->deleteJson("/api/v1/auth/sessions/{$current->session_id}", [], $headers);
        $selfAttempt->assertStatus(422);

        $terminateOther = $this->deleteJson("/api/v1/auth/sessions/{$other->session_id}", [], $headers);
        $terminateOther->assertOk()->assertJsonPath('success', true);

        $this->assertNotNull($other->fresh()->revoked_at);
    }
}
