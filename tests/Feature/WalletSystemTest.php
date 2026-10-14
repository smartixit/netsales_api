<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Models\Network;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WalletSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected Network $network;

    protected Pos $pos;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Bypass-Encryption' => 'true',
        ];

        $suffix = uniqid();

        $this->network = Network::create([
            'network_name' => 'Net '.$suffix,
            'network_holder_name' => 'Holder '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'pos_name' => 'POS '.$suffix,
            'pos_holder_name' => 'POS Holder '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);
    }

    public function test_can_create_and_fund_network_wallet(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/network-wallets', [
                'network_id' => $this->network->network_id,
                'wallet_balance' => 50000.00,
                'status' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet_balance', 50000);

        $walletId = $response->json('data.wallet_id');

        // Test deposit
        $depositResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/network-wallets/{$walletId}/deposit", [
                'amount' => 15000.00,
                'statement' => 'تغذية بنكية مباشرة',
            ]);

        $depositResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet.wallet_balance', 65000);

        // Test withdraw
        $withdrawResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/network-wallets/{$walletId}/withdraw", [
                'amount' => 10000.00,
                'statement' => 'سحب نقدي',
            ]);

        $withdrawResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet.wallet_balance', 55000);

        // Test logs
        $logsResponse = $this->withHeaders($this->headers)
            ->getJson("/api/v1/network-wallets/{$walletId}/logs");

        $logsResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_perform_atomic_transfer_between_network_and_pos_wallets(): void
    {
        // 1. Create network wallet with 100,000 balance
        $netWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 100000.00,
            'status' => true,
        ]);

        // 2. Create POS wallet with 0 balance
        $posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);

        // 3. Transfer 30,000 from Network to POS
        $transferResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/pos-wallets/transfer-from-network', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'amount' => 30000.00,
                'statement' => 'تغذية رصيد للوكيل',
            ]);

        $transferResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.network_wallet.wallet_balance', 70000)
            ->assertJsonPath('data.pos_wallet.wallet_balance', 30000);

        // 4. Refund / Transfer 10,000 from POS back to Network
        $refundResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/pos-wallets/transfer-to-network', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'amount' => 10000.00,
                'statement' => 'استرجاع فائض الرصيد',
            ]);

        $refundResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pos_wallet.wallet_balance', 20000)
            ->assertJsonPath('data.network_wallet.wallet_balance', 80000);
    }
}
