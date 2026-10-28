<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\SaleType;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\UserStatus;
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
        $suffix = uniqid();
        $deviceUuid = 'TEST-DEVICE-UUID-'.$suffix;

        \App\Models\UserDevice::firstOrCreate([
            'user_id' => $this->user->user_id,
            'device_uuid' => $deviceUuid,
        ], [
            'device_name' => 'PHPUnit Device',
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

         $posUser = User::create([
            'role_id' => $this->user->role_id,
            'user_full_name' => 'POS Agent '.$suffix,
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // 1. Create Network & POS
        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'Net '.$suffix,
            'network_phone_number' => '733111222',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'POS '.$suffix,
            'pos_phone_number' => $posUser->user_phone_number,
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
                        'voucher_count' => 100,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::PENDING->value)
            ->assertJsonPath('data.sale_type', SaleType::CASH->value)
            ->assertJsonPath('data.request_total_amount', 45000)
            ->assertJsonPath('data.items.0.voucher_price', 500)
            ->assertJsonPath('data.items.0.item_discount', 10)
            ->assertJsonPath('data.items.0.item_total_amount', 45000);

        $requestId = $createResponse->json('data.request_id');

        // 2. Approve Request
        // Gross: 100 * 500 = 50,000
        // Effective discount: 10% (from VoucherPackageDiscount) = 5,000
        // Net Invoice Amount = 45,000
        $approveResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve", [
                'request_note' => 'تمت الموافقة وتجهيز الكروت',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', RequestStatus::APPROVED->value)
            ->assertJsonPath('data.invoice.sale_type', SaleType::CASH->value)
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
                        'voucher_count' => 20,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.request_id');

        $rejectResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/reject", [
                'rejection_reason' => 'نفاد الكمية المطبوعة حالياً',
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
                        'voucher_count' => 100,
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
                'request_note' => 'تم الاعتماد بالبيع الآجل وفق التسهيل الائتماني',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice.sale_type', SaleType::CREDIT->value)
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

    public function test_explicit_credit_request_applies_credit_discount_and_generates_credit_invoice(): void
    {
        // 1. Submit Request with SaleType::CREDIT
        // Gross: 100 * 500 = 50,000
        // Credit discount is 8% = 4,000 -> Net: 46,000
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'sale_type' => SaleType::CREDIT->value,
                'request_note' => 'طلب آجل صريح',
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 100,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sale_type', SaleType::CREDIT->value)
            ->assertJsonPath('data.request_total_amount', 46000)
            ->assertJsonPath('data.items.0.voucher_price', 500)
            ->assertJsonPath('data.items.0.item_discount', 8)
            ->assertJsonPath('data.items.0.item_total_amount', 46000);

        $requestId = $createResponse->json('data.request_id');

        // 2. Approve Request -> invoice should also be CREDIT with 46,000
        $approveResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve", [
                'request_note' => 'موافقة على الطلب الآجل',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice.sale_type', SaleType::CREDIT->value)
            ->assertJsonPath('data.invoice.invoice_total_amount', 46000);
    }

    public function test_flexible_approval_with_override_items_and_sale_type_leaves_original_request_untouched(): void
    {
        // 1. Submit original request for 100 vouchers (CASH, Net 45,000)
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'sale_type' => SaleType::CASH->value,
                'request_note' => 'طلب كروت 100 حبة نقداً',
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 100,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.request_id');

        // 2. Approve with overrides:
        // - Change sale_type to CREDIT
        // - Change quantity to 50 vouchers instead of 100
        // Expected for Invoice:
        // Gross: 50 * 500 = 25,000
        // Credit discount (8%) = 2,000 -> Net: 23,000
        $approveResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve", [
                'request_note' => 'اعتماد جزئي لـ 50 كرت فقط بالبيع الآجل',
                'sale_type' => SaleType::CREDIT->value,
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 50,
                    ],
                ],
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice.sale_type', SaleType::CREDIT->value)
            ->assertJsonPath('data.invoice.invoice_total_amount', 23000)
            ->assertJsonPath('data.invoice.items.0.voucher_count', 50)
            ->assertJsonPath('data.invoice.items.0.item_discount', 8)
            ->assertJsonPath('data.invoice.items.0.item_total_amount', 23000);

        // 3. Verify that the ORIGINAL Request and its items were NOT modified in the database
        $originalRequestResponse = $this->withHeaders($this->headers)
            ->getJson("/api/v1/requests/{$requestId}");

        $originalRequestResponse->assertStatus(200)
            ->assertJsonPath('data.status', RequestStatus::APPROVED->value)
            ->assertJsonPath('data.sale_type', SaleType::CASH->value) // Untouched!
            ->assertJsonPath('data.request_total_amount', 45000) // Untouched!
            ->assertJsonPath('data.items.0.voucher_count', 100) // Untouched!
            ->assertJsonPath('data.items.0.item_total_amount', 45000); // Untouched!
    }

    /**
     * اختبار إمكانية تعديل طلب الكروت قيد الانتظار وتحديث البنود واحتساب الإجمالي.
     */
    public function test_can_update_pending_request(): void
    {
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'sale_type' => SaleType::CASH->value,
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 10,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.request_id');

        // تعديل الكمية إلى 30 وتغيير الملاحظة
        $updateResponse = $this->withHeaders($this->headers)
            ->putJson("/api/v1/requests/{$requestId}", [
                'request_note' => 'تم تعديل الكمية بناء على رغبة نقطة البيع',
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 30,
                    ],
                ],
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.voucher_count', 30)
            ->assertJsonPath('data.request_note', 'تم تعديل الكمية بناء على رغبة نقطة البيع');
    }

    /**
     * اختبار حظر تعديل أو حذف أي طلب كروت تم اعتماده أو رفضه مسبقاً لحفظ نزاهة التدقيق المالي.
     */
    public function test_cannot_update_or_delete_approved_request(): void
    {
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'sale_type' => SaleType::CASH->value,
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 10,
                    ],
                ],
            ]);

        $requestId = $createResponse->json('data.request_id');

        // اعتماد الطلب
        $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve")
            ->assertStatus(200);

        // محاولة تعديل الطلب المعتمد -> يجب أن ترفض برمز 422
        $updateResponse = $this->withHeaders($this->headers)
            ->putJson("/api/v1/requests/{$requestId}", [
                'request_note' => 'محاولة تعديل غير مصرح بها',
            ]);

        $updateResponse->assertStatus(422);

        // محاولة حذف الطلب المعتمد -> يجب أن ترفض برمز 422
        $deleteResponse = $this->withHeaders($this->headers)
            ->deleteJson("/api/v1/requests/{$requestId}");

        $deleteResponse->assertStatus(422);
    }

    /**
     * اختبار إمكانية حذف طلب كروت قيد الانتظار بنجاح.
     */
    public function test_can_delete_pending_request(): void
    {
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'sale_type' => SaleType::CASH->value,
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 10,
                    ],
                ],
            ]);

        $requestId = $createResponse->json('data.request_id');

        $deleteResponse = $this->withHeaders($this->headers)
            ->deleteJson("/api/v1/requests/{$requestId}");

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}


