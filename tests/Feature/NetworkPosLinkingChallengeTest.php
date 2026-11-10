<?php

namespace Tests\Feature;

use App\Enums\LinkingChallengeStatus;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\Permission;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\CreatesActiveBillingSubscription;

class NetworkPosLinkingChallengeTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesActiveBillingSubscription;

    protected User $networkOwner;

    protected User $posOwner;

    protected User $unauthorizedUser;

    protected Network $network;

    protected Pos $pos;

    protected Pos $otherPos;

    protected array $netSession;

    protected array $posSession;

    protected array $unauthSession;

    protected function setUp(): void
    {
        parent::setUp();

        $gov = Governorate::first() ?? Governorate::create(['governorate_name' => 'Aden']);
        $dir = Directorate::first() ?? Directorate::create(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Crater']);

        $netRole = Role::where('role_key', RoleKey::NETWORK_OWNER->value)->first();
        $posRole = Role::where('role_key', RoleKey::POS_AGENT->value)->first();

        $netEditPerm = Permission::where('permission_key', 'networks.edit')->first();
        $netViewPerm = Permission::where('permission_key', 'networks.view')->first();
        $posEditPerm = Permission::where('permission_key', 'pos.edit')->first();
        $posViewPerm = Permission::where('permission_key', 'pos.view')->first();

        $permIdsNet = array_filter([$netEditPerm?->permission_id, $netViewPerm?->permission_id]);
        $permIdsPos = array_filter([$posEditPerm?->permission_id, $posViewPerm?->permission_id]);

        if ($netRole && ! empty($permIdsNet)) {
            $netRole->permissions()->syncWithoutDetaching($permIdsNet);
        }
        if ($posRole && ! empty($permIdsPos)) {
            $posRole->permissions()->syncWithoutDetaching($permIdsPos);
        }

        $this->networkOwner = User::create([
            'role_id' => null,
            'user_full_name' => 'Net Owner Challenge Test',
            'user_phone_number' => '77330000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        if (! empty($permIdsNet)) {
            $this->networkOwner->permissions()->syncWithoutDetaching($permIdsNet);
        }

        $this->posOwner = User::create([
            'role_id' => null,
            'user_full_name' => 'Pos Owner Challenge Test',
            'user_phone_number' => '77440000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        if (! empty($permIdsPos)) {
            $this->posOwner->permissions()->syncWithoutDetaching($permIdsPos);
        }

        $this->unauthorizedUser = User::create([
            'role_id' => null,
            'user_full_name' => 'Unauthorized User Test',
            'user_phone_number' => '77550000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->network = Network::create([
            'owner_id' => $this->networkOwner->user_id,
            'network_name' => 'شبكة الأمل للاختبار',
            'network_phone_number' => '771100000',
            'status' => NetworkStatus::ACTIVE,
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
        ]);

        $this->createActiveBillingSubscriptionForNetwork($this->network, [], null, $this->networkOwner);

        $this->pos = Pos::create([
            'owner_id' => $this->posOwner->user_id,
            'pos_name' => 'نقطة الأمل للاختبار',
            'pos_phone_number' => '773300000',
            'status' => PosStatus::ACTIVE,
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
        ]);

        $this->otherPos = Pos::create([
            'owner_id' => $this->unauthorizedUser->user_id,
            'pos_name' => 'نقطة طرف ثالث للاختبار',
            'pos_phone_number' => '775500000',
            'status' => PosStatus::ACTIVE,
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
        ]);

        NetworkUser::create([
            'network_id' => $this->network->network_id,
            'user_id' => $this->networkOwner->user_id,
            'role_id' => $netRole?->role_id,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $this->pos->pos_id,
            'user_id' => $this->posOwner->user_id,
            'role_id' => $posRole?->role_id,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $this->otherPos->pos_id,
            'user_id' => $this->unauthorizedUser->user_id,
            'role_id' => $posRole?->role_id,
            'status' => 'ACTIVE',
        ]);

        $this->netSession = $this->createAuthenticatedSession($this->networkOwner);
        $this->posSession = $this->createAuthenticatedSession($this->posOwner);
        $this->unauthSession = $this->createAuthenticatedSession($this->unauthorizedUser);
    }

    public function test_network_owner_can_create_linking_challenge(): void
    {
        $response = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'duration_minutes' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.network_id', $this->network->network_id)
            ->assertJsonPath('data.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.status', LinkingChallengeStatus::PENDING->value)
            ->assertJsonPath('data.status_label', 'قيد الانتظار');

        $payload = $response->json('data.payload');
        $this->assertNotEmpty($payload);
        $this->assertStringStartsWith('NTS_LINK_V1_', $payload);

        $uuid = $response->json('data.challenge_uuid');
        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $uuid,
            'network_id' => $this->network->network_id,
            'status' => LinkingChallengeStatus::PENDING->value,
        ]);
    }

    public function test_unauthorized_user_cannot_create_challenge_for_network(): void
    {
        $response = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
            ]);

        $this->assertTrue(in_array($response->status(), [403, 422]));
    }

    public function test_pos_owner_can_preview_valid_challenge(): void
    {
        // 1. Create challenge
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $payload = $createRes->json('data.payload');
        $uuid = $createRes->json('data.challenge_uuid');

        // 2. POS Owner previews
        $previewRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_id' => $this->pos->pos_id,
            ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.challenge_uuid', $uuid)
            ->assertJsonPath('data.network.network_id', $this->network->network_id)
            ->assertJsonPath('data.pos.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.is_targeted', true);
    }

    public function test_targeted_challenge_cannot_be_previewed_by_different_pos(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $payload = $createRes->json('data.payload');

        // Another POS attempts preview
        $previewRes = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_id' => $this->otherPos->pos_id,
            ]);

        $previewRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_id']);
    }

    public function test_pos_owner_can_confirm_challenge_and_establish_relationship(): void
    {
        // 1. Create challenge
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // 2. POS Owner confirms linking
        $confirmRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->pos->pos_id,
            ]);

        $confirmRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.network_id', $this->network->network_id)
            ->assertJsonPath('data.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.status', true);

        // 3. Verify challenge consumed
        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $uuid,
            'status' => LinkingChallengeStatus::CONSUMED->value,
            'consumed_by_user_id' => $this->posOwner->user_id,
            'consumed_by_pos_id' => $this->pos->pos_id,
        ]);

        // 4. Verify NetworkPos relationship created
        $this->assertDatabaseHas('network_pos', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        // 5. Verify auto-provisioned POS wallet
        $this->assertDatabaseHas('pos_wallets', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 0.00,
        ]);
    }

    public function test_challenge_cannot_be_consumed_twice_replay_protection(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // First confirmation succeeds
        $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->pos->pos_id,
            ])->assertStatus(201);

        // Second confirmation attempt fails
        $replayRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->pos->pos_id,
            ]);

        $replayRes->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_uuid']);
    }

    public function test_network_owner_can_revoke_challenge(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        $revokeRes = $this->withHeaders($this->netSession['headers'])
            ->deleteJson("/api/v1/network-pos/challenges/{$uuid}");

        $revokeRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $uuid,
            'status' => LinkingChallengeStatus::REVOKED->value,
        ]);
    }

    public function test_expired_challenge_preview_is_rejected(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $payload = $createRes->json('data.payload');
        $uuid = $createRes->json('data.challenge_uuid');

        // Simulate challenge expiry
        DB::table('network_pos_linking_challenges')
            ->where('challenge_uuid', $uuid)
            ->update(['expires_at' => now()->subMinutes(5)]);

        $previewRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_id' => $this->pos->pos_id,
            ]);

        $previewRes->assertStatus(422)
            ->assertJsonValidationErrors(['payload']);
        $this->assertStringContainsString('انتهت صلاحية', $previewRes->json('errors.payload.0'));
    }

    public function test_revoked_challenge_preview_is_rejected(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $payload = $createRes->json('data.payload');
        $uuid = $createRes->json('data.challenge_uuid');

        // Revoke the challenge
        $this->withHeaders($this->netSession['headers'])
            ->deleteJson("/api/v1/network-pos/challenges/{$uuid}")
            ->assertStatus(200);

        // Preview should now be rejected
        $previewRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_id' => $this->pos->pos_id,
            ]);

        $previewRes->assertStatus(422)
            ->assertJsonValidationErrors(['payload']);
        $this->assertStringContainsString('غير صالحة أو تم استخدامها', $previewRes->json('errors.payload.0'));
    }

    public function test_confirmation_after_revocation_is_rejected(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // Revoke
        $this->withHeaders($this->netSession['headers'])
            ->deleteJson("/api/v1/network-pos/challenges/{$uuid}")
            ->assertStatus(200);

        // Confirmation attempt must fail
        $confirmRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->pos->pos_id,
            ]);

        $confirmRes->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_uuid']);
        $this->assertStringContainsString('تم استخدام دعوة الربط هذه مسبقاً أو تم إلغاؤها', $confirmRes->json('errors.challenge_uuid.0'));
    }

    public function test_challenge_expiry_between_preview_and_confirmation_is_rejected(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $payload = $createRes->json('data.payload');
        $uuid = $createRes->json('data.challenge_uuid');

        // Preview succeeds initially
        $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_id' => $this->pos->pos_id,
            ])->assertStatus(200);

        // Expiry happens right after preview before confirm
        DB::table('network_pos_linking_challenges')
            ->where('challenge_uuid', $uuid)
            ->update(['expires_at' => now()->subSeconds(10)]);

        // Confirm fails on server validation
        $confirmRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->pos->pos_id,
            ]);

        $confirmRes->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_uuid']);
        $this->assertStringContainsString('انتهت صلاحية', $confirmRes->json('errors.challenge_uuid.0'));
    }

    public function test_confirmation_by_unauthorized_pos_owner_is_rejected(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // Unauthorized user attempts to confirm for a POS they do not own or manage
        $confirmRes = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->pos->pos_id,
            ]);

        $confirmRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_id']);
        $this->assertStringContainsString('غير مصرح لك بإدارة نقطة البيع هذه', $confirmRes->json('errors.pos_id.0'));
    }

    public function test_confirmation_from_different_pos_than_targeted_pos_is_rejected(): void
    {
        // Targeted to $this->pos
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // Confirm attempt using otherPos
        $confirmRes = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->otherPos->pos_id,
            ]);

        $confirmRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_id']);
        $this->assertStringContainsString('مخصصة لنقطة بيع أخرى', $confirmRes->json('errors.pos_id.0'));
    }

    public function test_tampered_or_malformed_challenge_payload_is_rejected(): void
    {
        $previewRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => 'NTS_LINK_V1_TAMPERED_CIPHERTEXT_12345',
                'pos_id' => $this->pos->pos_id,
            ]);

        $previewRes->assertStatus(422)
            ->assertJsonValidationErrors(['payload']);
        $this->assertStringContainsString('رمز QR غير صالح أو مشوه', $previewRes->json('errors.payload.0'));
    }

    public function test_existing_network_pos_relationship_blocks_challenge_and_confirmation(): void
    {
        // Pre-create link
        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        // Attempt to create targeted challenge fails
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $createRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_id']);
        $this->assertStringContainsString('مرتبطة بالفعل', $createRes->json('errors.pos_id.0'));
    }

    public function test_cross_tenant_or_cross_owner_revocation_is_blocked(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // 1. User without networks.edit permission gets 403 Forbidden
        $unauthRes = $this->withHeaders($this->unauthSession['headers'])
            ->deleteJson("/api/v1/network-pos/challenges/{$uuid}");
        $this->assertSame(403, $unauthRes->status());

        // 2. Network Owner B from a different network (who has networks.edit) gets 422 IDOR rejection
        $netRole = Role::where('role_key', RoleKey::NETWORK_OWNER->value)->first();
        $netEditPerm = Permission::where('permission_key', 'networks.edit')->first();
        $ownerB = User::create([
            'role_id' => null,
            'user_full_name' => 'Cross Tenant Net Owner B',
            'user_phone_number' => '77990000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        if ($netEditPerm) {
            $ownerB->permissions()->syncWithoutDetaching([$netEditPerm->permission_id]);
        }
        $sessionB = $this->createAuthenticatedSession($ownerB);

        $crossTenantRes = $this->withHeaders($sessionB['headers'])
            ->deleteJson("/api/v1/network-pos/challenges/{$uuid}");

        $crossTenantRes->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_uuid']);
        $this->assertStringContainsString('غير مصرح لك بإلغاء دعوة الربط هذه', $crossTenantRes->json('errors.challenge_uuid.0'));
    }

    public function test_row_locking_and_concurrent_duplicate_confirmation_guarantees_single_consumption(): void
    {
        // 1. Create challenge
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);
        $uuid = $createRes->json('data.challenge_uuid');

        // 2. Verify InnoDB row-level locking semantics on MySQL
        DB::beginTransaction();
        $lockedRow = DB::table('network_pos_linking_challenges')
            ->where('challenge_uuid', $uuid)
            ->lockForUpdate()
            ->first();
        $this->assertNotNull($lockedRow);

        // Open an independent second PDO connection to verify that the lock blocks concurrent lock acquisition
        $config = config('database.connections.mysql');
        $pdo2 = new \PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            $config['username'],
            $config['password']
        );
        $pdo2->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');

        $lockBlocked = false;
        try {
            $stmt = $pdo2->prepare('SELECT * FROM network_pos_linking_challenges WHERE challenge_uuid = :uuid FOR UPDATE');
            $stmt->execute(['uuid' => $uuid]);
        } catch (\PDOException $e) {
            $lockBlocked = true;
            $this->assertStringContainsString('1205', $e->getMessage());
        }

        $this->assertTrue($lockBlocked, 'Pessimistic lock (SELECT ... FOR UPDATE) must block concurrent transactions on MySQL InnoDB.');

        // Roll back the holding transaction
        DB::rollBack();

        // 3. Confirm challenge via API
        $confirmRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->pos->pos_id,
            ]);
        $confirmRes->assertStatus(201);

        // 4. Any concurrent or repeated confirmation attempt fails immediately
        $secondConfirm = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_id' => $this->pos->pos_id,
            ]);
        $secondConfirm->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_uuid']);

        // 5. Database uniqueness guarantee: network_pos has exactly 1 row
        $this->assertSame(
            1,
            DB::table('network_pos')
                ->where('network_id', $this->network->network_id)
                ->where('pos_id', $this->pos->pos_id)
                ->count()
        );
    }
}
