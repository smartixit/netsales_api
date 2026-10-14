<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PaymentType;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\User;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class VoucherRequestAndInvoicingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected Network $network;

    protected Pos $pos;

    protected VoucherPackage $package;

    protected NetworkWallet $networkWallet;

    protected PosWallet $posWallet;

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

        // 1. Create Network & POS
        $this->network = Network::create([
            'network_name' => 'Net '.$suffix,
            'network_holder_name' => 'Net Holder '.$suffix,
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

        // 2. Link Network and POS
        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        // 3. Create Voucher Package (Price: 500 YER, Default discount: 5%)
        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة 500 '.$suffix,
            'package_color' => '#10B981',
            'package_voucher_price' => 500.00,
            'package_default_cash_discount' => 5.00,
            'status' => true,
        ]);

        // 4. Create custom discount for this POS (10% discount)
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'created_by' => $this->user->user_id,
            'discount_cash' => 10.00,
            'discount_credit' => 8.00,
            'status' => true,
        ]);

        // 5. Create Wallets: POS wallet with 100,000, Network wallet with 0
        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 100000.00,
            'status' => true,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);
    }

    public function test_can_submit_and_approve_request_with_auto_invoicing_and_settlement(): void
    {
        // 1. Submit Request for 100 vouchers
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'request_note' => 'طلب عاجل لكروت 500',
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'item_voucher_count' => 100,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::PENDING->value)
            ->assertJsonPath('data.request_total_amount', 50000);

        $requestId = $createResponse->json('data.request_id');

        // 2. Approve Request
        // Gross: 100 * 500 = 50,000
        // Effective discount: 10% (from VoucherPackageDiscount) = 5,000
        // Net Invoice Amount = 45,000
        $approveResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve", [
                'note' => 'تمت الموافقة وتجهيز الكروت',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', RequestStatus::APPROVED->value)
            ->assertJsonPath('data.invoice.invoice_sale_type', PaymentType::CASH->value)
            ->assertJsonPath('data.invoice.invoice_total_amount', 45000);

        // 3. Verify Wallet Balances Settlement
        $this->posWallet->refresh();
        $this->networkWallet->refresh();

        // POS Wallet should be 100,000 - 45,000 = 55,000
        $this->assertEquals(55000.00, (float) $this->posWallet->wallet_balance);

        // Network Wallet should be 0 + 45,000 = 45,000
        $this->assertEquals(45000.00, (float) $this->networkWallet->wallet_balance);

        // 4. Verify Ledger Logs
        $posLogs = $this->withHeaders($this->headers)
            ->getJson("/api/v1/pos-wallets/{$this->posWallet->wallet_id}/logs");

        $posLogs->assertStatus(200)
            ->assertJsonPath('success', true);

        $netLogs = $this->withHeaders($this->headers)
            ->getJson("/api/v1/network-wallets/{$this->networkWallet->wallet_id}/logs");

        $netLogs->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_reject_request_with_reason(): void
    {
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'item_voucher_count' => 20,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.request_id');

        $rejectResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/reject", [
                'reason' => 'نفاد الكمية المطبوعة حالياً',
            ]);

        $rejectResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::REJECTED->value);
    }

    public function test_credit_sale_and_negative_balance_handling_when_insufficient_wallet_balance(): void
    {
        // 1. تصفير رصيد محفظة الوكيل لمحاكاة عدم توفر رصيد نقدي كافٍ
        $this->posWallet->wallet_balance = 0.00;
        $this->posWallet->save();

        // 2. إرسال طلب كروت جديد (100 كرت × 500 = 50,000 إجمالي قبل الخصم)
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'request_note' => 'طلب كروت بالآجل لعدم توفر رصيد',
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'item_voucher_count' => 100,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.request_id');

        // 3. اعتماد الطلب:
        // لعدم كفاية الرصيد النقدي، يجب أن يتحول البيع آلياً إلى آجل (CREDIT)
        // مع تطبيق نسبة خصم الآجل (8% = 4,000) فيكون الصافي = 46,000
        $approveResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve", [
                'note' => 'تم الاعتماد بالبيع الآجل وفق التسهيل الائتماني',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice.invoice_sale_type', PaymentType::CREDIT->value)
            ->assertJsonPath('data.invoice.is_credit_sale', true)
            ->assertJsonPath('data.invoice.invoice_total_amount', 46000);

        // 4. التحقق من تسوية المحفظة بالرصيد السالب (مديونية على الوكيل)
        $refreshedPosWallet = $this->posWallet->fresh();
        $this->assertEquals(-46000.00, (float) $refreshedPosWallet->wallet_balance);
        $this->assertTrue($refreshedPosWallet->isOverdrawn());
        $this->assertEquals('DEBIT', $refreshedPosWallet->getAccountingStatus());
        $this->assertEquals(46000.00, $refreshedPosWallet->getDebitAmount());

        // 5. التحقق من إيداع المبيعات في محفظة الشبكة كاستحقاق
        $refreshedNetWallet = $this->networkWallet->fresh();
        $this->assertEquals(46000.00, (float) $refreshedNetWallet->wallet_balance);

        // 6. التحقق من مخرجات API لمحفظة الوكيل (عرض المركز المالي والمديونية)
        $posWalletApi = $this->withHeaders($this->headers)
            ->getJson("/api/v1/pos-wallets/{$this->posWallet->wallet_id}");

        $posWalletApi->assertStatus(200)
            ->assertJsonPath('data.wallet_balance', -46000)
            ->assertJsonPath('data.accounting_status', 'DEBIT')
            ->assertJsonPath('data.is_overdrawn', true)
            ->assertJsonPath('data.debit_amount', 46000);
    }
}
