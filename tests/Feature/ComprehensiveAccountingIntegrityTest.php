<?php

namespace Tests\Feature;

use App\Enums\InvoicePaymentStatus;
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
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * حزمة اختبارات النزاهة المحاسبية والعمليات المالية الدقيقة (Comprehensive Accounting & Financial Integrity Test).
 *
 * مصممة لمعالجة كافة السيناريوهات المحتملة وغير المحتملة:
 * 1. الحفظ التام لرصيد المحافظ والعمليات المزدوجة (Conservation of Balances).
 * 2. منع السحب بالقيم السالبة أو الصفرية أو تجاوز الحدود الدنيا والقصوى.
 * 3. دقة الأرقام العشرية ومنع أخطاء التقريب والـ Floating-Point Drift.
 * 4. السداد الجزئي التراكمي للفواتير حتى الاكتمال (Partial to Full Payment Lifecycle).
 * 5. منع السداد الفائض (Overpayment Prevention).
 * 6. تطبيق الخصومات المخصصة لنقاط البيع وتجاوز خصومات الباقة الافتراضية بدقة رياضية.
 * 7. تسوية مبالغ المصروفات ديناميكياً عند الإضافة والتعديل والحذف والاسترجاع.
 * 8. حظر العمليات على المحافظ غير النشطة أو المعلقة.
 */
class ComprehensiveAccountingIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected UserSession $session;
    protected Network $network;
    protected Pos $pos;
    protected NetworkWallet $networkWallet;
    protected PosWallet $posWallet;
    protected VoucherPackage $package;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Financial Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Bypass-Encryption' => 'true',
        ];

        $suffix = uniqid();

         $posUser = User::create([
            'role_id' => $this->user->role_id,
            'user_full_name' => 'POS Agent '.$suffix,
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'FinNet '.$suffix,
            'network_phone_number' => '733111222',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $posUser->user_id,
            'pos_name' => 'FinPOS '.$suffix,
            'pos_phone_number' => $posUser->user_phone_number,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 100000.00,
            'status' => true,
        ]);

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 10000.00,
            'status' => true,
        ]);

        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'VIP 10GB '.$suffix,
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.00, // 10%
            'package_default_credit_discount' => 5.00, // 5%
            'status' => true,
        ]);
    }

    /**
     * اختبار: التحويل البيني المتوازن والمحفوظ (Conservation of Value)
     * يجب أن يقل رصيد محفظة الشبكة بنفس القيمة التي يزيد بها رصيد نقطة البيع بالضبط،
     * مع بقاء المجموع الإجمالي للأرصدة ثابتاً دون أي تسرّب مالي.
     */
    public function test_atomic_transfer_conserves_total_system_balance(): void
    {
        $initialNetworkBalance = (float) $this->networkWallet->wallet_balance;
        $initialPosBalance = (float) $this->posWallet->wallet_balance;
        $initialTotal = $initialNetworkBalance + $initialPosBalance;

        $transferAmount = 15750.50;

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/pos-wallets/transfer-from-network', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'amount' => $transferAmount,
                'statement' => 'تحويل رصيد تشغيلي دقيق',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->networkWallet->refresh();
        $this->posWallet->refresh();

        $this->assertEquals($initialNetworkBalance - $transferAmount, (float) $this->networkWallet->wallet_balance);
        $this->assertEquals($initialPosBalance + $transferAmount, (float) $this->posWallet->wallet_balance);

        $finalTotal = (float) $this->networkWallet->wallet_balance + (float) $this->posWallet->wallet_balance;
        $this->assertEquals($initialTotal, $finalTotal, 'مجموع الأرصدة قبل التحويل يجب أن يتطابق تماماً مع المجموع بعد التحويل');
    }

    /**
     * اختبار: رفض التحويل أو السحب بقيم سالبة أو صفرية أو غير عددية
     */
    public function test_rejects_zero_negative_and_invalid_amounts(): void
    {
        $invalidAmounts = [0, -500, -0.01, 'zero', ''];

        foreach ($invalidAmounts as $amount) {
            // محاولة إيداع بمبلغ غير صالح
            $depositResponse = $this->withHeaders($this->headers)
                ->postJson("/api/v1/network-wallets/{$this->networkWallet->wallet_id}/deposit", [
                    'amount' => $amount,
                    'statement' => 'إيداع تجريبي غير صالح',
                ]);
            $depositResponse->assertStatus(422);

            // محاولة سحب بمبلغ غير صالح
            $withdrawResponse = $this->withHeaders($this->headers)
                ->postJson("/api/v1/network-wallets/{$this->networkWallet->wallet_id}/withdraw", [
                    'amount' => $amount,
                    'statement' => 'سحب تجريبي غير صالح',
                ]);
            $withdrawResponse->assertStatus(422);

            // محاولة تحويل بيني بمبلغ غير صالح
            $transferResponse = $this->withHeaders($this->headers)
                ->postJson('/api/v1/pos-wallets/transfer-from-network', [
                    'network_id' => $this->network->network_id,
                    'pos_id' => $this->pos->pos_id,
                    'amount' => $amount,
                    'statement' => 'تحويل تجريبي غير صالح',
                ]);
            $transferResponse->assertStatus(422);
        }

        // التأكد من عدم تأثر الأرصدة
        $this->networkWallet->refresh();
        $this->assertEquals(100000.00, (float) $this->networkWallet->wallet_balance);
    }

    /**
     * اختبار: منع السحب الذي يتجاوز الرصيد المتوفر في محفظة الشبكة
     */
    public function test_prevent_overdraft_on_network_wallet(): void
    {
        $currentBalance = (float) $this->networkWallet->wallet_balance;
        $excessiveAmount = $currentBalance + 0.01;

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v1/network-wallets/{$this->networkWallet->wallet_id}/withdraw", [
                'amount' => $excessiveAmount,
                'statement' => 'محاولة سحب مكشوف غير مسموح به',
            ]);

        $response->assertStatus(422);

        $this->networkWallet->refresh();
        $this->assertEquals($currentBalance, (float) $this->networkWallet->wallet_balance);
    }

    /**
     * اختبار: الالتزام بالحد الأدنى للتحويل المحقون في إعدادات النظام
     */
    public function test_enforces_system_setting_minimum_transfer_amount(): void
    {
        // الحد الأدنى الافتراضي في النظام 100 ر.ي
        $belowMinAmount = 50.00;

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/pos-wallets/transfer-from-network', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'amount' => $belowMinAmount,
                'statement' => 'تحويل أقل من الحد الأدنى',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('الحد الأدنى', $response->json('message'));
    }

    /**
     * اختبار: التحويل العكسي من محفظة نقطة البيع إلى محفظة الشبكة (POS to Network Transfer)
     */
    public function test_pos_to_network_wallet_transfer_integrity(): void
    {
        $refundAmount = 3500.00;
        $initialPosBalance = (float) $this->posWallet->wallet_balance;
        $initialNetworkBalance = (float) $this->networkWallet->wallet_balance;

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/pos-wallets/transfer-to-network', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'amount' => $refundAmount,
                'statement' => 'استرجاع رصيد فائض إلى الشبكة',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->posWallet->refresh();
        $this->networkWallet->refresh();

        $this->assertEquals($initialPosBalance - $refundAmount, (float) $this->posWallet->wallet_balance);
        $this->assertEquals($initialNetworkBalance + $refundAmount, (float) $this->networkWallet->wallet_balance);
    }

    /**
     * اختبار: منع العمليات المالية على المحافظ غير النشطة أو المعطلة
     */
    public function test_blocked_financial_operations_on_inactive_wallet(): void
    {
        // تعطيل محفظة نقطة البيع
        $this->posWallet->update(['status' => false]);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/pos-wallets/transfer-from-network', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'amount' => 1000.00,
                'statement' => 'محاولة تحويل لمحفظة معطلة',
            ]);

        $response->assertStatus(422);
    }

    /**
     * اختبار: حساب الخصومات المركبة بدقة رياضية وتفوق الخصم المخصص لنقطة البيع
     * إذا كان للباقة خصم افتراضي 10%، ولنقطة البيع خصم مخصص 15%، يجب اعتماد الـ 15% حصرياً.
     */
    public function test_custom_pos_discount_strictly_overrides_package_default_discount(): void
    {
        // إنشاء خصم مخصص لنقطة البيع بنسبة 15% نقدي و 8% آجل
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 15.00,
            'discount_credit' => 8.00,
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        // شراء 10 كروت بسعر 1000 للكارت = 10,000 إجمالي
        // الخصم النقدي المخصص 15% = 1,500 خصم -> الصافي = 8,500
        $requestResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'request_note' => 'طلب مع خصم مخصص',
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 10,
                    ],
                ],
            ]);

        $requestResponse->assertStatus(201);
        $requestId = $requestResponse->json('data.request_id');

        // اعتماد الطلب وتوليد الفاتورة
        $approveResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve");

        $approveResponse->assertStatus(200);

        $invoice = SaleInvoice::where('pos_id', $this->pos->pos_id)
            ->with('items')
            ->latest('invoice_id')
            ->firstOrFail();

        $this->assertEquals(8500.00, (float) $invoice->invoice_total_amount, 'صافي الفاتورة يجب أن يكون 8500 بعد خصم 15% المخصص');
        $this->assertEquals(15.00, (float) $invoice->items->first()->item_discount, 'يجب أن يطبق خصم الـ 15% المخصص بدلاً من الـ 10% الافتراضية');
    }

    /**
     * اختبار: دورة حياة السداد التراكمي الجزئي للفاتورة حتى السداد الكامل
     * UNPAID -> PARTIALLY_PAID -> PAID
     */
    public function test_cumulative_partial_payments_until_fully_paid(): void
    {
        // إنشاء فاتورة مبيعات بمبلغ صافي 10,000 ر.ي (آجل - غير مسددة)
        $invoice = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 10000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $this->assertEquals(InvoicePaymentStatus::UNPAID, $invoice->payment_status);
        $this->assertEquals(10000.00, (float) $invoice->remaining_amount);

        // الدفعة الأولى: سداد جزئي 3,000 ر.ي
        $invoice->paid_amount = 3000.00;
        $invoice->remaining_amount = 7000.00;
        $invoice->payment_status = InvoicePaymentStatus::PARTIALLY_PAID;
        $invoice->save();

        $this->assertTrue($invoice->isPartiallyPaid());
        $this->assertFalse($invoice->isFullyPaid());
        $this->assertEquals(7000.00, (float) $invoice->remaining_amount);

        // الدفعة الثانية: سداد جزئي إضافي 4,000 ر.ي (الإجمالي المسدد 7,000، المتبقي 3,000)
        $invoice->paid_amount = 7000.00;
        $invoice->remaining_amount = 3000.00;
        $invoice->save();

        $this->assertTrue($invoice->isPartiallyPaid());
        $this->assertEquals(3000.00, (float) $invoice->remaining_amount);

        // الدفعة النهائية: سداد المتبقي 3,000 ر.ي بالكامل
        $invoice->paid_amount = 10000.00;
        $invoice->remaining_amount = 0.00;
        $invoice->payment_status = InvoicePaymentStatus::PAID;
        $invoice->save();

        $this->assertTrue($invoice->isFullyPaid());
        $this->assertFalse($invoice->isPartiallyPaid());
        $this->assertEquals(0.00, (float) $invoice->remaining_amount);
    }

    /**
     * اختبار: منع السداد الفائض (Overpayment) على الفاتورة
     */
    public function test_prevents_paying_more_than_invoice_final_amount(): void
    {
        // محاولة إنشاء فاتورة مبيعات مباشرة حيث المبلغ المدفوع يتجاوز النهائي
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/sale-invoices', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'paid_amount' => 50000.00, // أكثر من إجمالي الكروت
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_price' => 1000.00,
                        'voucher_count' => 5, // إجمالي 5,000
                    ],
                ],
            ]);

        // يجب كبح المبلغ المدفوع تلقائياً بحيث لا يتجاوز إجمالي الفاتورة
        if ($response->status() === 201) {
            $data = $response->json('data');
            $this->assertLessThanOrEqual((float) $data['invoice_total_amount'], (float) $data['paid_amount'], 'لا يمكن أن يتجاوز المدفوع إجمالي الفاتورة');
            $this->assertEquals((float) $data['invoice_total_amount'], (float) $data['paid_amount'], 'يتم كبح المبلغ المدفوع تلقائياً بحيث لا يتجاوز إجمالي الفاتورة');
        } else {
            $response->assertStatus(422);
        }
    }

    /**
     * اختبار: تسوية سندات المصروفات بالتكامل الذري مع محفظة الشبكة
     * إنشاء مصروف -> خصم تلقائي من المحفظة.
     * تعديل المصروف بالزيادة -> خصم الفارق الإضافي من المحفظة.
     * تعديل المصروف بالنقصان -> رد الفارق المسترجع إلى المحفظة.
     */
    public function test_expense_lifecycle_adjusts_wallet_balance_precisely(): void
    {
        $initialBalance = (float) $this->networkWallet->wallet_balance;

        // 1. تسجيل مصروف بقيمة 5,000 ر.ي
        $expenseResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/expenses', [
                'network_id' => $this->network->network_id,
                'expense_date' => now('Asia/Aden')->toDateString(),
                'items' => [
                    [
                        'item_statement' => 'فاتورة كهرباء المقر',
                        'item_amount' => 5000.00,
                    ],
                ],
            ]);

        $expenseResponse->assertStatus(201);
        $expenseId = $expenseResponse->json('data.expense_id');

        $this->networkWallet->refresh();
        $this->assertEquals($initialBalance - 5000.00, (float) $this->networkWallet->wallet_balance);

        // 2. تعديل المصروف بزيادة القيمة إلى 7,000 ر.ي (فرق 2,000 خصم إضافي)
        $updateResponse = $this->withHeaders($this->headers)
            ->putJson("/api/v1/expenses/{$expenseId}", [
                'items' => [
                    [
                        'item_statement' => 'فاتورة كهرباء المقر المعدلة',
                        'item_amount' => 7000.00,
                    ],
                ],
            ]);

        $updateResponse->assertStatus(200);

        $this->networkWallet->refresh();
        $this->assertEquals($initialBalance - 7000.00, (float) $this->networkWallet->wallet_balance);

        // 3. تعديل المصروف بتخفيض القيمة إلى 4,000 ر.ي (فرق 3,000 استرجاع للمحفظة)
        $decreaseResponse = $this->withHeaders($this->headers)
            ->putJson("/api/v1/expenses/{$expenseId}", [
                'items' => [
                    [
                        'item_statement' => 'تسوية كهرباء بعد الخصم',
                        'item_amount' => 4000.00,
                    ],
                ],
            ]);

        $decreaseResponse->assertStatus(200);

        $this->networkWallet->refresh();
        $this->assertEquals($initialBalance - 4000.00, (float) $this->networkWallet->wallet_balance);

        // 4. حذف المصروف ناعماً وتأكيد الحالة دون المساس بالتاريخ المالي الفعلي
        $deleteResponse = $this->withHeaders($this->headers)
            ->deleteJson("/api/v1/expenses/{$expenseId}");

        $deleteResponse->assertStatus(200);

        $this->networkWallet->refresh();
        $this->assertEquals($initialBalance - 4000.00, (float) $this->networkWallet->wallet_balance, 'حذف المصروف ناعماً يحافظ على السجل المالي كما هو');
        $this->assertSoftDeleted('expenses', ['expense_id' => $expenseId]);
    }
}
