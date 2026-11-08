<?php

namespace Tests\Feature;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\SaleType;
use App\Enums\UserStatus;
use App\Enums\WalletOperationType;
use App\Http\Requests\API\V1\SaleInvoice\StoreSaleInvoiceRequest;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\NetworkWalletLog;
use App\Models\OutboxMessage;
use App\Models\Pos;
use App\Models\PosRepayment;
use App\Models\PosWallet;
use App\Models\PosWalletLog;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Services\SaleInvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone4CConcurrencyAndDirectInvoiceDebtTest extends TestCase
{
    protected User $networkOwner;

    protected User $posOwner;

    protected Network $network;

    protected Pos $pos;

    protected PosWallet $posWallet;

    protected NetworkWallet $networkWallet;

    protected VoucherPackage $package;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();

        $this->networkOwner = User::create([
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'مالك الشبكة المتزامنة '.$suffix,
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->network = Network::create([
            'owner_id' => $this->networkOwner->user_id,
            'network_name' => 'شبكة التزامن '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_uuid' => 'NET-W-'.$suffix,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);

        $this->posOwner = User::create([
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'وكيل نقطة البيع المتزامنة '.$suffix,
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->posOwner->user_id,
            'pos_name' => 'نقطة التزامن '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_uuid' => 'POS-W-'.$suffix,
            'wallet_balance' => 0.00,
            'credit_limit' => 200000.00,
            'status' => true,
        ]);

        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة التزامن '.$suffix,
            'package_voucher_price' => 5000.00,
            'package_hours' => 24,
            'package_price' => 5000.00,
            'status' => true,
            'created_by' => $this->networkOwner->user_id,
        ]);

        $deviceUuid = 'DEV-NET-'.$suffix;
        UserDevice::create([
            'user_id' => $this->networkOwner->user_id,
            'device_uuid' => $deviceUuid,
            'device_name' => 'Net Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $session = UserSession::create([
            'user_id' => $this->networkOwner->user_id,
            'device_uuid' => $deviceUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'Accept' => 'application/json',
            'X-Session-Id' => (string) $session->session_id,
            'X-Device-Uuid' => $deviceUuid,
            'X-Bypass-Encryption' => 'true',
        ];
    }

    /**
     * 1. التحقق من محاسبة الفاتورة المباشرة الآجلة وسدادها مع صيانة النقد المتاح والمحفظة.
     */
    public function test_direct_credit_invoice_accounting_and_repayment_settlement(): void
    {
        $this->posWallet->wallet_balance = 2000.00;
        $this->posWallet->save();

        /** @var SaleInvoiceService $invoiceService */
        $invoiceService = app(SaleInvoiceService::class);

        // إنشاء طلب فاتورة بيع مباشر بقيمة 10,000 ريال نوع CREDIT
        // رصيد المحفظة 2,000، لذا المدفوع مغطى 2,000، والمتبقي ذمة مدينة 8,000
        $request = StoreSaleInvoiceRequest::create('/api/v1/sale-invoices', 'POST', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CREDIT',
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 2,
                    'unit_price' => 5000.00,
                    'subtotal' => 10000.00,
                ],
            ],
            'notes' => 'فاتورة مباشرة لاختبار المحاسبة والديون',
        ]);
        $request->setUserResolver(fn () => $this->networkOwner);

        $invoice = $invoiceService->createDirectInvoice($request, $this->networkOwner->user_id);

        $invoice->refresh();
        $this->networkWallet->refresh();
        $this->posWallet->refresh();

        $this->assertEquals(10000.00, (float) $invoice->invoice_total_amount);
        $this->assertEquals(2000.00, (float) $invoice->paid_amount);
        $this->assertEquals(8000.00, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PARTIALLY_PAID, $invoice->payment_status);

        // النقد المتاح للشبكة زاد فقط بالمبلغ المغطى فعلياً (2,000)، وليس كامل الفاتورة
        $this->assertEquals(2000.00, (float) $this->networkWallet->wallet_balance);
        // محفظة نقطة البيع نزلت إلى -8,000
        $this->assertEquals(-8000.00, (float) $this->posWallet->wallet_balance);

        // يجب أن ينتج عن الفاتورة قيد شبكة واحد فقط بالمبلغ المغطى فعلياً.
        $networkLogs = NetworkWalletLog::query()
            ->where('wallet_id', $this->networkWallet->wallet_id)
            ->orderBy('log_id')
            ->get();

        $this->assertCount(1, $networkLogs);
        $this->assertSame(WalletOperationType::DEPOSIT, $networkLogs->sole()->log_operation);
        $this->assertSame('2000.00', $networkLogs->sole()->log_amount);

        // ويجب أن يسجل خصم الفاتورة كاملاً من محفظة نقطة البيع مرة واحدة.
        $posLogs = PosWalletLog::query()
            ->where('wallet_id', $this->posWallet->wallet_id)
            ->orderBy('log_id')
            ->get();

        $this->assertCount(1, $posLogs);
        $this->assertSame(WalletOperationType::WITHDRAW, $posLogs->sole()->log_operation_type);
        $this->assertSame('10000.00', $posLogs->sole()->log_amount);

        // الآن سداد الذمة المدينة الناتجة (8,000)
        $this->actingAs($this->networkOwner);
        $res = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 8000.00,
            'payment_method' => 'CASH',
        ]);

        $res->assertStatus(201);

        $invoice->refresh();
        $this->networkWallet->refresh();
        $this->posWallet->refresh();

        // الفاتورة أصبحت مسددة بالكامل
        $this->assertEquals(10000.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PAID, $invoice->payment_status);

        // رصيد محفظة نقطة البيع استعاد وضعه إلى 0.00
        $this->assertEquals(0.00, (float) $this->posWallet->wallet_balance);
        // النقد المتاح للشبكة زاد بـ 8,000 ليصبح إجمالي المقبوض نقداً 10,000
        $this->assertEquals(10000.00, (float) $this->networkWallet->wallet_balance);

        // دفتر الشبكة يجب أن يعكس مصدرَي التحصيل دون أي قيد مكرر.
        $networkLogs = NetworkWalletLog::query()
            ->where('wallet_id', $this->networkWallet->wallet_id)
            ->orderBy('log_id')
            ->get();

        $this->assertCount(2, $networkLogs);
        $this->assertSame(
            [WalletOperationType::DEPOSIT, WalletOperationType::DEPOSIT],
            $networkLogs->pluck('log_operation')->all()
        );
        $this->assertSame(['2000.00', '8000.00'], $networkLogs->pluck('log_amount')->all());

        // دفتر نقطة البيع: سحب إجمالي الفاتورة ثم إيداع مبلغ تسوية الدين مرة واحدة.
        $posLogs = PosWalletLog::query()
            ->where('wallet_id', $this->posWallet->wallet_id)
            ->orderBy('log_id')
            ->get();

        $this->assertCount(2, $posLogs);
        $this->assertSame(
            [WalletOperationType::WITHDRAW, WalletOperationType::DEPOSIT],
            $posLogs->pluck('log_operation_type')->all()
        );
        $this->assertSame(['10000.00', '8000.00'], $posLogs->pluck('log_amount')->all());
    }

    /**
     * 2. التحقق من منع التكرار (Idempotency) في سداد المديونية.
     */
    public function test_repayment_idempotency_prevents_duplicate_collection_and_balance_inflation(): void
    {
        // دين 15,000
        $this->posWallet->wallet_balance = -15000.00;
        $this->posWallet->save();

        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'invoice_uuid' => 'INV-IDEM-'.uniqid(),
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 15000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 15000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden'),
            'created_by' => $this->networkOwner->user_id,
        ]);

        $idempotencyKey = (string) Str::uuid();
        $headersWithIdempotency = array_merge($this->headers, [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        $payload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 10000.00,
            'payment_method' => 'CASH',
        ];

        $this->actingAs($this->networkOwner);

        // الطلب الأول
        $res1 = $this->withHeaders($headersWithIdempotency)->postJson('/api/v1/pos-debts/repayments', $payload);
        $res1->assertStatus(201);
        $repaymentId1 = $res1->json('data.repayment_id');

        $this->posWallet->refresh();
        $this->networkWallet->refresh();
        $this->assertEquals(-5000.00, (float) $this->posWallet->wallet_balance);
        $this->assertEquals(10000.00, (float) $this->networkWallet->wallet_balance);

        // الطلب الثاني بنفس مفتاح منع التكرار
        $res2 = $this->withHeaders($headersWithIdempotency)->postJson('/api/v1/pos-debts/repayments', $payload);
        $res2->assertStatus(201);
        $this->assertEquals($repaymentId1, $res2->json('data.repayment_id'));

        // التأكد من عدم تكرار السند أو تضخيم المحافظ
        $this->posWallet->refresh();
        $this->networkWallet->refresh();
        $this->assertEquals(-5000.00, (float) $this->posWallet->wallet_balance);
        $this->assertEquals(10000.00, (float) $this->networkWallet->wallet_balance);

        $repaymentsCount = PosRepayment::where('pos_id', $this->pos->pos_id)->count();
        $this->assertEquals(1, $repaymentsCount);
    }

    /**
     * 3. اختبار قفل الصفوف المتشائم (Row Locking & Concurrency Protection).
     */
    public function test_pessimistic_row_locking_protects_concurrent_repayment_allocations(): void
    {
        $this->posWallet->wallet_balance = -10000.00;
        $this->posWallet->save();

        $invoice = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'invoice_uuid' => 'INV-LOCK-'.uniqid(),
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 10000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden'),
            'created_by' => $this->networkOwner->user_id,
        ]);

        // نقوم بمحاكاة إجراء عمليتين متتاليتين في نطاق تحصيلي لضمان أن كل منهما تلتزم بقفل الصفوف
        // الأول يسدد 6,000، والثاني يسدد 4,000
        $this->actingAs($this->networkOwner);

        $res1 = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 6000.00,
        ])->assertStatus(201);

        $res2 = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 4000.00,
        ])->assertStatus(201);

        $invoice->refresh();
        $this->posWallet->refresh();
        $this->networkWallet->refresh();

        $this->assertEquals(10000.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PAID, $invoice->payment_status);
        $this->assertEquals(0.00, (float) $this->posWallet->wallet_balance);
        $this->assertEquals(10000.00, (float) $this->networkWallet->wallet_balance);

        // محاولة عملية ثالثة لسداد 1,000 بعد أن تم استيفاء كامل المديونية تفشل حتماً
        $res3 = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 1000.00,
        ])->assertStatus(422);

        $this->assertStringContainsString('لا توجد أي فواتير بيع آجل مستحقة السداد', $res3->json('errors.amount.0'));
    }

    /**
     * 4. التحقق من تشغيل أمر outbox:process لمعالجة رسائل الأحداث الموثوقة.
     */
    public function test_outbox_process_command_dispatches_pending_repayment_events(): void
    {
        $this->posWallet->wallet_balance = -5000.00;
        $this->posWallet->save();

        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'invoice_uuid' => 'INV-OUTBOX-'.uniqid(),
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => 5000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 5000.00,
            'payment_status' => InvoicePaymentStatus::UNPAID,
            'invoice_date' => now('Asia/Aden'),
            'created_by' => $this->networkOwner->user_id,
        ]);

        $this->actingAs($this->networkOwner);
        $res = $this->withHeaders($this->headers)->postJson('/api/v1/pos-debts/repayments', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'amount' => 5000.00,
        ])->assertStatus(201);

        $repaymentId = $res->json('data.repayment_id');

        $outbox = OutboxMessage::where('aggregate_type', 'POS_REPAYMENT')
            ->where('event_type', 'DEBT_REPAID')
            ->where('aggregate_id', (string) $repaymentId)
            ->first();

        $this->assertNotNull($outbox);
        $this->assertEquals('PENDING', $outbox->status);

        // تشغيل أمر المعالجة
        $exitCode = Artisan::call('outbox:process');
        $this->assertEquals(0, $exitCode);

        $outbox->refresh();
        $this->assertEquals('PUBLISHED', $outbox->status);
    }
}
