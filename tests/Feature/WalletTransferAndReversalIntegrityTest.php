<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Http\Requests\API\V1\Pos\TransferNetworkToPosWalletRequest;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\NetworkWalletLog;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\User;
use App\Models\WalletTransfer;
use App\Services\PosWalletService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletTransferAndReversalIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected Network $network;

    protected Pos $pos;

    protected NetworkWallet $networkWallet;

    protected PosWallet $posWallet;

    protected PosWalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $gov = Governorate::firstOrFail();
        $dir = Directorate::firstOrFail();

        $this->user = User::firstOrFail();

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'NetFin '.uniqid(),
            'network_phone_number' => '71'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'PosFin '.uniqid(),
            'pos_phone_number' => '71'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'is_active' => true,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 100000.00,
            'status' => true,
        ]);

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 20000.00,
            'status' => true,
        ]);

        $this->walletService = app(PosWalletService::class);
    }

    /**
     * Test 1: Inter-wallet transfer creates a centralized WalletTransfer row and links logs.
     */
    public function test_transfer_creates_central_transfer_and_links_logs(): void
    {
        $request = new TransferNetworkToPosWalletRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 10000.00,
            'statement' => 'تغذية رصيد اختبارية',
        ]);

        $result = $this->walletService->transferFromNetwork($request, $this->user->user_id);

        /** @var WalletTransfer $transfer */
        $transfer = $result['transfer'];
        $this->assertNotNull($transfer);
        $this->assertEquals(10000.00, (float) $transfer->amount);
        $this->assertEquals('NET_TO_POS', $transfer->direction);
        $this->assertEquals('COMPLETED', $transfer->status);
        $this->assertFalse($transfer->isReversal());

        // Verify logs are linked via transfer_id
        $this->assertEquals($transfer->transfer_id, $result['network_log']->transfer_id);
        $this->assertEquals($transfer->transfer_id, $result['pos_log']->transfer_id);

        $this->assertEquals(90000.00, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals(30000.00, (float) $this->posWallet->fresh()->wallet_balance);
    }

    /**
     * Test 2: Database enforces wallet identity in logs. Mismatched wallet_id is rejected by MySQL FK.
     */
    public function test_database_rejects_wallet_log_with_mismatched_wallet_id(): void
    {
        $transfer = WalletTransfer::create([
            'transfer_uuid' => (string) Str::uuid(),
            'network_wallet_id' => $this->networkWallet->wallet_id,
            'pos_wallet_id' => $this->posWallet->wallet_id,
            'amount' => 5000.00,
            'direction' => 'NET_TO_POS',
            'created_by' => $this->user->user_id,
        ]);

        // Create another network and wallet
        $otherNet = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'OtherNet '.uniqid(),
            'network_phone_number' => '71'.rand(1000000, 9999999),
            'governorate_id' => $this->network->governorate_id,
            'directorate_id' => $this->network->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $otherWallet = NetworkWallet::create([
            'network_id' => $otherNet->network_id,
            'wallet_balance' => 50000.00,
            'status' => true,
        ]);

        // Attempting to attach this transfer to a different network wallet must fail with FK violation
        $this->expectException(QueryException::class);

        NetworkWalletLog::create([
            'wallet_id' => $otherWallet->wallet_id, // MISMATCH!
            'transfer_id' => $transfer->transfer_id,
            'log_operation' => 'WITHDRAW',
            'log_amount' => 5000.00,
            'log_statement' => 'Malicious spoof attempt',
            'created_by' => $this->user->user_id,
        ]);
    }

    /**
     * Test 3: Atomic reversal correctly adjusts balances and links reversal records.
     */
    public function test_can_reverse_transfer_atomically(): void
    {
        $request = new TransferNetworkToPosWalletRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 15000.00,
        ]);

        $res = $this->walletService->transferFromNetwork($request, $this->user->user_id);
        $transferId = $res['transfer']->transfer_id;

        $this->assertEquals(85000.00, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals(35000.00, (float) $this->posWallet->fresh()->wallet_balance);

        // Execute reversal
        $reversalRes = $this->walletService->reverseTransfer($transferId, $this->user->user_id);

        /** @var WalletTransfer $orig */
        $orig = $reversalRes['original_transfer'];
        /** @var WalletTransfer $reversal */
        $reversal = $reversalRes['reversal_transfer'];

        $this->assertEquals('REVERSED', $orig->status);
        $this->assertTrue($reversal->isReversal());
        $this->assertEquals($transferId, $reversal->original_transfer_id);

        // Balances restored
        $this->assertEquals(100000.00, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals(20000.00, (float) $this->posWallet->fresh()->wallet_balance);
    }

    /**
     * Test 4: Cannot reverse an already reversed transfer twice.
     */
    public function test_cannot_reverse_same_transfer_twice(): void
    {
        $request = new TransferNetworkToPosWalletRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 5000.00,
        ]);

        $res = $this->walletService->transferFromNetwork($request, $this->user->user_id);
        $transferId = $res['transfer']->transfer_id;

        // First reversal succeeds
        $this->walletService->reverseTransfer($transferId, $this->user->user_id);

        // Second reversal must fail
        $this->expectException(ValidationException::class);
        $this->walletService->reverseTransfer($transferId, $this->user->user_id);
    }

    /**
     * Test 5: Reversal-of-reversal is prevented at the database schema level.
     */
    public function test_database_rejects_reversing_a_reversal(): void
    {
        $origTransfer = WalletTransfer::create([
            'transfer_uuid' => (string) Str::uuid(),
            'network_wallet_id' => $this->networkWallet->wallet_id,
            'pos_wallet_id' => $this->posWallet->wallet_id,
            'amount' => 5000.00,
            'direction' => 'NET_TO_POS',
            'created_by' => $this->user->user_id,
        ]);

        $reversalTransfer = WalletTransfer::create([
            'transfer_uuid' => (string) Str::uuid(),
            'network_wallet_id' => $this->networkWallet->wallet_id,
            'pos_wallet_id' => $this->posWallet->wallet_id,
            'amount' => 5000.00,
            'direction' => 'POS_TO_NET',
            'original_transfer_id' => $origTransfer->transfer_id,
            'created_by' => $this->user->user_id,
        ]);

        $reversalTransfer->refresh();
        $this->assertTrue($reversalTransfer->isReversal());

        // Attempting to create a reversal that points to $reversalTransfer must trigger MySQL FK failure
        $this->expectException(QueryException::class);

        WalletTransfer::create([
            'transfer_uuid' => (string) Str::uuid(),
            'network_wallet_id' => $this->networkWallet->wallet_id,
            'pos_wallet_id' => $this->posWallet->wallet_id,
            'amount' => 5000.00,
            'direction' => 'NET_TO_POS',
            'original_transfer_id' => $reversalTransfer->transfer_id, // Target has is_reversal = 1 !
            'created_by' => $this->user->user_id,
        ]);
    }
}
