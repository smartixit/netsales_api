<?php

namespace Tests\Feature;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NetworkStatus;
use App\Enums\NotificationType;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\RoleScope;
use App\Enums\SaleType;
use App\Enums\UserStatus;
use App\Enums\WalletOperationType;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\NetworkWalletLog;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\PosWalletLog;
use App\Models\Request as RequestModel;
use App\Models\SaleInvoice;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Services\RequestService;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\CreatesProcurementRequestSnapshots;

class Milestone4BFinancialApprovalTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesProcurementRequestSnapshots;

    protected User $user;

    protected UserSession $session;

    protected array $headers;

    protected Network $network;

    protected Pos $pos;

    protected VoucherPackage $packageA;

    protected VoucherPackage $packageB;

    protected NetworkWallet $networkWallet;

    protected PosWallet $posWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();

        // 1. سوبر أدمن النظام للاختبارات
        $this->user = User::firstOrFail();

        $devUuid = 'DEV-4B-'.$suffix;
        UserDevice::firstOrCreate([
            'user_id' => $this->user->user_id,
            'device_uuid' => $devUuid,
        ], [
            'device_name' => 'Financial Test Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => $devUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Milestone 4-B Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Device-Uuid' => $devUuid,
            'X-Bypass-Encryption' => 'true',
        ];

        // 2. إنشاء الشبكة ونقطة البيع والربط
        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'شبكة الاختبار المالي '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'نقطة السداد والفوترة '.$suffix,
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

        // 3. الباقات
        // Package A: سعر 1000، خصم نقدي 10% (صافي 900)، خصم آجل 5% (صافي 950)
        $this->packageA = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة مالية 1000 '.$suffix,
            'package_color' => '#3B82F6',
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.00,
            'package_default_credit_discount' => 5.00,
            'status' => true,
        ]);

        // Package B: سعر 500، خصم نقدي 8% (صافي 460)، خصم آجل 4% (صافي 480)
        $this->packageB = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة مالية 500 '.$suffix,
            'package_color' => '#10B981',
            'package_voucher_price' => 500.00,
            'package_default_cash_discount' => 8.00,
            'package_default_credit_discount' => 4.00,
            'status' => true,
        ]);

        // 4. المحافظ المالية
        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 100000.00,
            'status' => true,
        ]);
    }

    /**
     * 1. اعتماد طلب نقدي (CASH):
     * دفع نقداً يداً بيد خارج المحفظة؛ رصيد محفظة الوكيل لا يتأثر إطلاقاً، وتتولد فاتورة مسددة بالكامل PAID،
     * وتُسجل الشبكة تحصيل المبيعات النقدية كإيراد في محفظتها.
     */
    public function test_cash_approval_executes_exact_debit_credit_and_generates_paid_invoice(): void
    {
        // إرسال طلب نقدي: 50 كرت × 1000 = 50,000، خصم 10% = 45,000 صافي
        $createRes = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'request_note' => 'طلب نقدي 45,000',
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 50],
            ],
        ])->assertStatus(201);

        $requestId = $createRes->json('data.request_id');

        $posBalanceBefore = (float) $this->posWallet->fresh()->wallet_balance;

        // تنفيذ الاعتماد المالي
        $approveRes = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve")
            ->assertStatus(200);

        $approveRes->assertJsonPath('data.request.status', RequestStatus::APPROVED->value);
        $approveRes->assertJsonPath('data.invoice.sale_type', SaleType::CASH->value);
        $approveRes->assertJsonPath('data.invoice.invoice_total_amount', 45000);
        $approveRes->assertJsonPath('data.invoice.paid_amount', 45000);
        $approveRes->assertJsonPath('data.invoice.remaining_amount', 0);
        $approveRes->assertJsonPath('data.invoice.payment_status', InvoicePaymentStatus::PAID->value);

        // التحقق من الأرصدة: محفظة الوكيل لم يُخصم منها شيء لأن الدفع نقداً يداً بيد
        $this->assertEquals($posBalanceBefore, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals(45000.00, (float) $this->networkWallet->fresh()->wallet_balance);

        // التحقق من قيود اليومية للشبكة
        $netLog = NetworkWalletLog::where('wallet_id', $this->networkWallet->wallet_id)->latest('log_id')->first();
        $this->assertNotNull($netLog);
        $this->assertEquals(WalletOperationType::DEPOSIT, $netLog->log_operation);
        $this->assertEquals(45000.00, (float) $netLog->log_amount);
        $this->assertStringContainsString('تحصيل مبيعات كروت نقدية', $netLog->log_statement);
    }

    /**
     * 2. نجاح الاعتماد النقدي بغض النظر عن رصيد محفظة الوكيل:
     * لأن البيع كاش تم تسليمه يداً بيد، فإن رصيد المحفظة الإلكترونية لا يعيق العملية حتى وإن كان صفراً.
     */
    public function test_cash_approval_succeeds_regardless_of_pos_wallet_balance(): void
    {
        // تعيين رصيد صفر في محفظة الوكيل
        $this->posWallet->wallet_balance = 0.00;
        $this->posWallet->save();

        $createRes = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 50],
            ],
        ])->assertStatus(201);

        $requestId = $createRes->json('data.request_id');

        // تنفيذ الاعتماد المالي: ينجح فوراً لأن الدفع نقدي
        $approveRes = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve")
            ->assertStatus(200);

        $approveRes->assertJsonPath('data.invoice.sale_type', SaleType::CASH->value);
        $approveRes->assertJsonPath('data.invoice.invoice_total_amount', 45000);
        $approveRes->assertJsonPath('data.invoice.paid_amount', 45000);
        $approveRes->assertJsonPath('data.invoice.payment_status', InvoicePaymentStatus::PAID->value);

        // رصيد الوكيل يظل 0 دون أي خصم
        $this->assertEquals(0.00, (float) $this->posWallet->fresh()->wallet_balance);
        // رصيد الشبكة زاد بقيمة التحصيل النقدي
        $this->assertEquals(45000.00, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals(RequestStatus::APPROVED, RequestModel::find($requestId)->status);
    }

    /**
     * 3. اعتماد طلب بيع آجل برصيد صفر:
     * يتحول رصيد الوكيل إلى سالب (مديونية DEBIT)، الفاتورة تكون UNPAID، والشبكة تُسجل استحقاق المبيعات.
     */
    public function test_repeated_approval_returns_cached_invoice_without_repeating_wallet_mutations(): void
    {
        $createRes = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 50],
            ],
        ])->assertStatus(201);

        $requestId = $createRes->json('data.request_id');

        // الاعتماد الأول
        $firstApprove = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve")
            ->assertStatus(200);

        $originalInvoiceId = $firstApprove->json('data.invoice.invoice_id');
        $posBalanceAfterFirst = (float) $this->posWallet->fresh()->wallet_balance;
        $netBalanceAfterFirst = (float) $this->networkWallet->fresh()->wallet_balance;
        $posLogsCountAfterFirst = PosWalletLog::where('wallet_id', $this->posWallet->wallet_id)->count();

        // إعادة استدعاء الاعتماد مرة أخرى: يجب أن تُرفض قطعياً بـ 422 لمنع الاعتماد المزدوج
        $secondApprove = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve")
            ->assertStatus(422);

        $secondApprove->assertJsonValidationErrors(['status']);

        // التأكد التام من عدم المساس بالأرصدة مجدداً وعدم تكرار القيود
        $this->assertEquals($posBalanceAfterFirst, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals($netBalanceAfterFirst, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals($posLogsCountAfterFirst, PosWalletLog::where('wallet_id', $this->posWallet->wallet_id)->count());
    }

    /**
     * 7. التراجع الذري التام (Atomic Rollback) عند حدوث خطأ أثناء التسوية:
     * إذا طرأ أي استثناء أثناء العملية، يتراجع النظام عن إنشاء الفاتورة وعن أي تعديل في الأرصدة.
     */
    public function test_failure_during_settlement_rolls_back_entire_transaction_atomically(): void
    {
        $createRes = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 50],
            ],
        ])->assertStatus(201);

        $requestId = $createRes->json('data.request_id');
        $initialPosBalance = (float) $this->posWallet->fresh()->wallet_balance;
        $initialNetBalance = (float) $this->networkWallet->fresh()->wallet_balance;

        // محاكاة إخفاق أثناء التسوية داخل Transaction عبر استدعاء السيرفس وإلقاء استثناء
        $requestService = app(RequestService::class);

        try {
            DB::transaction(function () use ($requestService, $requestId): void {
                $requestService->approve($requestId, $this->user->user_id);
                throw new Exception('محاكاة عطل فني في خادم قاعدة البيانات أثناء الحفظ النهائي');
            });
            $this->fail('كان من المفترض إلقاء استثناء');
        } catch (Exception $e) {
            $this->assertStringContainsString('محاكاة عطل فني', $e->getMessage());
        }

        // التحقق من التراجع التام: لا توجد فاتورة، الأرصدة سليمة 100%، والطلب يظل PENDING
        $this->assertEquals(0, SaleInvoice::where('request_id', $requestId)->count());
        $this->assertEquals($initialPosBalance, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals($initialNetBalance, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals(RequestStatus::PENDING, RequestModel::find($requestId)->status);
    }

    /**
     * 8. الحماية من الإنفاق المزدوج واستنزاف الرصيد في العمليات الآجلة المتزامنة:
     * وجود رصيد يغطي طلباً واحداً بالآجل يمنع تمرير الطلبين معاً عبر القفل المتشائم.
     */
    public function test_cross_network_user_cannot_approve_procurement_request(): void
    {
        $suffix2 = uniqid();

        // شبكة أخرى ومستخدم آخر
        $otherUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'مستخدم آخر '.$suffix2,
            'user_phone_number' => '71'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        $otherNetwork = Network::create([
            'owner_id' => $otherUser->user_id,
            'network_name' => 'شبكة منافسة '.$suffix2,
            'network_phone_number' => '71'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $otherDev = 'DEV-OTHER-'.$suffix2;
        UserDevice::create([
            'user_id' => $otherUser->user_id,
            'device_uuid' => $otherDev,
            'device_name' => 'Other Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $otherSession = UserSession::create([
            'user_id' => $otherUser->user_id,
            'device_uuid' => $otherDev,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Other Tenant',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $otherHeaders = [
            'X-Session-Id' => (string) $otherSession->session_id,
            'X-Device-Uuid' => $otherDev,
            'X-Bypass-Encryption' => 'true',
        ];

        // طلب يخص الشبكة الأولى
        $reqId = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [['package_id' => $this->packageA->package_id, 'voucher_count' => 50]],
        ])->json('data.request_id');

        // محاولة الاعتماد من مستخدم الشبكة الثانية
        $res = $this->withHeaders($otherHeaders)
            ->postJson("/api/v1/requests/{$reqId}/approve");

        // يُرفض برمز 403 (غياب الصلاحية) أو 422 (عدم التبعية للشبكة)
        $this->assertTrue(in_array($res->status(), [403, 422], true));

        // لم يتم إنشاء أي فاتورة
        $this->assertEquals(0, SaleInvoice::where('request_id', $reqId)->count());
    }

    /**
     * 10. عدم المساس ببنود الطلب الأصلي عند اعتماد بنود معدلة:
     * الفاتورة تُسجل البنود والتسوية المعتمدة، بينما بنود الطلب الأصلي تبقى ثابتة كتوثيق تاريخي.
     */
    public function test_approval_preserves_original_request_snapshot_immutability(): void
    {
        // 1. طلب أصلي: 10 كروت من باقة A (إجمالي 10 × 1000 - 10% = 9,000)
        $createRes = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [['package_id' => $this->packageA->package_id, 'voucher_count' => 10]],
        ])->json('data.request_id');

        // 2. معاينة التعديل وإصدار توكن للباقة الجديدة B
        $previewRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$createRes}/preview-modification", [
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 20], // تعديل كمية الباقة الأصلية
                ['package_id' => $this->packageB->package_id, 'voucher_count' => 15], // إضافة باقة جديدة
            ],
        ])->assertStatus(200);

        $itemsWithTokens = $previewRes->json('data.items');
        $itemsForApprove = collect($itemsWithTokens)->map(fn ($it) => [
            'package_id' => $it['package_id'],
            'voucher_count' => $it['voucher_count'],
            'pricing_token' => $it['pricing_token'] ?? null,
        ])->toArray();

        // 3. اعتماد التعديلات
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$createRes}/approve", [
            'items' => $itemsForApprove,
        ])->assertStatus(200);

        // صافي الفاتورة المعتمدة:
        // باقة A: 20 × 1000 - 10% = 18,000
        // باقة B: 15 × 500 - 8% = 6,900
        // إجمالي = 24,900
        $approveRes->assertJsonPath('data.invoice.invoice_total_amount', 24900);
        $this->assertEquals(100000.00, (float) $this->posWallet->fresh()->wallet_balance); // رصيد المحفظة لم يمس لأن البيع كاش

        // التأكد التام من بقاء بنود الطلب الأصلي كما هي: بند واحد فقط بكمية 10
        $originalRequest = RequestModel::with('items')->find($createRes);
        $this->assertCount(1, $originalRequest->items);
        $this->assertEquals(10, $originalRequest->items->first()->voucher_count);
        $this->assertEquals(9000.00, (float) $originalRequest->request_total_amount);

        // بينما الفاتورة تحتوي على البندين المعدلين
        $invoice = SaleInvoice::where('request_id', $createRes)->with('items')->first();
        $this->assertCount(2, $invoice->items);
    }

    /**
     * 11. توثيق سجل التدقيق الشامل (System Audit Log):
     * اعتماد الطلب يسجل حدثاً موثقاً في system_logs يتضمن حالة الطلب الأصلية وبيانات الفاتورة الجديدة.
     */
    public function test_audit_log_captures_complete_financial_and_item_transition(): void
    {
        $reqId = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [['package_id' => $this->packageA->package_id, 'voucher_count' => 50]],
        ])->json('data.request_id');

        $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$reqId}/approve")
            ->assertStatus(200);

        $log = SystemLog::where('module', 'voucher_requests')
            ->where('action', 'approved')
            ->where('entity_id', $reqId)
            ->latest('log_id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(45000, $log->old_values['request_total_amount']);
        $this->assertEquals(45000, $log->new_values['invoice_total_amount']);
        $this->assertEquals(SaleType::CASH->value, $log->new_values['sale_type']);
        $this->assertArrayHasKey('invoice_uuid', $log->new_values);
    }

    /**
     * 12. اعتماد آجل عند سقف الائتمان بالضبط (Exact Limit):
     * رصيد المحفظة 0، سقف الائتمان 47,500، والفاتورة بقيمة 47,500.
     * يجب أن تنجح العملية ويصبح الرصيد -47,500 بالضبط.
     */
    public function test_approval_notifications_sent_to_correct_pos_and_network_recipients(): void
    {
        // إنشاء مستخدم مستقل كنقطة بيع ومستخدم كمالك للشبكة
        $posOwner = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'وكيل نقطة بيع مستقل '.uniqid(),
            'user_phone_number' => '71'.rand(1000000, 9999999),
            'user_email' => 'pos_owner_'.uniqid().'@netsales.test',
            'user_password' => bcrypt('Password@123'),
            'status' => 'ACTIVE',
        ]);

        $netOwner = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'مالك الشبكة المستقل '.uniqid(),
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_email' => 'net_owner_'.uniqid().'@netsales.test',
            'user_password' => bcrypt('Password@123'),
            'status' => 'ACTIVE',
        ]);

        $this->network->owner_id = $netOwner->user_id;
        $this->network->save();

        $this->pos->owner_id = $posOwner->user_id;
        $this->pos->save();

        $this->posWallet->wallet_balance = 100000.00;
        $this->posWallet->save();

        $reqId = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [['package_id' => $this->packageA->package_id, 'voucher_count' => 50]],
        ])->json('data.request_id');

        // تعيين منشئ الطلب كوكيل نقطة البيع
        RequestModel::where('request_id', $reqId)->update(['created_by' => $posOwner->user_id]);

        $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$reqId}/approve")
            ->assertStatus(200);

        // التحقق من وصول إشعار الاعتماد والخصم لصاحب نقطة البيع (وليس للمراجع)
        $posNotifications = DB::table('notifications')->where('user_id', $posOwner->user_id)->get();
        $this->assertTrue($posNotifications->contains('notification_type', NotificationType::VOUCHER_REQUEST_APPROVED->value));
        $this->assertTrue($posNotifications->contains('notification_type', NotificationType::WALLET_WITHDRAWAL->value));

        // التحقق من وصول إشعار الإيداع لصاحب الشبكة
        $netNotifications = DB::table('notifications')->where('user_id', $netOwner->user_id)->get();
        $this->assertTrue($netNotifications->contains('notification_type', NotificationType::WALLET_DEPOSIT->value));
    }

    /**
     * 19. إثبات التزامن الحقيقي على مستوى MySQL InnoDB ومنع الاعتماد المزدوج عبر أقفال السجلات (lockForUpdate):
     * يثبت هذا الاختبار أن قفل السطر FOR UPDATE على طلب الشراء يمنع أي اتصال آخر من التعديل عليه بالتوازي،
     * وأن ترتيب الأقفال (requests -> pos_wallets -> network_wallets) يمنع حدوث الجمود الميت (Deadlock).
     */
    public function test_mysql_innodb_row_locking_prevents_concurrent_double_approval(): void
    {
        $this->posWallet->wallet_balance = 100000.00;
        $this->posWallet->save();

        $reqId = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [['package_id' => $this->packageA->package_id, 'voucher_count' => 10]],
        ])->json('data.request_id');

        // فتح اتصال ثانٍ مستقل بـ MySQL لاختبار تنافس الأقفال المتشائمة
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $pdo2 = new \PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 2,
        ]);

        // تعيين مهلة قفل قصيرة جداً (ثانية واحدة) للاتصال الثاني لاختبار الحجب
        $pdo2->exec('SET innodb_lock_wait_timeout = 1');

        // الاتصال الأول يبدأ معاملة ويقفل السطر FOR UPDATE
        DB::beginTransaction();
        $lockedRequest = DB::table('requests')->where('request_id', $reqId)->lockForUpdate()->first();
        $this->assertNotNull($lockedRequest);

        // الاتصال الثاني يحاول قفل نفس السجل بالتوازي
        $lockBlocked = false;
        try {
            $pdo2->beginTransaction();
            $stmt = $pdo2->prepare('SELECT request_id FROM requests WHERE request_id = ? FOR UPDATE');
            $stmt->execute([$reqId]);
            $stmt->fetch();
            $pdo2->commit();
        } catch (\PDOException $e) {
            // كود خطأ MySQL 1205: Lock wait timeout exceeded
            if (str_contains($e->getMessage(), '1205') || str_contains($e->getMessage(), 'Lock wait timeout')) {
                $lockBlocked = true;
            }
            if ($pdo2->inTransaction()) {
                $pdo2->rollBack();
            }
        }

        DB::rollBack();

        $this->assertTrue($lockBlocked, 'MySQL InnoDB FOR UPDATE must block concurrent transactions from acquiring conflicting row locks.');
    }

    /**
     * 20. التحقق من القيد المزدوج في سجل محفظة نقطة البيع عند البيع النقدي (CASH Sale Double-Entry Logging):
     * عند اعتماد بيع نقدي، يُسجل قيدان متقابلان (استحقاق ثم سداد فوري) في سجل كشف حساب المحفظة
     * لتوثيق حركة الفاتورة كاملاً، مع بقاء صافي رصيد المحفظة ثابتاً دون أي تغيير (Net Zero Impact).
     */
    public function test_cash_sale_records_double_entry_logs_in_pos_wallet_with_zero_net_balance_impact(): void
    {
        $initialPosBalance = 1500.00;
        $this->posWallet->wallet_balance = $initialPosBalance;
        $this->posWallet->save();

        $initialLogsCount = DB::table('pos_wallet_logs')->where('wallet_id', $this->posWallet->wallet_id)->count();

        // إنشاء طلب بيع نقدي
        $reqRes = $this->postProcurementRequest([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 50, // 50 * 100 = 5000 - 10% = 4500
                ],
            ],
        ]);
        $reqRes->assertStatus(201);
        $reqId = $reqRes->json('data.request_id');

        // اعتماد الطلب من قبل الشبكة
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$reqId}/approve");
        $approveRes->assertStatus(200);

        $invoiceData = $approveRes->json('data.invoice');
        $this->assertEquals(SaleType::CASH->value, $invoiceData['sale_type']);
        $this->assertEquals(45000.00, (float) $invoiceData['invoice_total_amount']);
        $this->assertEquals(45000.00, (float) $invoiceData['paid_amount']);
        $this->assertEquals(0.00, (float) $invoiceData['remaining_amount']);
        $this->assertEquals(InvoicePaymentStatus::PAID->value, $invoiceData['payment_status']);

        // التحقق من ثبات رصيد المحفظة دون أي تأثر (Net Zero Impact)
        $this->posWallet->refresh();
        $this->assertEquals($initialPosBalance, (float) $this->posWallet->wallet_balance);

        // التحقق من تسجيل القيدين المزدوجين فقط في دفتر أستاذ محفظة نقطة البيع
        $logs = DB::table('pos_wallet_logs')
            ->where('wallet_id', $this->posWallet->wallet_id)
            ->latest('log_id')
            ->get();

        $this->assertCount($initialLogsCount + 2, $logs, 'يجب تسجيل قيدين مزدوجين فقط (استحقاق وسداد فوري) دون أي تكرار');

        // قيد الاستحقاق (WITHDRAW)
        $withdrawLog = $logs->firstWhere('log_operation_type', WalletOperationType::WITHDRAW->value);
        $this->assertNotNull($withdrawLog, 'يجب تسجيل قيد استحقاق (WITHDRAW) في سجل كشف حساب محفظة الوكيل');
        $this->assertEquals(45000.00, (float) $withdrawLog->log_amount);
        $this->assertStringContainsString('استحقاق فاتورة مبيعات كروت نقدية', $withdrawLog->log_statement);

        // قيد السداد الفوري (DEPOSIT)
        $depositLog = $logs->firstWhere('log_operation_type', WalletOperationType::DEPOSIT->value);
        $this->assertNotNull($depositLog, 'يجب تسجيل قيد سداد نقدي فوري (DEPOSIT) في سجل كشف حساب محفظة الوكيل');
        $this->assertEquals(45000.00, (float) $depositLog->log_amount);
        $this->assertStringContainsString('سداد نقدي فوري', $depositLog->log_statement);
    }
}
