<?php

namespace Tests\Feature;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\SaleType;
use App\Enums\UserStatus;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\OutboxMessage;
use App\Models\Pos;
use App\Models\PosRepayment;
use App\Models\PosRepaymentAllocation;
use App\Models\PosWallet;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone4CDebtTrackingAndRepaymentTest extends TestCase
{
    protected User $superUser;

    protected User $networkOwner;

    protected User $posOwner;

    protected User $otherNetworkOwner;

    protected Network $network;

    protected Network $otherNetwork;

    protected Pos $pos;

    protected PosWallet $posWallet;

    protected NetworkWallet $networkWallet;

    protected VoucherPackage $package;

    protected array $headers;

    protected array $posHeaders;

    protected array $otherHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();

        // 1. سوبر أدمن النظام للاختبارات
        $this->superUser = User::firstOrFail();

        // 2. إنشاء مزود الشبكة وصاحبها
        $this->networkOwner = User::create([
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'مالك الشبكة '.$suffix,
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->network = Network::create([
            'owner_id' => $this->networkOwner->user_id,
            'network_name' => 'شبكة نت سيلز التجريبية '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        // 3. إنشاء محفظة الشبكة
        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_uuid' => 'NET-W-'.$suffix,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);

        // 4. إنشاء وكيل نقطة البيع وصاحبها
        $this->posOwner = User::create([
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'وكيل نقطة البيع '.$suffix,
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->posOwner->user_id,
            'pos_name' => 'نقطة الأمل '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        // ربط نقطة البيع بالشبكة
        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        // 5. إنشاء محفظة نقطة البيع
        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_uuid' => 'POS-W-'.$suffix,
            'wallet_balance' => 0.00,
            'credit_limit' => 100000.00,
            'status' => true,
        ]);

        // 6. إنشاء باقة كروت
        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة 1000 ريال '.$suffix,
            'package_voucher_price' => 1000.00,
            'package_hours' => 24,
            'package_price' => 1000.00,
            'status' => true,
            'created_by' => $this->networkOwner->user_id,
        ]);

        // 7. شبكة منافسة لاختبار عزل المستأجرين
        $this->otherNetworkOwner = User::create([
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'مالك شبكة أخرى '.$suffix,
            'user_phone_number' => '71'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        $this->otherNetwork = Network::create([
            'owner_id' => $this->otherNetworkOwner->user_id,
            'network_name' => 'شبكة منافسة '.$suffix,
            'network_phone_number' => '71'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        // جهاز وجلسة صاحب الشبكة
        $netDev = 'DEV-NET-'.$suffix;
        UserDevice::create([
            'user_id' => $this->networkOwner->user_id,
            'device_uuid' => $netDev,
            'device_name' => 'Net Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);
        $netSession = UserSession::create([
            'user_id' => $this->networkOwner->user_id,
            'device_uuid' => $netDev,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);
        $this->headers = [
            'X-Session-Id' => (string) $netSession->session_id,
            'X-Device-Uuid' => $netDev,
            'X-Bypass-Encryption' => 'true',
            'Accept' => 'application/json',
        ];

        // جهاز وجلسة صاحب نقطة البيع
        $posDev = 'DEV-POS-'.$suffix;
        UserDevice::create([
            'user_id' => $this->posOwner->user_id,
            'device_uuid' => $posDev,
            'device_name' => 'POS Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);
        $posSession = UserSession::create([
            'user_id' => $this->posOwner->user_id,
            'device_uuid' => $posDev,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);
        $this->posHeaders = [
            'X-Session-Id' => (string) $posSession->session_id,
            'X-Device-Uuid' => $posDev,
            'X-Bypass-Encryption' => 'true',
            'Accept' => 'application/json',
        ];

        // جهاز وجلسة صاحب الشبكة المنافسة
        $otherDev = 'DEV-OTHER-'.$suffix;
        UserDevice::create([
            'user_id' => $this->otherNetworkOwner->user_id,
            'device_uuid' => $otherDev,
            'device_name' => 'Other Dev',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);
        $otherSession = UserSession::create([
            'user_id' => $this->otherNetworkOwner->user_id,
            'device_uuid' => $otherDev,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);
        $this->otherHeaders = [
            'X-Session-Id' => (string) $otherSession->session_id,
            'X-Device-Uuid' => $otherDev,
            'X-Bypass-Encryption' => 'true',
            'Accept' => 'application/json',
        ];

        $this->actingAs($this->networkOwner);
    }

    /**
     * 1. إدارة السقف الائتماني: صاحب الشبكة يضبط ويرفع السقف بنجاح.
     */
    public function test_authorized_network_user_can_set_and_increase_credit_limit(): void
    {
        $res = $this->withHeaders($this->headers)->putJson("/api/v1/pos-wallets/{$this->posWallet->wallet_id}/credit-limit", [
            'credit_limit' => 150000.00,
        ]);

        $res->assertStatus(200);
        $this->assertEquals(150000.00, (float) $res->json('data.credit_limit'));
        $this->assertEquals(150000.00, (float) $this->posWallet->fresh()->credit_limit);

        // التحقق من تدوين قيد المتابعة الإدارية في سجل المحفظة
        $log = DB::table('pos_wallet_logs')->where('wallet_id', $this->posWallet->wallet_id)->latest('log_id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('تحديث السقف الائتماني', $log->log_statement);
    }

    /**
     * 2. منع تخفيض السقف الائتماني تحت المديونية القائمة ومنع إلغائه مع وجود دين.
     */
    public function test_prevent_unsafe_credit_limit_reduction_below_outstanding_debt(): void
    {
        // مديونية قائمة: -50,000 ر.ي تحت سقف 100,000 ر.ي
        $this->posWallet->wallet_balance = -50000.00;
        $this->posWallet->credit_limit = 100000.00;
        $this->posWallet->save();

        // محاولة تخفيض السقف إلى 40,000 (أقل من الدين 50,000) يجب أن تُرفض بـ 422
        $res1 = $this->withHeaders($this->headers)->putJson("/api/v1/pos-wallets/{$this->posWallet->wallet_id}/credit-limit", [
            'credit_limit' => 40000.00,
        ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['credit_limit']);

        // محاولة إلغاء السقف بالكامل (null) مع وجود دين قائم يجب أن تُرفض بـ 422
        $res2 = $this->withHeaders($this->headers)->putJson("/api/v1/pos-wallets/{$this->posWallet->wallet_id}/credit-limit", [
            'credit_limit' => null,
        ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['credit_limit']);

        // يظل السقف الائتماني السابق ثابتاً دون أي مساس
        $this->assertEquals(100000.00, (float) $this->posWallet->fresh()->credit_limit);
    }

    /**
     * 3. حظر نقطة البيع ومستخدمي الشبكات الأخرى من تعديل السقف الائتماني.
     */
    public function test_pos_user_and_cross_network_user_cannot_modify_credit_limit(): void
    {
        // 1. محاولة وكيل نقطة البيع تعديل سقفه الائتماني بنفسه -> 403 Forbidden
        $this->actingAs($this->posOwner);
        $res1 = $this->withHeaders($this->posHeaders)->putJson("/api/v1/pos-wallets/{$this->posWallet->wallet_id}/credit-limit", [
            'credit_limit' => 500000.00,
        ]);
        $res1->assertStatus(403);

        // 2. محاولة مالك شبكة أخرى تعديل سقف محفظة لا تخص شبكته -> 403 Forbidden
        $this->actingAs($this->otherNetworkOwner);
        $res2 = $this->withHeaders($this->otherHeaders)->putJson("/api/v1/pos-wallets/{$this->posWallet->wallet_id}/credit-limit", [
            'credit_limit' => 500000.00,
        ]);
        $res2->assertStatus(403);
    }

    /**
     * 4. سداد كامل لفاتورة بيع آجل واحدة وتحديث حالتها إلى PAID.
     */
    public function test_full_repayment_of_single_credit_invoice(): void
    {
        $this->posWallet->wallet_balance = -10000.00;
        $this->posWallet->save();

        $invoice = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 10000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden')->subDays(5),
            'created_by' => $this->networkOwner->user_id,
        ]);

        $this->actingAs($this->networkOwner);
        $res = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 10000.00,
            'payment_method' => 'CASH',
            'notes' => 'سداد كاش كامل',
        ]);

        $res->assertStatus(201);
        $repaymentId = $res->json('data.repayment_id');

        // الفاتورة أصبحت مسددة بالكامل
        $invoice->refresh();
        $this->assertEquals(10000.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PAID, $invoice->payment_status);

        // رصيد محفظة نقطة البيع زاد باتجاه الصفر ليصبح 0.00
        $this->assertEquals(0.00, (float) $this->posWallet->fresh()->wallet_balance);

        // رصيد محفظة الشبكة المتاح زاد بمبلغ النقد المحصل
        $this->assertEquals(10000.00, (float) $this->networkWallet->fresh()->wallet_balance);

        // التحقق من إنشاء سجل التوزيع
        $alloc = PosRepaymentAllocation::where('repayment_id', $repaymentId)->first();
        $this->assertNotNull($alloc);
        $this->assertEquals(10000.00, (float) $alloc->allocated_amount);
        $this->assertEquals($invoice->invoice_id, $alloc->invoice_id);

        // التحقق من تسجيل رسالة Outbox
        $this->assertDatabaseHas('outbox_messages', [
            'aggregate_type' => 'POS_REPAYMENT',
            'aggregate_id' => $repaymentId,
            'event_type' => 'DEBT_REPAID',
        ]);
    }

    /**
     * 5. سداد متعدد على عدة فواتير وفق خوارزمية FIFO الحتمية مع تغطية جزئية للفاتورة الأخيرة.
     */
    public function test_repayment_across_multiple_invoices_using_deterministic_fifo(): void
    {
        $this->posWallet->wallet_balance = -55000.00;
        $this->posWallet->save();

        // 3 فواتير بتواريخ مختلفة:
        // الفاتورة 1: أقدم فاتورة (قبل 20 يوم) بقيمة 20,000
        $inv1 = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 20000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 20000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden')->subDays(20),
            'created_by' => $this->networkOwner->user_id,
        ]);

        // الفاتورة 2: متوسطة (قبل 10 أيام) بقيمة 15,000
        $inv2 = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 15000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 15000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden')->subDays(10),
            'created_by' => $this->networkOwner->user_id,
        ]);

        // الفاتورة 3: حديثة (قبل يومين) بقيمة 20,000
        $inv3 = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 20000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 20000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden')->subDays(2),
            'created_by' => $this->networkOwner->user_id,
        ]);

        // نقوم بسداد بمبلغ 40,000 ر.ي:
        // التوزيع المتوقع:
        // - الفاتورة 1 تسدد بالكامل: 20,000 (متبقي 0 -> PAID)
        // - الفاتورة 2 تسدد بالكامل: 15,000 (متبقي 0 -> PAID)
        // - الفاتورة 3 يسدد منها المتبقي: 5,000 (متبقي 15,000 -> PARTIALLY_PAID)
        $this->actingAs($this->networkOwner);
        $res = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 40000.00,
            'payment_method' => 'BANK_TRANSFER',
        ]);

        $res->assertStatus(201);
        $repaymentId = $res->json('data.repayment_id');

        $inv1->refresh();
        $this->assertEquals(20000.00, (float) $inv1->paid_amount);
        $this->assertEquals(0.00, (float) $inv1->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PAID, $inv1->payment_status);

        $inv2->refresh();
        $this->assertEquals(15000.00, (float) $inv2->paid_amount);
        $this->assertEquals(0.00, (float) $inv2->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PAID, $inv2->payment_status);

        $inv3->refresh();
        $this->assertEquals(5000.00, (float) $inv3->paid_amount);
        $this->assertEquals(15000.00, (float) $inv3->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PARTIALLY_PAID, $inv3->payment_status);

        // التحقق من تطابق مجموع التوزيعات مع مبلغ السداد
        $totalAllocated = PosRepaymentAllocation::where('repayment_id', $repaymentId)->sum('allocated_amount');
        $this->assertEquals(40000.00, (float) $totalAllocated);

        // رصيد محفظة الوكيل: -55000 + 40000 = -15000
        $this->assertEquals(-15000.00, (float) $this->posWallet->fresh()->wallet_balance);
        // رصيد محفظة الشبكة زاد بـ 40,000
        $this->assertEquals(40000.00, (float) $this->networkWallet->fresh()->wallet_balance);
    }

    /**
     * 6. رفض السداد الفائض عن إجمالي المديونية المستحقة.
     */
    public function test_reject_repayment_exceeding_total_outstanding_debt(): void
    {
        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 10000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden'),
            'created_by' => $this->networkOwner->user_id,
        ]);

        $initialRepaymentsCount = PosRepayment::where('pos_id', $this->pos->pos_id)->count();

        $this->actingAs($this->networkOwner);
        // محاولة سداد 15,000 بينما الدين المستحق فقط 10,000
        $res = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 15000.00,
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
        $this->assertEquals($initialRepaymentsCount, PosRepayment::where('pos_id', $this->pos->pos_id)->count());
    }

    /**
     * 7. عكس سند السداد بدقة واسترجاع حالة الفواتير والمحافظ (Controlled Repayment Reversal).
     */
    public function test_repayment_reversal_restores_exact_invoices_and_wallet_balances(): void
    {
        $this->posWallet->wallet_balance = -20000.00;
        $this->posWallet->save();

        $invoice = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 20000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 20000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden'),
            'created_by' => $this->networkOwner->user_id,
        ]);

        $this->actingAs($this->networkOwner);
        // 1. تسجيل السداد أولاً
        $payRes = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 20000.00,
        ])->assertStatus(201);
        $repaymentId = $payRes->json('data.repayment_id');

        $this->assertEquals(0.00, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals(20000.00, (float) $this->networkWallet->fresh()->wallet_balance);

        // 2. تنفيذ العكس
        $revRes = $this->withHeaders($this->headers)->postJson("/api/v1/pos-debts/repayments/{$repaymentId}/reverse", [
            'reversal_reason' => 'شيك مرتجع بدون رصيد',
        ]);
        $revRes->assertStatus(200);

        // التحقق من استعادة الفاتورة لحالتها السابقة ومبلغها المستحق
        $invoice->refresh();
        $this->assertEquals(0.00, (float) $invoice->paid_amount);
        $this->assertEquals(20000.00, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::UNPAID, $invoice->payment_status);

        // التحقق من إعادة قيد الدين على محفظة الوكيل
        $this->assertEquals(-20000.00, (float) $this->posWallet->fresh()->wallet_balance);

        // التحقق من خصم المبلغ المسترد من محفظة الشبكة
        $this->assertEquals(0.00, (float) $this->networkWallet->fresh()->wallet_balance);

        // التحقق من وسم السند والتوزيعات كـ REVERSED
        $repayment = PosRepayment::find($repaymentId);
        $this->assertEquals('REVERSED', $repayment->status);
        $this->assertEquals('شيك مرتجع بدون رصيد', $repayment->reversal_reason);
        $this->assertEquals('REVERSED', $repayment->allocations()->first()->status);

        // محاولة تكرار العكس يجب أن تُرفض
        $this->withHeaders($this->headers)->postJson("/api/v1/pos-debts/repayments/{$repaymentId}/reverse", [
            'reversal_reason' => 'محاولة عكس ثانية',
        ])->assertStatus(422);
    }

    /**
     * 8. رفض العكس إذا كان رصيد محفظة الشبكة غير كافٍ لاسترداد النقد المحصل.
     */
    public function test_reject_reversal_if_network_wallet_balance_insufficient(): void
    {
        $this->posWallet->wallet_balance = -10000.00;
        $this->posWallet->save();

        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 10000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden'),
            'created_by' => $this->networkOwner->user_id,
        ]);

        $this->actingAs($this->networkOwner);
        $payRes = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 10000.00,
        ])->assertStatus(201);
        $repaymentId = $payRes->json('data.repayment_id');

        // سحب رصيد الشبكة المتاح لأي سبب تشغيلي ليصبح 0.00
        DB::table('network_wallets')
            ->where('wallet_id', $this->networkWallet->wallet_id)
            ->update(['wallet_balance' => 0.00]);

        // محاولة العكس تفشل لعدم كفاية رصيد محفظة الشبكة
        $revRes = $this->withHeaders($this->headers)->postJson("/api/v1/pos-debts/repayments/{$repaymentId}/reverse", [
            'reversal_reason' => 'عكس تجريبي',
        ]);

        $revRes->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    /**
     * 9. تحليل أعمار الديون (Multi-Invoice Aging) وتصنيف الفواتير في الشرائح الزمنية.
     */
    public function test_multi_invoice_aging_summary_and_bucket_classification(): void
    {
        $now = now('Asia/Aden');

        // فاتورة شريحة 0–30 يوماً: 5,000 (قبل 10 أيام)
        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 5000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 5000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => $now->copy()->subDays(10),
            'created_by' => $this->networkOwner->user_id,
        ]);

        // فاتورة شريحة 31–60 يوماً: 8,000 (قبل 40 يوماً)
        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 8000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 8000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => $now->copy()->subDays(40),
            'created_by' => $this->networkOwner->user_id,
        ]);

        // فاتورة شريحة 61–90 يوماً: 12,000 (قبل 75 يوماً)
        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 12000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 12000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => $now->copy()->subDays(75),
            'created_by' => $this->networkOwner->user_id,
        ]);

        // فاتورة شريحة أكثر من 90 يوماً: 20,000 (قبل 120 يوماً)
        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 20000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 20000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => $now->copy()->subDays(120),
            'created_by' => $this->networkOwner->user_id,
        ]);

        // فاتورة مسددة بالكامل (لا تدخل في الحسابات): 30,000
        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 30000.00,
            'paid_amount' => 30000.00,
            'remaining_amount' => 0.00,
            'payment_status' => InvoicePaymentStatus::PAID,
            'invoice_date' => $now->copy()->subDays(150),
            'created_by' => $this->networkOwner->user_id,
        ]);

        $this->actingAs($this->networkOwner);
        $res = $this->withHeaders($this->headers)->getJson("/api/v1/pos-debts/summary?network_id={$this->network->network_id}&pos_id={$this->pos->pos_id}");

        $res->assertStatus(200);
        $data = $res->json('data');

        $this->assertEquals(45000.00, (float) $data['total_outstanding_debt']);
        $this->assertEquals(4, $data['unpaid_invoices_count']);
        $this->assertEquals(0, $data['partially_paid_invoices_count']);

        $buckets = $data['aging_buckets'];
        $this->assertEquals(5000.00, (float) $buckets['days_0_30']);
        $this->assertEquals(8000.00, (float) $buckets['days_31_60']);
        $this->assertEquals(12000.00, (float) $buckets['days_61_90']);
        $this->assertEquals(20000.00, (float) $buckets['days_over_90']);
    }

    /**
     * 10. معالجة رسائل Outbox عبر مشغل الجدولة بنجاح (Outbox Command Processor).
     */
    public function test_outbox_command_processes_pending_financial_events(): void
    {
        // تسجيل حدث معلق في صندوق Outbox
        $msg = OutboxMessage::create([
            'message_uuid' => (string) Str::uuid(),
            'aggregate_type' => 'POS_REPAYMENT',
            'aggregate_id' => 999,
            'event_type' => 'DEBT_REPAID',
            'payload' => ['test' => true],
            'status' => 'PENDING',
            'retry_count' => 0,
            'max_retries' => 5,
        ]);

        $this->artisan('outbox:process')
            ->assertSuccessful();

        $this->assertEquals('PUBLISHED', $msg->fresh()->status);
    }
}
