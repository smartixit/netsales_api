<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\UserStatus;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\PosWalletRepositoryInterface;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\Pos;
use App\Models\User;
use App\Services\NetworkPosService;
use App\Services\NetworkService;
use App\Services\PosService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WalletAutoCreationTest extends TestCase
{
    use DatabaseTransactions;

    protected NetworkService $networkService;
    protected PosService $posService;
    protected NetworkPosService $networkPosService;
    protected NetworkWalletRepositoryInterface $networkWalletRepository;
    protected PosWalletRepositoryInterface $posWalletRepository;
    protected NetworkRepositoryInterface $networkRepository;
    protected PosRepositoryInterface $posRepository;
    protected NetworkPosRepositoryInterface $networkPosRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->networkService = app(NetworkService::class);
        $this->posService = app(PosService::class);
        $this->networkPosService = app(NetworkPosService::class);
        $this->networkWalletRepository = app(NetworkWalletRepositoryInterface::class);
        $this->posWalletRepository = app(PosWalletRepositoryInterface::class);
        $this->networkRepository = app(NetworkRepositoryInterface::class);
        $this->posRepository = app(PosRepositoryInterface::class);
        $this->networkPosRepository = app(NetworkPosRepositoryInterface::class);
    }

    public function test_network_approval_automatically_creates_network_wallet(): void
    {
        $gov = Governorate::firstOrCreate(['governorate_name' => 'Sanaa']);
        $dir = Directorate::firstOrCreate(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Al-Sabeen']);

        $admin = User::firstOrFail();

        $network = $this->networkRepository->create([
            'owner_id' => $admin->user_id,
            'network_name' => 'Test Telecom',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'network_phone_number' => '777111222',
            'status' => NetworkStatus::PENDING,
        ]);

        $this->assertNull($this->networkWalletRepository->findByNetworkId($network->network_id));

        $approved = $this->networkService->approve($network->network_id, $admin->user_id);

        $this->assertEquals(NetworkStatus::ACTIVE, $approved->status);
        $wallet = $this->networkWalletRepository->findByNetworkId($network->network_id);
        $this->assertNotNull($wallet);
        $this->assertEquals(0.00, (float) $wallet->wallet_balance);
        $this->assertTrue((bool) $wallet->status);

        $smsSetting = \App\Models\NetworkSmsSetting::where('network_id', $network->network_id)->first();
        $this->assertNotNull($smsSetting);
        $this->assertFalse((bool) $smsSetting->is_enabled);
        $this->assertFalse((bool) $smsSetting->use_custom_gateway);
    }

    public function test_linking_pos_to_network_automatically_creates_joint_pos_wallet(): void
    {
        $gov = Governorate::firstOrCreate(['governorate_name' => 'Aden']);
        $dir = Directorate::firstOrCreate(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Crater']);

        $admin = User::firstOrFail();
        $posUser = User::create([
            'role_id' => $admin->role_id,
            'user_full_name' => 'Retailer 1',
            'user_phone_number' => '733999888',
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $network = $this->networkRepository->create([
            'owner_id' => $admin->user_id,
            'network_name' => 'Aden Net',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'network_phone_number' => '733111222',
            'status' => NetworkStatus::ACTIVE,
        ]);

        $pos = $this->posRepository->create([
            'owner_id' => $posUser->user_id,
            'pos_name' => 'Shop 1',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'pos_phone_number' => '733999888',
            'status' => PosStatus::ACTIVE,
        ]);

        $this->assertNull($this->posWalletRepository->findByNetworkAndPos($network->network_id, $pos->pos_id));

        $link = $this->networkPosService->create([
            'network_id' => $network->network_id,
            'pos_id' => $pos->pos_id,
            'status' => true,
        ]);

        $this->assertNotNull($link);
        $wallet = $this->posWalletRepository->findByNetworkAndPos($network->network_id, $pos->pos_id);
        $this->assertNotNull($wallet);
        $this->assertEquals(0.00, (float) $wallet->wallet_balance);
        $this->assertTrue((bool) $wallet->status);
    }

    public function test_pos_approval_activates_links_and_creates_wallets_for_pre_linked_networks(): void
    {
        $gov = Governorate::firstOrCreate(['governorate_name' => 'Taiz']);
        $dir = Directorate::firstOrCreate(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Al-Qahirah']);
        $admin = User::firstOrFail();

        $posUser2 = User::create([
            'role_id' => $admin->role_id,
            'user_full_name' => 'Pending Retailer',
            'user_phone_number' => '711999888',
            'user_password' => bcrypt('password'),
            'status' => UserStatus::PENDING,
        ]);

        $network1 = $this->networkRepository->create([
            'owner_id' => $admin->user_id,
            'network_name' => 'Net 1',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'network_phone_number' => '711111222',
            'status' => NetworkStatus::ACTIVE,
        ]);

        $pos = $this->posRepository->create([
            'owner_id' => $posUser2->user_id,
            'pos_name' => 'Pending Shop',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'pos_phone_number' => '711999888',
            'status' => PosStatus::PENDING,
        ]);

        // Create link directly in DB as pending/inactive during POS registration
        $this->networkPosRepository->create([
            'network_id' => $network1->network_id,
            'pos_id' => $pos->pos_id,
            'status' => false,
        ]);

        $this->assertNull($this->posWalletRepository->findByNetworkAndPos($network1->network_id, $pos->pos_id));

        $approvedPos = $this->posService->approve($pos->pos_id, $admin->user_id);

        $this->assertEquals(PosStatus::ACTIVE, $approvedPos->status);
        $link = $this->networkPosRepository->findByNetworkAndPos($network1->network_id, $pos->pos_id);
        $this->assertTrue((bool) $link->status);

        $wallet = $this->posWalletRepository->findByNetworkAndPos($network1->network_id, $pos->pos_id);
        $this->assertNotNull($wallet);
        $this->assertEquals(0.00, (float) $wallet->wallet_balance);
        $this->assertTrue((bool) $wallet->status);
    }

    public function test_network_operational_messages_prohibited_from_system_gateway_fallback(): void
    {
        $gov = Governorate::firstOrCreate(['governorate_name' => 'Ibb']);
        $dir = Directorate::firstOrCreate(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Al-Mashannah']);
        $admin = User::firstOrFail();

        $network = $this->networkRepository->create([
            'owner_id' => $admin->user_id,
            'network_name' => 'Isolated Net',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'network_phone_number' => '733000111',
            'status' => NetworkStatus::ACTIVE,
        ]);

        /** @var \App\Services\Support\SMSGateService $smsGateService */
        $smsGateService = app(\App\Services\Support\SMSGateService::class);

        // 1. System OTP or Admin broadcast ALWAYS uses system gateway (even with network ID)
        [$otpConfig, $ownerType] = $smsGateService->resolveGatewaySettings($network->network_id, messageType: \App\Enums\SmsMessageType::OTP);
        $this->assertNotNull($otpConfig);
        $this->assertEquals('system', $ownerType);

        [$adminConfig, $adminOwner] = $smsGateService->resolveGatewaySettings($network->network_id, messageType: \App\Enums\SmsMessageType::ADMIN_BROADCAST);
        $this->assertNotNull($adminConfig);
        $this->assertEquals('system', $adminOwner);

        // 2. Network operational messages without custom gateway are blocked (return null)
        [$operationalConfig, $ownerTypeOp] = $smsGateService->resolveGatewaySettings($network->network_id, messageType: \App\Enums\SmsMessageType::RECEIPT);
        $this->assertNull($operationalConfig);
        $this->assertEquals('none', $ownerTypeOp);

        // 3. Un-attributed message without networkId and not sovereign is also blocked (no system fallback)
        [$nullConfig, $nullOwner] = $smsGateService->resolveGatewaySettings(null, messageType: \App\Enums\SmsMessageType::RECEIPT);
        $this->assertNull($nullConfig);
        $this->assertEquals('none', $nullOwner);

        // 4. Policy check verifies that operational messages without custom gateway are prohibited
        $this->assertFalse($smsGateService->smsAllowedFor(null, \App\Enums\SmsMessageType::RECEIPT));
        $this->assertTrue($smsGateService->smsAllowedFor(null, \App\Enums\SmsMessageType::OTP));
        $this->assertTrue($smsGateService->smsAllowedFor(null, \App\Enums\SmsMessageType::ADMIN_BROADCAST));
    }
}
