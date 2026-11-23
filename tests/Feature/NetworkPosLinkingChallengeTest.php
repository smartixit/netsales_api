<?php

namespace Tests\Feature;

use App\Enums\LinkingChallengeStatus;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleKey;
use App\Enums\RoleScope;
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
use Tests\Concerns\CreatesActiveBillingSubscription;
use Tests\TestCase;

class NetworkPosLinkingChallengeTest extends TestCase
{
    use CreatesActiveBillingSubscription;
    use DatabaseTransactions;

    protected User $networkOwner;

    protected User $posOwner;

    protected User $unauthorizedUser;

    protected Network $network;

    protected Pos $pos;

    protected Pos $otherPos;

    /** @var array<string, mixed> */
    protected array $netSession;

    /** @var array<string, mixed> */
    protected array $posSession;

    /** @var array<string, mixed> */
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

        $this->networkOwner = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Net Owner Challenge Test',
            'user_phone_number' => '77330000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        if (! empty($permIdsNet)) {
            $this->networkOwner->permissions()->syncWithoutDetaching($permIdsNet);
        }

        $this->posOwner = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'Pos Owner Challenge Test',
            'user_phone_number' => '77440000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        if (! empty($permIdsPos)) {
            $this->posOwner->permissions()->syncWithoutDetaching($permIdsPos);
        }

        $this->unauthorizedUser = $this->createUserWithScope(RoleScope::POS, [
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
                'pos_uuid' => $this->pos->pos_uuid,

                'duration_minutes' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.network_id', $this->network->network_id)
            ->assertJsonPath('data.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.network.network_id', $this->network->network_id)
            ->assertJsonPath('data.network.network_name', $this->network->network_name)
            ->assertJsonPath('data.pos.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.pos.pos_uuid', $this->pos->pos_uuid)
            ->assertJsonPath('data.pos.pos_name', $this->pos->pos_name)
            ->assertJsonPath('data.status', LinkingChallengeStatus::PENDING->value)
            ->assertJsonPath('data.status_label', 'قيد الانتظار');

        $networkData = $response->json('data.network');
        $posData = $response->json('data.pos');
        $this->assertArrayNotHasKey('owner_id', $networkData);
        $this->assertArrayNotHasKey('network_phone_number', $networkData);
        $this->assertArrayNotHasKey('owner_id', $posData);
        $this->assertArrayNotHasKey('pos_phone_number', $posData);
        $this->assertArrayNotHasKey('pos_email', $posData);

        $payload = $response->json('data.payload');
        $this->assertNotEmpty($payload);
        $this->assertStringStartsWith('NTS_LINK_V1_', $payload);

        $uuid = $response->json('data.challenge_uuid');
        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $uuid,
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => LinkingChallengeStatus::PENDING->value,
        ]);
    }

    public function test_network_owner_can_create_challenge_using_resolved_pos_business_code(): void
    {
        $response = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.pos.pos_uuid', $this->pos->pos_uuid)
            ->assertJsonPath('data.pos.pos_name', $this->pos->pos_name);

        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $response->json('data.challenge_uuid'),
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => LinkingChallengeStatus::PENDING->value,
        ]);
    }

    public function test_network_owner_cannot_create_an_untargeted_invitation(): void
    {
        $response = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pos_uuid']);
    }

    public function test_network_owner_create_challenge_response_contains_minimal_target_projection(): void
    {
        $response = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.network_id', $this->network->network_id)
            ->assertJsonPath('data.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.pos.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.pos.pos_uuid', $this->pos->pos_uuid)
            ->assertJsonPath('data.pos.pos_name', $this->pos->pos_name)
            ->assertJsonMissingPath('data.pos.pos_phone_number')
            ->assertJsonMissingPath('data.pos.owner_id')
            ->assertJsonMissingPath('data.pos.owner');

        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $response->json('data.challenge_uuid'),
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => LinkingChallengeStatus::PENDING->value,
        ]);
    }

    public function test_network_owner_cannot_create_challenge_with_internal_pos_id(): void
    {
        $response = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pos_id']);
    }

    public function test_all_challenge_requests_reject_internal_pos_id_without_pos_uuid(): void
    {
        $createWithoutBusinessCode = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
            ]);

        $createWithoutBusinessCode->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_id', 'pos_uuid']);

        $validChallenge = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $validChallenge->assertCreated();
        $challengeUuid = $validChallenge->json('data.challenge_uuid');
        $payload = $validChallenge->json('data.payload');

        $previewWithoutBusinessCode = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_id' => $this->pos->pos_id,
            ]);

        $previewWithoutBusinessCode->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_id', 'pos_uuid']);

        $confirmWithoutBusinessCode = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $challengeUuid,
                'pos_id' => $this->pos->pos_id,
            ]);

        $confirmWithoutBusinessCode->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_id', 'pos_uuid']);

        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $challengeUuid,
            'status' => LinkingChallengeStatus::PENDING->value,
        ]);
        $this->assertDatabaseMissing('network_pos', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
        ]);
    }

    public function test_network_owner_can_resolve_exact_eligible_pos_business_code(): void
    {
        $response = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/resolve-target', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.pos_name', $this->pos->pos_name)
            ->assertJsonPath('data.pos_uuid', $this->pos->pos_uuid)
            ->assertJsonMissingPath('data.pos_phone_number')
            ->assertJsonMissingPath('data.owner');
        $response->assertJsonMissingPath('data.owner_id')
            ->assertJsonMissingPath('data.governorate_name')
            ->assertJsonMissingPath('data.directorate_name');
    }

    public function test_search_rejects_terms_that_become_empty_or_too_short_after_trimming(): void
    {
        foreach (['%20%20', '%20a%20'] as $search) {
            $response = $this->withHeaders($this->netSession['headers'])
                ->getJson('/api/v1/network-pos/challenges/linkable-targets?network_id='.$this->network->network_id.'&search='.$search);

            $response->assertUnprocessable()->assertJsonValidationErrors(['search']);
        }
    }

    public function test_search_treats_like_wildcards_as_literal_characters(): void
    {
        $response = $this->withHeaders($this->netSession['headers'])
            ->getJson('/api/v1/network-pos/challenges/linkable-targets?network_id='.$this->network->network_id.'&search=%25_');

        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_network_owner_can_search_only_eligible_pos_targets_for_network(): void
    {
        $response = $this->withHeaders($this->netSession['headers'])
            ->getJson('/api/v1/network-pos/challenges/linkable-targets?network_id='.$this->network->network_id.'&search=الأمل');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.0.pos_uuid', $this->pos->pos_uuid)
            ->assertJsonPath('data.0.pos_name', $this->pos->pos_name)
            ->assertJsonMissingPath('data.0.owner_id')
            ->assertJsonMissingPath('data.0.pos_phone_number');
    }

    public function test_eligible_pos_search_uses_cursor_pagination(): void
    {
        $secondPosOwner = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'Second POS Owner Cursor Test',
            'user_phone_number' => '77660000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $secondPos = Pos::create([
            'owner_id' => $secondPosOwner->user_id,
            'pos_name' => 'نقطة الأمل الثانية للاختبار',
            'pos_phone_number' => '773300001',
            'status' => PosStatus::ACTIVE,
            'governorate_id' => $this->network->governorate_id,
            'directorate_id' => $this->network->directorate_id,
        ]);

        $firstPage = $this->withHeaders($this->netSession['headers'])
            ->getJson('/api/v1/network-pos/challenges/linkable-targets?network_id='.$this->network->network_id.'&search=الأمل&per_page=1');

        $firstPage->assertOk()->assertJsonCount(1, 'data');
        $cursor = $firstPage->json('next_cursor');
        $this->assertNotEmpty($cursor);
        $this->assertSame($secondPos->pos_id, $firstPage->json('data.0.pos_id'));

        $secondPage = $this->withHeaders($this->netSession['headers'])
            ->getJson('/api/v1/network-pos/challenges/linkable-targets?network_id='.$this->network->network_id.'&search=الأمل&per_page=1&cursor='.urlencode($cursor));

        $secondPage->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($this->pos->pos_id, $secondPage->json('data.0.pos_id'));
        $this->assertNull($secondPage->json('next_cursor'));
    }

    public function test_search_excludes_pos_already_linked_to_selected_network(): void
    {
        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => false,
        ]);

        $response = $this->withHeaders($this->netSession['headers'])
            ->getJson('/api/v1/network-pos/challenges/linkable-targets?network_id='.$this->network->network_id.'&search=الأمل');

        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_search_excludes_pos_with_active_pending_invitation(): void
    {
        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $createResponse->assertCreated();

        $response = $this->withHeaders($this->netSession['headers'])
            ->getJson('/api/v1/network-pos/challenges/linkable-targets?network_id='.$this->network->network_id.'&search=الأمل');

        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_network_owner_cannot_create_second_active_challenge_for_same_pos(): void
    {
        $first = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);
        $first->assertCreated();

        $second = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $second->assertUnprocessable()->assertJsonValidationErrors(['pos_uuid']);
    }

    public function test_user_without_network_membership_cannot_search_linkable_pos_targets(): void
    {
        $response = $this->withHeaders($this->unauthSession['headers'])
            ->getJson('/api/v1/network-pos/challenges/linkable-targets?network_id='.$this->network->network_id.'&search=الأمل');

        $this->assertTrue(in_array($response->status(), [403, 422]));
    }

    public function test_network_owner_cannot_resolve_already_linked_pos_target(): void
    {
        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        $response = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/resolve-target', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target']);
    }

    public function test_unauthorized_user_cannot_create_challenge_for_network(): void
    {
        $response = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $this->assertTrue(in_array($response->status(), [403, 422]));
    }

    public function test_pos_owner_can_preview_valid_challenge(): void
    {
        // 1. Create challenge
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,

            ]);

        $payload = $createRes->json('data.payload');
        $uuid = $createRes->json('data.challenge_uuid');

        // 2. POS Owner previews
        $previewRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.challenge_uuid', $uuid)
            ->assertJsonPath('data.network.network_id', $this->network->network_id)
            ->assertJsonPath('data.pos.pos_id', $this->pos->pos_id)
            ->assertJsonPath('data.is_targeted', true);
    }

    public function test_preview_rejects_internal_pos_id(): void
    {
        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $createResponse->assertCreated();

        $response = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $createResponse->json('data.payload'),
                'pos_id' => $this->pos->pos_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_id']);
    }

    public function test_targeted_challenge_cannot_be_previewed_by_different_pos(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,

            ]);

        $payload = $createRes->json('data.payload');

        // Another POS attempts preview
        $previewRes = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_uuid' => $this->otherPos->pos_uuid,
            ]);

        $previewRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_uuid']);
    }

    public function test_suspended_pos_membership_cannot_preview_a_challenge(): void
    {
        $posRole = Role::where('role_key', RoleKey::POS_AGENT->value)->first();
        PosUser::create([
            'pos_id' => $this->pos->pos_id,
            'user_id' => $this->unauthorizedUser->user_id,
            'role_id' => $posRole?->role_id,
            'status' => 'SUSPENDED',
        ]);

        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);
        $createResponse->assertCreated();

        $response = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $createResponse->json('data.payload'),
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_uuid']);
    }

    public function test_pos_owner_can_confirm_challenge_and_establish_relationship(): void
    {
        // 1. Create challenge
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,

            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // 2. POS Owner confirms linking
        $confirmRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_uuid' => $this->pos->pos_uuid,
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

    public function test_confirmation_rejects_internal_pos_id(): void
    {
        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $createResponse->assertCreated();
        $challengeUuid = $createResponse->json('data.challenge_uuid');

        $response = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $challengeUuid,
                'pos_id' => $this->pos->pos_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_id']);

        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $challengeUuid,
            'status' => LinkingChallengeStatus::PENDING->value,
        ]);
        $this->assertDatabaseMissing('network_pos', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
        ]);
    }

    public function test_suspended_pos_membership_cannot_confirm_a_challenge(): void
    {
        $posRole = Role::where('role_key', RoleKey::POS_AGENT->value)->first();
        PosUser::create([
            'pos_id' => $this->pos->pos_id,
            'user_id' => $this->unauthorizedUser->user_id,
            'role_id' => $posRole?->role_id,
            'status' => 'SUSPENDED',
        ]);

        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);
        $createResponse->assertCreated();
        $challengeUuid = $createResponse->json('data.challenge_uuid');

        $response = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $challengeUuid,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pos_uuid']);
        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $challengeUuid,
            'status' => LinkingChallengeStatus::PENDING->value,
        ]);
        $this->assertDatabaseMissing('network_pos', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
        ]);
    }

    public function test_challenge_cannot_be_consumed_twice_replay_protection(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,

            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // First confirmation succeeds
        $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_uuid' => $this->pos->pos_uuid,
            ])->assertStatus(201);

        // Second confirmation attempt fails
        $replayRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $replayRes->assertStatus(422)
            ->assertJsonValidationErrors(['challenge_uuid']);
    }

    public function test_network_owner_can_revoke_challenge(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
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
                'pos_uuid' => $this->pos->pos_uuid,

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
                'pos_uuid' => $this->pos->pos_uuid,
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
                'pos_uuid' => $this->pos->pos_uuid,

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
                'pos_uuid' => $this->pos->pos_uuid,
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
                'pos_uuid' => $this->pos->pos_uuid,

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
                'pos_uuid' => $this->pos->pos_uuid,
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
                'pos_uuid' => $this->pos->pos_uuid,

            ]);

        $payload = $createRes->json('data.payload');
        $uuid = $createRes->json('data.challenge_uuid');

        // Preview succeeds initially
        $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => $payload,
                'pos_uuid' => $this->pos->pos_uuid,
            ])->assertStatus(200);

        // Expiry happens right after preview before confirm
        DB::table('network_pos_linking_challenges')
            ->where('challenge_uuid', $uuid)
            ->update(['expires_at' => now()->subSeconds(10)]);

        // Confirm fails on server validation
        $confirmRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_uuid' => $this->pos->pos_uuid,
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
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // Unauthorized user attempts to confirm for a POS they do not own or manage
        $confirmRes = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $confirmRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_uuid']);
        $this->assertStringContainsString('غير مصرح لك بإدارة نقطة البيع هذه', $confirmRes->json('errors.pos_uuid.0'));
    }

    public function test_confirmation_from_different_pos_than_targeted_pos_is_rejected(): void
    {
        // Targeted to $this->pos
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,

            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // Confirm attempt using otherPos
        $confirmRes = $this->withHeaders($this->unauthSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_uuid' => $this->otherPos->pos_uuid,
            ]);

        $confirmRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_uuid']);
        $this->assertStringContainsString('مخصصة لنقطة بيع أخرى', $confirmRes->json('errors.pos_uuid.0'));
    }

    public function test_tampered_or_malformed_challenge_payload_is_rejected(): void
    {
        $previewRes = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/preview', [
                'payload' => 'NTS_LINK_V1_TAMPERED_CIPHERTEXT_12345',
                'pos_uuid' => $this->pos->pos_uuid,
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
                'pos_uuid' => $this->pos->pos_uuid,

            ]);

        $createRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_uuid']);
        $this->assertStringContainsString('مرتبطة بالفعل', $createRes->json('errors.pos_uuid.0'));
    }

    public function test_cross_tenant_or_cross_owner_revocation_is_blocked(): void
    {
        $createRes = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $uuid = $createRes->json('data.challenge_uuid');

        // 1. User without networks.edit permission gets 403 Forbidden
        $unauthRes = $this->withHeaders($this->unauthSession['headers'])
            ->deleteJson("/api/v1/network-pos/challenges/{$uuid}");
        $this->assertSame(403, $unauthRes->status());

        // 2. Network Owner B from a different network (who has networks.edit) gets 422 IDOR rejection
        $netRole = Role::where('role_key', RoleKey::NETWORK_OWNER->value)->first();
        $netEditPerm = Permission::where('permission_key', 'networks.edit')->first();
        $ownerB = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Cross Tenant Net Owner B',
            'user_phone_number' => '77990000'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        if ($netEditPerm) {
            $ownerB->permissions()->syncWithoutDetaching([$netEditPerm->permission_id]);
        }
        Network::query()->create([
            'owner_id' => $ownerB->user_id,
            'network_name' => 'Cross Tenant Network B '.uniqid(),
            'network_phone_number' => '77'.random_int(10000000, 99999999),
            'governorate_id' => $this->network->governorate_id,
            'directorate_id' => $this->network->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);
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
                'pos_uuid' => $this->pos->pos_uuid,

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
                'pos_uuid' => $this->pos->pos_uuid,
            ]);
        $confirmRes->assertStatus(201);

        // 4. Any concurrent or repeated confirmation attempt fails immediately
        $secondConfirm = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_uuid' => $this->pos->pos_uuid,
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
    public function test_consumed_challenge_cannot_be_revoked(): void
    {
        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $createResponse->assertCreated();
        $uuid = $createResponse->json('data.challenge_uuid');

        $confirmResponse = $this->withHeaders($this->posSession['headers'])
            ->postJson('/api/v1/network-pos/challenges/confirm', [
                'challenge_uuid' => $uuid,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);
        $confirmResponse->assertCreated();

        $revokeResponse = $this->withHeaders($this->netSession['headers'])
            ->deleteJson("/api/v1/network-pos/challenges/{$uuid}");

        $revokeResponse->assertNotFound();
        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $uuid,
            'status' => LinkingChallengeStatus::CONSUMED->value,
        ]);
    }

    public function test_expired_challenge_is_marked_expired_instead_of_revoked(): void
    {
        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $createResponse->assertCreated();
        $uuid = $createResponse->json('data.challenge_uuid');

        DB::table('network_pos_linking_challenges')
            ->where('challenge_uuid', $uuid)
            ->update(['expires_at' => now('Asia/Aden')->subMinute()]);

        $revokeResponse = $this->withHeaders($this->netSession['headers'])
            ->deleteJson("/api/v1/network-pos/challenges/{$uuid}");

        $revokeResponse->assertNotFound();
        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $uuid,
            'status' => LinkingChallengeStatus::EXPIRED->value,
        ]);
    }

    public function test_network_owner_can_read_safe_challenge_status_without_secret_fields(): void
    {
        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,

            ]);

        $createResponse->assertCreated();

        $uuid = $createResponse->json('data.challenge_uuid');

        $statusResponse = $this->withHeaders($this->netSession['headers'])
            ->getJson("/api/v1/network-pos/challenges/{$uuid}");

        $statusResponse->assertOk()
            ->assertJsonPath('data.status', LinkingChallengeStatus::PENDING->value)
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'status_label',
                    'expires_at',
                ],
            ])
            ->assertJsonMissingPath('data.payload')
            ->assertJsonMissingPath('data.challenge_uuid')
            ->assertJsonMissingPath('data.network_id')
            ->assertJsonMissingPath('data.pos_id');
    }

    public function test_status_read_marks_expired_pending_challenge_as_expired(): void
    {
        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $createResponse->assertCreated();
        $uuid = $createResponse->json('data.challenge_uuid');

        DB::table('network_pos_linking_challenges')
            ->where('challenge_uuid', $uuid)
            ->update(['expires_at' => now('Asia/Aden')->subMinute()]);

        $statusResponse = $this->withHeaders($this->netSession['headers'])
            ->getJson("/api/v1/network-pos/challenges/{$uuid}");

        $statusResponse->assertOk()
            ->assertJsonPath('data.status', LinkingChallengeStatus::EXPIRED->value);

        $this->assertDatabaseHas('network_pos_linking_challenges', [
            'challenge_uuid' => $uuid,
            'status' => LinkingChallengeStatus::EXPIRED->value,
        ]);
    }

    public function test_user_outside_network_cannot_read_challenge_status(): void
    {
        $createResponse = $this->withHeaders($this->netSession['headers'])
            ->postJson('/api/v1/network-pos/challenges', [
                'network_id' => $this->network->network_id,
                'pos_uuid' => $this->pos->pos_uuid,
            ]);

        $createResponse->assertCreated();

        $uuid = $createResponse->json('data.challenge_uuid');

        $statusResponse = $this->withHeaders($this->unauthSession['headers'])
            ->getJson("/api/v1/network-pos/challenges/{$uuid}");

        $this->assertTrue(
            in_array($statusResponse->status(), [403, 422], true),
            'A user outside the network must not read its challenge status.'
        );
        if ($statusResponse->status() === 422) {
            $statusResponse->assertJsonValidationErrors(['challenge_uuid']);
        }
    }

}
