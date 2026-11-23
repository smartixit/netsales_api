<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Jobs\SendSmsNotificationJob;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
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

        $suffix = uniqid();
        $this->user = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Wallet Network Owner '.$suffix,
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $deviceUuid = 'TEST-DEVICE-WALLET-'.uniqid();
        UserDevice::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => $deviceUuid,
            'device_name' => 'PHPUnit Wallet Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => $deviceUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Device-Uuid' => $deviceUuid,
            'X-Bypass-Encryption' => 'true',
        ];

        $posUser = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'POS Agent '.$suffix,
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'Net '.$suffix,
            'network_phone_number' => $this->user->user_phone_number,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $posUser->user_id,
            'pos_name' => 'POS '.$suffix,
            'pos_phone_number' => $posUser->user_phone_number,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'is_active' => true,
        ]);
    }

    public function test_network_owner_cannot_create_or_fund_the_platform_managed_network_wallet(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/network-wallets', [
                'network_id' => $this->network->network_id,
                'wallet_balance' => 50000.00,
                'status' => true,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('network_wallets', [
            'network_id' => $this->network->network_id,
        ]);
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

    public function test_sms_notification_is_sent_with_exact_templates_on_deposit_and_withdrawal(): void
    {
        Queue::fake([SendSmsNotificationJob::class]);

        $posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 2000.00,
            'status' => true,
        ]);

        // 1. Deposit 1,000 -> balance becomes 3,000
        $depositResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/pos-wallets/{$posWallet->wallet_id}/deposit", [
                'amount' => 1000.00,
                'statement' => 'إيداع نقدي',
            ]);

        $depositResponse->assertStatus(200);

        // Credit SMS template:
        // نقطة {اسم_النقطة} - شبكة {اسم_الشبكة}: لكم سند قبض بمبلغ 1,000.00YER رصيدكم 3,000.00YER
        $expectedReceiptMsg = "{$this->pos->pos_name} - {$this->network->network_name}: لكم سند قبض بمبلغ 1,000.00YER رصيدكم 3,000.00YER";

        Queue::assertPushed(SendSmsNotificationJob::class, function ($job) use ($expectedReceiptMsg) {
            return $job->phoneNumbers === $this->pos->pos_phone_number && $job->message === $expectedReceiptMsg;
        });

        // 2. Withdraw 1,000 -> balance becomes 2,000
        $withdrawResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/pos-wallets/{$posWallet->wallet_id}/withdraw", [
                'amount' => 1000.00,
                'statement' => 'سحب نقدي',
            ]);

        $withdrawResponse->assertStatus(200);

        // Debit SMS template:
        // نقطة {اسم_النقطة} - شبكة {اسم_الشبكة}: عليكم سند دفع بمبلغ 1,000.00YER رصيدكم 2,000.00YER
        $expectedPaymentMsg = "{$this->pos->pos_name} - {$this->network->network_name}: عليكم سند صرف بمبلغ 1,000.00YER رصيدكم 2,000.00YER";

        Queue::assertPushed(SendSmsNotificationJob::class, function ($job) use ($expectedPaymentMsg) {
            return $job->phoneNumbers === $this->pos->pos_phone_number && $job->message === $expectedPaymentMsg;
        });
    }
}
