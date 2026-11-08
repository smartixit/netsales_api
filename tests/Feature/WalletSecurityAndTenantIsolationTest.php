<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\UserStatus;
use App\Models\Network;
use App\Models\NetworkUser;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\PosWallet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WalletSecurityAndTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function createAuthenticatedUser(RoleKey $roleKey, string $suffix = '1'): array
    {
        $role = Role::firstOrCreate(
            ['role_key' => $roleKey->value],
            [
                'role_name' => $roleKey->value,
                'scope' => str_starts_with($roleKey->value, 'NETWORK') ? 'NETWORK' : (str_starts_with($roleKey->value, 'POS') ? 'POS' : 'PLATFORM'),
                'is_system' => true,
                'status' => true,
            ]
        );

        $user = User::create([
            'role_id' => $role->scope === 'PLATFORM' ? $role->role_id : null,
            'user_full_name' => "User {$roleKey->value} {$suffix}",
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_password' => bcrypt('password123'),
            'status' => UserStatus::ACTIVE,
        ]);

        $authSession = $this->createAuthenticatedSession($user);

        return [
            'user' => $user,
            'role' => $role,
            'headers' => array_merge($authSession['headers'], [
                'Accept' => 'application/json',
            ]),
        ];
    }

    public function test_network_owner_can_view_own_wallet_and_logs(): void
    {
        $auth = $this->createAuthenticatedUser(RoleKey::NETWORK_OWNER, 'owner');
        $user = $auth['user'];

        $network = Network::create([
            'owner_id' => $user->user_id,
            'network_name' => 'Net Test Alpha',
            'network_phone_number' => '771234567',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::create([
            'network_id' => $network->network_id,
            'user_id' => $user->user_id,
            'role_id' => $auth['role']->role_id,
            'status' => 'ACTIVE',
        ]);

        $wallet = NetworkWallet::create([
            'network_id' => $network->network_id,
            'wallet_uuid' => 'NET-WALL-1',
            'wallet_balance' => 50000.0,
            'status' => true,
        ]);

        $response = $this->withHeaders($auth['headers'])->getJson("/api/v1/network-wallets/{$wallet->wallet_id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.wallet_id', $wallet->wallet_id);
        $this->assertEquals(50000, $response->json('data.wallet_balance'));

        $logsResponse = $this->withHeaders($auth['headers'])->getJson("/api/v1/network-wallets/{$wallet->wallet_id}/logs");
        $logsResponse->assertStatus(200);

        $byNetResponse = $this->withHeaders($auth['headers'])->getJson("/api/v1/network-wallets/network/{$network->network_id}");
        $byNetResponse->assertStatus(200);
    }

    public function test_cross_network_wallet_access_is_strictly_forbidden(): void
    {
        $attackerAuth = $this->createAuthenticatedUser(RoleKey::NETWORK_OWNER, 'attacker');
        $victimAuth = $this->createAuthenticatedUser(RoleKey::NETWORK_OWNER, 'victim');

        $victimNetwork = Network::create([
            'owner_id' => $victimAuth['user']->user_id,
            'network_name' => 'Victim Net',
            'network_phone_number' => '779999999',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::create([
            'network_id' => $victimNetwork->network_id,
            'user_id' => $victimAuth['user']->user_id,
            'role_id' => $victimAuth['role']->role_id,
            'status' => 'ACTIVE',
        ]);

        $victimWallet = NetworkWallet::create([
            'network_id' => $victimNetwork->network_id,
            'wallet_uuid' => 'NET-WALL-VICTIM',
            'wallet_balance' => 999999.0,
            'status' => true,
        ]);

        // Attacker attempts to view victim's wallet
        $response = $this->withHeaders($attackerAuth['headers'])->getJson("/api/v1/network-wallets/{$victimWallet->wallet_id}");
        $response->assertStatus(403);

        // Attacker attempts to view victim's logs
        $logsResponse = $this->withHeaders($attackerAuth['headers'])->getJson("/api/v1/network-wallets/{$victimWallet->wallet_id}/logs");
        $logsResponse->assertStatus(403);

        // Attacker attempts to view victim's wallet by networkId
        $byNetResponse = $this->withHeaders($attackerAuth['headers'])->getJson("/api/v1/network-wallets/network/{$victimNetwork->network_id}");
        $byNetResponse->assertStatus(403);
    }

    public function test_pos_agent_can_view_own_pos_wallet_and_logs(): void
    {
        $netOwnerAuth = $this->createAuthenticatedUser(RoleKey::NETWORK_OWNER, 'net');
        $posAgentAuth = $this->createAuthenticatedUser(RoleKey::POS_AGENT, 'pos');

        $network = Network::create([
            'owner_id' => $netOwnerAuth['user']->user_id,
            'network_name' => 'Alpha Net',
            'network_phone_number' => '771111111',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        $pos = Pos::create([
            'owner_id' => $posAgentAuth['user']->user_id,
            'pos_name' => 'Alpha POS',
            'pos_phone_number' => '712222222',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $pos->pos_id,
            'user_id' => $posAgentAuth['user']->user_id,
            'role_id' => $posAgentAuth['role']->role_id,
            'status' => 'ACTIVE',
        ]);

        $posWallet = PosWallet::create([
            'network_id' => $network->network_id,
            'pos_id' => $pos->pos_id,
            'wallet_uuid' => 'POS-WALL-1',
            'wallet_balance' => 12000.0,
            'status' => true,
        ]);

        $response = $this->withHeaders($posAgentAuth['headers'])->getJson("/api/v1/pos-wallets/{$posWallet->wallet_id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.wallet_id', $posWallet->wallet_id);
        $this->assertEquals(12000, $response->json('data.wallet_balance'));

        $logsResponse = $this->withHeaders($posAgentAuth['headers'])->getJson("/api/v1/pos-wallets/{$posWallet->wallet_id}/logs");
        $logsResponse->assertStatus(200);

        $byNetPosResponse = $this->withHeaders($posAgentAuth['headers'])->getJson("/api/v1/pos-wallets/network/{$network->network_id}/pos/{$pos->pos_id}");
        $byNetPosResponse->assertStatus(200);
    }

    public function test_network_wallet_lists_are_scoped_to_owned_or_active_network_memberships(): void
    {
        $ownerA = $this->createAuthenticatedUser(RoleKey::NETWORK_OWNER, 'list-a');
        $ownerB = $this->createAuthenticatedUser(RoleKey::NETWORK_OWNER, 'list-b');

        $networkA = Network::create([
            'owner_id' => $ownerA['user']->user_id,
            'network_name' => 'Network List A',
            'network_phone_number' => '771110001',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);
        $networkB = Network::create([
            'owner_id' => $ownerB['user']->user_id,
            'network_name' => 'Network List B',
            'network_phone_number' => '771110002',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::create([
            'network_id' => $networkA->network_id,
            'user_id' => $ownerA['user']->user_id,
            'role_id' => $ownerA['role']->role_id,
            'status' => 'ACTIVE',
        ]);
        NetworkUser::create([
            'network_id' => $networkB->network_id,
            'user_id' => $ownerB['user']->user_id,
            'role_id' => $ownerB['role']->role_id,
            'status' => 'ACTIVE',
        ]);

        $walletA = NetworkWallet::create([
            'network_id' => $networkA->network_id,
            'wallet_uuid' => 'NET-LIST-A',
            'wallet_balance' => 1000,
            'status' => true,
        ]);
        $walletB = NetworkWallet::create([
            'network_id' => $networkB->network_id,
            'wallet_uuid' => 'NET-LIST-B',
            'wallet_balance' => 2000,
            'status' => true,
        ]);

        // No client-side tenant filter: the server must return only Network A.
        $response = $this->withHeaders($ownerA['headers'])->getJson('/api/v1/network-wallets?all=true');
        $response->assertStatus(200)
            ->assertJsonFragment(['wallet_id' => $walletA->wallet_id])
            ->assertJsonMissing(['wallet_id' => $walletB->wallet_id]);

        // Supplying the victim's network ID must further narrow, never widen, scope.
        $crossTenantFilter = $this->withHeaders($ownerA['headers'])
            ->getJson('/api/v1/network-wallets?all=true&network_id='.$networkB->network_id);
        $crossTenantFilter->assertStatus(200)
            ->assertJsonMissing(['wallet_id' => $walletA->wallet_id])
            ->assertJsonMissing(['wallet_id' => $walletB->wallet_id]);
    }

    public function test_pos_wallet_lists_are_scoped_to_owned_pos_or_active_pos_memberships(): void
    {
        $networkOwner = $this->createAuthenticatedUser(RoleKey::NETWORK_OWNER, 'pos-list-net');
        $posOwnerA = $this->createAuthenticatedUser(RoleKey::POS_AGENT, 'pos-list-a');
        $posOwnerB = $this->createAuthenticatedUser(RoleKey::POS_AGENT, 'pos-list-b');

        $network = Network::create([
            'owner_id' => $networkOwner['user']->user_id,
            'network_name' => 'POS Wallet List Network',
            'network_phone_number' => '772220001',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        $posA = Pos::create([
            'owner_id' => $posOwnerA['user']->user_id,
            'pos_name' => 'Owned POS A',
            'pos_phone_number' => '712220001',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);
        $posB = Pos::create([
            'owner_id' => $posOwnerB['user']->user_id,
            'pos_name' => 'Victim POS B',
            'pos_phone_number' => '712220002',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $posA->pos_id,
            'user_id' => $posOwnerA['user']->user_id,
            'role_id' => $posOwnerA['role']->role_id,
            'status' => 'ACTIVE',
        ]);
        PosUser::create([
            'pos_id' => $posB->pos_id,
            'user_id' => $posOwnerB['user']->user_id,
            'role_id' => $posOwnerB['role']->role_id,
            'status' => 'ACTIVE',
        ]);

        $walletA = PosWallet::create([
            'network_id' => $network->network_id,
            'pos_id' => $posA->pos_id,
            'wallet_uuid' => 'POS-LIST-A',
            'wallet_balance' => 3000,
            'status' => true,
        ]);
        $walletB = PosWallet::create([
            'network_id' => $network->network_id,
            'pos_id' => $posB->pos_id,
            'wallet_uuid' => 'POS-LIST-B',
            'wallet_balance' => 4000,
            'status' => true,
        ]);

        // A POS owner must not see another POS wallet on the same network.
        $response = $this->withHeaders($posOwnerA['headers'])->getJson('/api/v1/pos-wallets?all=true');
        $response->assertStatus(200)
            ->assertJsonFragment(['wallet_id' => $walletA->wallet_id])
            ->assertJsonMissing(['wallet_id' => $walletB->wallet_id]);

        // The client cannot escape its POS scope by explicitly requesting another POS.
        $crossPosFilter = $this->withHeaders($posOwnerA['headers'])
            ->getJson('/api/v1/pos-wallets?all=true&pos_id='.$posB->pos_id);
        $crossPosFilter->assertStatus(200)
            ->assertJsonMissing(['wallet_id' => $walletA->wallet_id])
            ->assertJsonMissing(['wallet_id' => $walletB->wallet_id]);
    }

    public function test_cross_pos_wallet_access_is_strictly_forbidden(): void
    {
        $netOwnerAuth = $this->createAuthenticatedUser(RoleKey::NETWORK_OWNER, 'net');
        $attackerPosAuth = $this->createAuthenticatedUser(RoleKey::POS_AGENT, 'posA');
        $victimPosAuth = $this->createAuthenticatedUser(RoleKey::POS_AGENT, 'posB');

        $network = Network::create([
            'owner_id' => $netOwnerAuth['user']->user_id,
            'network_name' => 'Master Net',
            'network_phone_number' => '773333333',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        $victimPos = Pos::create([
            'owner_id' => $victimPosAuth['user']->user_id,
            'pos_name' => 'Victim POS',
            'pos_phone_number' => '715555555',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $victimPos->pos_id,
            'user_id' => $victimPosAuth['user']->user_id,
            'role_id' => $victimPosAuth['role']->role_id,
            'status' => 'ACTIVE',
        ]);

        $victimPosWallet = PosWallet::create([
            'network_id' => $network->network_id,
            'pos_id' => $victimPos->pos_id,
            'wallet_uuid' => 'POS-WALL-VICTIM',
            'wallet_balance' => 88888.0,
            'status' => true,
        ]);

        // Attacker POS attempts to view victim POS wallet
        $response = $this->withHeaders($attackerPosAuth['headers'])->getJson("/api/v1/pos-wallets/{$victimPosWallet->wallet_id}");
        $response->assertStatus(403);

        // Attacker POS attempts to view victim POS logs
        $logsResponse = $this->withHeaders($attackerPosAuth['headers'])->getJson("/api/v1/pos-wallets/{$victimPosWallet->wallet_id}/logs");
        $logsResponse->assertStatus(403);

        // Attacker POS attempts to view victim POS wallet by network/pos
        $byNetPosResponse = $this->withHeaders($attackerPosAuth['headers'])->getJson("/api/v1/pos-wallets/network/{$network->network_id}/pos/{$victimPos->pos_id}");
        $byNetPosResponse->assertStatus(403);
    }
}
