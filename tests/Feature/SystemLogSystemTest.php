<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use App\Models\UserSession;
use App\Services\SystemLoggerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SystemLogSystemTest extends TestCase
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

    public function test_can_record_log_and_query_via_api(): void
    {
        $log = SystemLoggerService::log(
            'TEST_AUDIT',
            'CREATE',
            'User',
            (int) $this->user->user_id,
            null,
            ['status' => 'ACTIVE', 'role' => 'ADMIN'],
            (int) $this->user->user_id,
            '192.168.1.100',
            'PHPUnit Testing Agent'
        );

        $this->assertInstanceOf(SystemLog::class, $log);
        $this->assertEquals('TEST_AUDIT', $log->module);
        $this->assertEquals(['status' => 'ACTIVE', 'role' => 'ADMIN'], $log->new_values);

        // List API with search
        $response = $this->getJson('/api/v1/system-logs?module=TEST_AUDIT', $this->headers);
        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));

        // Show API
        $showResponse = $this->getJson("/api/v1/system-logs/{$log->log_id}", $this->headers);
        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.log_id', $log->log_id)
            ->assertJsonPath('data.module', 'TEST_AUDIT')
            ->assertJsonPath('data.entity_type', 'User')
            ->assertJsonPath('data.entity_id', (int) $this->user->user_id);
    }

    public function test_can_query_logs_by_entity_and_by_user(): void
    {
        SystemLoggerService::log(
            'ENTITY_TEST',
            'UPDATE',
            'Network',
            9999,
            ['name' => 'Old Net'],
            ['name' => 'New Net'],
            (int) $this->user->user_id
        );

        // By Entity
        $entityResponse = $this->getJson('/api/v1/system-logs/entity/Network/9999', $this->headers);
        $entityResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($entityResponse->json('data'));
        $this->assertEquals('ENTITY_TEST', $entityResponse->json('data.0.module'));

        // By User
        $userResponse = $this->getJson("/api/v1/system-logs/user/{$this->user->user_id}", $this->headers);
        $userResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($userResponse->json('data'));
    }

    public function test_distinct_modules_and_actions(): void
    {
        SystemLoggerService::log('MOD_ALPHA', 'ACTION_X');
        SystemLoggerService::log('MOD_BETA', 'ACTION_Y');

        $modResponse = $this->getJson('/api/v1/system-logs/modules', $this->headers);
        $modResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertContains('MOD_ALPHA', $modResponse->json('data.modules'));
        $this->assertContains('MOD_BETA', $modResponse->json('data.modules'));

        $actResponse = $this->getJson('/api/v1/system-logs/actions', $this->headers);
        $actResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertContains('ACTION_X', $actResponse->json('data.actions'));
        $this->assertContains('ACTION_Y', $actResponse->json('data.actions'));
    }

    public function test_system_logs_stats(): void
    {
        SystemLoggerService::log('STATS_MOD', 'STATS_ACT');

        $statsResponse = $this->getJson('/api/v1/system-logs/stats', $this->headers);
        $statsResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_logs',
                    'today_logs',
                    'modules_count',
                    'logs_by_module',
                    'logs_by_action',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $statsResponse->json('data.total_logs'));
    }
}
