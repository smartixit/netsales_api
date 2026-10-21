<?php

namespace Tests;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('session_fail:127.0.0.1');

        $this->withoutMiddleware([
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
        ]);
    }

    /**
     * إعداد جلسة مصادقة صالحة تشمل جهاز نشط وترويسات X-Session-Id و X-Device-Uuid.
     *
     * @return array{session: UserSession, headers: array<string, string>, device: UserDevice}
     */
    protected function createAuthenticatedSession(User $user, ?string $deviceUuid = null): array
    {
        $deviceUuid = $deviceUuid ?? 'PHPUNIT-DEV-'.uniqid();

        $device = UserDevice::create([
            'user_id' => $user->user_id,
            'device_uuid' => $deviceUuid,
            'device_name' => 'PHPUnit Test Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $session = UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $deviceUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $headers = [
            'X-Session-Id' => (string) $session->session_id,
            'X-Device-Uuid' => $deviceUuid,
            'X-Bypass-Encryption' => 'true',
        ];

        return [
            'session' => $session,
            'headers' => $headers,
            'device' => $device,
        ];
    }
}

