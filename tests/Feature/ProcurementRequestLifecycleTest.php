<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\SaleType;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\NetworkWalletLog;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\PosWalletLog;
use App\Models\Request as RequestModel;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProcurementRequestLifecycleTest extends TestCase
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
        $deviceUuid = 'PROCUREMENT-DEV-'.$suffix;

        UserDevice::firstOrCreate([
            'user_id' => $this->user->user_id,
            'device_uuid' => $deviceUuid,
        ], [
            'device_name' => 'PHPUnit Procurement Device',
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

        // 1. مزود الشبكة
        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'شبكة الاختبار '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        // 2. نقطة البيع تابعة للمستخدم
        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'نقطة المشتريات '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        // 3. الربط التجاري النشط
        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        // 4. باقة كروت بسعر كتالوج 1000 ريال وخصم افتراضي 5%
        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة 1000 '.$suffix,
            'package_color' => '#3B82F6',
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 5.00,
            'package_default_credit_discount' => 3.00,
            'status' => true,
        ]);

        // 5. خصم مخصص لنقطة البيع 12% نقداً
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'created_by' => $this->user->user_id,
            'discount_cash' => 12.00,
            'discount_credit' => 8.00,
            'status' => true,
        ]);

        // 6. أرصدة المحافظ الابتدائية
        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 50000.00,
            'status' => true,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);
    }

    /**
     * إثبات اعتماد طلب التوريد وتوليد الفاتورة المرتبطة وتسوية الرصيد بنجاح بعد فتح البوابة الرسمية
     */
    public function test_approval_gate_allows_authorized_network_approval_and_generates_linked_sale_invoice(): void
    {
        // 1. إنشاء طلب معلق
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 50,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.request_id');

        $initialPosBalance = (float) $this->posWallet->fresh()->wallet_balance;
        $initialNetworkBalance = (float) $this->networkWallet->fresh()->wallet_balance;

        // 2. الاعتماد الرسمي وتوليد الفاتورة
        $approveResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve", [
                'request_note' => 'اعتماد وتوليد الفاتورة الرسمية',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', RequestStatus::APPROVED->value)
            ->assertJsonPath('data.invoice.request_id', $requestId);

        $invoiceId = $approveResponse->json('data.invoice.invoice_id');
        $invoice = SaleInvoice::find($invoiceId);
        $this->assertNotNull($invoice);
        $this->assertEquals($requestId, $invoice->request_id);

        // 3. التحقق من تسوية المحافظ: رصيد الوكيل لم يمس لأن الدفع كاش يداً بيد ورصيد الشبكة زاد بقيمة الفاتورة
        $invoiceTotal = (float) $invoice->invoice_total_amount;
        $this->assertEquals($initialPosBalance, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals($initialNetworkBalance + $invoiceTotal, (float) $this->networkWallet->fresh()->wallet_balance);
    }

    public function test_authorized_pos_can_create_procurement_request_with_server_authoritative_pricing(): void
    {
        $initialPosBalance = (float) $this->posWallet->fresh()->wallet_balance;
        $initialNetworkBalance = (float) $this->networkWallet->fresh()->wallet_balance;
        $initialInvoicesCount = SaleInvoice::count();
        $initialPosLogsCount = PosWalletLog::count();
        $initialNetworkLogsCount = NetworkWalletLog::count();

        // العميل يرسل فقط معرف الباقة والكمية (مع محاولة تزوير سعر وخصم محلي لاختبار تجاهلها من الخادم)
        $payload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'request_note' => 'طلب توريد تجريبي للمرحلة 4A',
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 50,
                    'voucher_price' => 1.00,
                    'item_discount' => 99.00,
                ],
            ],
        ];

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'PENDING');

        $requestId = $response->json('data.request_id');
        $this->assertNotNull($requestId);

        // التحقق من الحسابات السيادية للخادم في بنود الطلب:
        // سعر الكتالوج = 1000، الخصم الفعال = 12%، الصافي للكرت = 880، إجمالي 50 كرت = 44000
        $requestRecord = RequestModel::with('items')->findOrFail($requestId);
        $item = $requestRecord->items->first();

        $this->assertEquals(1000.00, (float) $item->voucher_price, 'يجب اعتماد سعر الكتالوج السيادي');
        $this->assertEquals(12.00, (float) $item->item_discount, 'يجب اعتماد الخصم الفعال السيادي');
        $this->assertEquals(44000.00, (float) $item->item_total_amount, 'يجب أن يكون الصافي بعد الخصم 44000');
        $this->assertEquals(44000.00, (float) $requestRecord->request_total_amount);

        // تأكيد انعدام أي أثر مالي على المحافظ (Non-Financial Lifecycle)
        $this->assertEquals($initialPosBalance, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals($initialNetworkBalance, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals($initialInvoicesCount, SaleInvoice::count());
        $this->assertEquals($initialPosLogsCount, PosWalletLog::count());
        $this->assertEquals($initialNetworkLogsCount, NetworkWalletLog::count());
    }

    /**
     * إثبات عمل وسيط عدم التكرار (Idempotency) لنفس المفتاح ونفس البيانات
     */
    public function test_idempotency_same_key_same_payload_replays_cached_response_without_duplicate_records(): void
    {
        $idempotencyKey = 'vouch-test-'.uniqid();
        $headersWithKey = array_merge($this->headers, [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        $payload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 15,
                ],
            ],
        ];

        // 1. الطلب الأول
        $firstResponse = $this->withHeaders($headersWithKey)->postJson('/api/v1/requests', $payload);
        $firstResponse->assertStatus(201);
        $firstUuid = $firstResponse->json('data.request_uuid');

        $requestsCountAfterFirst = RequestModel::where('network_id', $this->network->network_id)
            ->where('pos_id', $this->pos->pos_id)
            ->count();

        // 2. تكرار نفس الطلب بذات المفتاح ونفس البيانات (إعادة المحاولة بسبب انقطاع شبكة)
        $replayResponse = $this->withHeaders($headersWithKey)->postJson('/api/v1/requests', $payload);
        $replayResponse->assertStatus(201);
        $replayResponse->assertHeader('X-Idempotency-Hit', 'true');
        $this->assertEquals($firstUuid, $replayResponse->json('data.request_uuid'));

        // 3. التحقق من عدم إنشاء أي سجل إضافي في قاعدة البيانات
        $requestsCountAfterReplay = RequestModel::where('network_id', $this->network->network_id)
            ->where('pos_id', $this->pos->pos_id)
            ->count();

        $this->assertEquals($requestsCountAfterFirst, $requestsCountAfterReplay, 'لا يجوز تكرار إنشاء الطلب عند تكرار نفس المفتاح');
    }

    /**
     * إثبات رفض استخدام نفس مفتاح عدم التكرار مع بيانات مختلفة (Conflict Detection)
     */
    public function test_idempotency_same_key_different_payload_is_rejected_as_conflict(): void
    {
        $idempotencyKey = 'vouch-conflict-'.uniqid();
        $headersWithKey = array_merge($this->headers, [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        $payloadOriginal = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 10,
                ],
            ],
        ];

        $payloadDifferent = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CREDIT', // تغيير نمط البيع مع نفس المفتاح
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 50,
                ],
            ],
        ];

        // 1. الطلب الأصلي
        $this->withHeaders($headersWithKey)->postJson('/api/v1/requests', $payloadOriginal)->assertStatus(201);

        // 2. الطلب المعارض بذات المفتاح ومحتوى مختلف
        $conflictResponse = $this->withHeaders($headersWithKey)->postJson('/api/v1/requests', $payloadDifferent);
        $conflictResponse->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_request_creation_denied_for_unlinked_network_pos(): void
    {
        // إنشاء شبكة ثانية غير مرتبطة بهذه النقطة
        $unlinkedNetwork = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'شبكة غير مرتبطة',
            'network_phone_number' => '71'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $unlinkedPackage = VoucherPackage::create([
            'network_id' => $unlinkedNetwork->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة الشبكة الغريبة',
            'package_color' => '#EF4444',
            'package_voucher_price' => 500.00,
            'status' => true,
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $unlinkedNetwork->network_id,
                'pos_id' => $this->pos->pos_id,
                'items' => [
                    [
                        'package_id' => $unlinkedPackage->package_id,
                        'voucher_count' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pos_id']);
    }

    public function test_pos_user_can_cancel_pending_request_with_zero_financial_effects(): void
    {
        // 1. إنشاء طلب معلق
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

        $initialPosBalance = (float) $this->posWallet->fresh()->wallet_balance;
        $initialNetworkBalance = (float) $this->networkWallet->fresh()->wallet_balance;
        $initialInvoicesCount = SaleInvoice::count();
        $initialPosLogsCount = PosWalletLog::count();
        $initialNetworkLogsCount = NetworkWalletLog::count();

        // 2. إلغاء الطلب من قبل نقطة البيع
        $cancelResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/cancel");

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertEquals(RequestStatus::CANCELLED, RequestModel::findOrFail($requestId)->status);

        // إثبات عدم حدوث أي تغييرات مالية عند الإلغاء
        $this->assertEquals($initialPosBalance, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals($initialNetworkBalance, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals($initialInvoicesCount, SaleInvoice::count());
        $this->assertEquals($initialPosLogsCount, PosWalletLog::count());
        $this->assertEquals($initialNetworkLogsCount, NetworkWalletLog::count());

        // 3. محاولة إلغاء طلب تم إلغاؤه مسبقاً يجب أن تفشل (حالة نهائية)
        $reCancelResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/cancel");

        $reCancelResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_network_owner_can_reject_pending_request_with_reason_and_zero_financial_effects(): void
    {
        // 1. إنشاء طلب معلق
        $createResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->network->network_id,
                'pos_id' => $this->pos->pos_id,
                'items' => [
                    [
                        'package_id' => $this->package->package_id,
                        'voucher_count' => 30,
                    ],
                ],
            ]);

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.request_id');

        $initialPosBalance = (float) $this->posWallet->fresh()->wallet_balance;
        $initialNetworkBalance = (float) $this->networkWallet->fresh()->wallet_balance;
        $initialInvoicesCount = SaleInvoice::count();
        $initialPosLogsCount = PosWalletLog::count();
        $initialNetworkLogsCount = NetworkWalletLog::count();

        // 2. رفض الطلب مع توثيق سبب الرفض
        $rejectResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/reject", [
                'rejection_reason' => 'نفاد مخزون السيريال للباقة المحددة',
            ]);

        $rejectResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'REJECTED');

        $request = RequestModel::findOrFail($requestId);
        $this->assertEquals(RequestStatus::REJECTED, $request->status);
        $this->assertStringContainsString('نفاد مخزون السيريال', $request->request_note);

        // إثبات عدم حدوث أي تغييرات مالية عند الرفض
        $this->assertEquals($initialPosBalance, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals($initialNetworkBalance, (float) $this->networkWallet->fresh()->wallet_balance);
        $this->assertEquals($initialInvoicesCount, SaleInvoice::count());
        $this->assertEquals($initialPosLogsCount, PosWalletLog::count());
        $this->assertEquals($initialNetworkLogsCount, NetworkWalletLog::count());

        // 3. محاولة رفض طلب مرفوض مسبقاً تفشل
        $reRejectResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/reject", [
                'rejection_reason' => 'تكرار الرفض',
            ]);

        $reRejectResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * اختبار حالات التزامن وتداخل الحالات (Concurrency & Cross-State Integrity)
     */
    public function test_lifecycle_concurrency_repeated_and_cross_state_transitions_rejected(): void
    {
        // 1. طلب ملغى: لا يمكن رفضه
        $cancelledReq = RequestModel::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH,
            'request_total_amount' => 10000,
            'status' => RequestStatus::CANCELLED,
            'created_by' => $this->user->user_id,
        ]);

        $rejectCancelledResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$cancelledReq->request_id}/reject", [
                'rejection_reason' => 'محاولة رفض طلب ملغى',
            ]);

        $rejectCancelledResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // 2. طلب مرفوض: لا يمكن إلغاؤه
        $rejectedReq = RequestModel::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH,
            'request_total_amount' => 10000,
            'status' => RequestStatus::REJECTED,
            'created_by' => $this->user->user_id,
        ]);

        $cancelRejectedResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$rejectedReq->request_id}/cancel");

        $cancelRejectedResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // 3. محاولة اعتماد طلب ملغى أو مرفوض تُرفض تماماً لأن الطلب ليس PENDING
        $approveCancelledResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$cancelledReq->request_id}/approve");

        $approveCancelledResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
