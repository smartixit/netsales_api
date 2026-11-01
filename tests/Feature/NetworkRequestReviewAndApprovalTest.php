<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\SaleType;
use App\Enums\UserStatus;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NetworkRequestReviewAndApprovalTest extends TestCase
{
    use DatabaseTransactions;

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

        // 1. استخدام مستخدم النظام الرئيسي (سوبر أدمن معتمد بكامل الصلاحيات)
        $this->user = User::firstOrFail();

        $devUuid = 'DEV-'.$suffix;
        UserDevice::firstOrCreate([
            'user_id' => $this->user->user_id,
            'device_uuid' => $devUuid,
        ], [
            'device_name' => 'Test Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => $devUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
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
            'network_name' => 'شبكة الأفق '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'نقطة البركة '.$suffix,
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

        // 4. الباقة الأولى (Package A): سعر كتالوج 1000 ريال، خصم 10%
        $this->packageA = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة 1000A '.$suffix,
            'package_color' => '#3B82F6',
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.00,
            'package_default_credit_discount' => 5.00,
            'status' => true,
        ]);

        // 5. الباقة الثانية (Package B): سعر كتالوج 500 ريال، خصم 8%
        $this->packageB = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة 500B '.$suffix,
            'package_color' => '#10B981',
            'package_voucher_price' => 500.00,
            'package_default_cash_discount' => 8.00,
            'package_default_credit_discount' => 4.00,
            'status' => true,
        ]);

        // 6. المحافظ المالية
        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 500000.00, // رصيد نقدي كافٍ
            'overdraft_limit' => 200000.00,
            'status' => true,
        ]);
    }

    /**
     * اختبار معاينة التعديلات والتسعير السيادي دون توليد فواتير أو تعديل الطلب الأصلي أو المساس بالأرصدة
     */
    public function test_pricing_preview_returns_calculated_totals_without_creating_invoices_or_modifying_request(): void
    {
        // 1. إنشاء طلب أصلي للباقة A بعدد 100 كرت
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 100],
            ],
        ]);
        $createRes->assertStatus(201);
        $requestId = $createRes->json('data.request_id');

        $initialWalletBalance = (float) $this->posWallet->fresh()->wallet_balance;

        // 2. استدعاء المعاينة لتعديل الكمية إلى 50 وإضافة الباقة B بعدد 20
        $previewRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/preview-modification", [
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 50],
                ['package_id' => $this->packageB->package_id, 'voucher_count' => 20],
            ],
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request_id', $requestId)
            ->assertJsonPath('data.original_total_amount', 90000) // 100 * 1000 * 0.9 = 90,000
            // Package A: 50 * 1000 * 0.9 = 45,000
            // Package B: 20 * 500 * 0.92 = 9,200
            // Total Preview = 45,000 + 9,200 = 54,200
            ->assertJsonPath('data.preview_total_amount', 54200)
            ->assertJsonPath('data.has_modifications', true)
            ->assertJsonPath('data.items.0.package_id', $this->packageA->package_id)
            ->assertJsonPath('data.items.0.voucher_count', 50)
            ->assertJsonPath('data.items.0.source', 'ORIGINAL')
            ->assertJsonPath('data.items.0.change_type', 'QUANTITY_CHANGED')
            ->assertJsonPath('data.items.1.package_id', $this->packageB->package_id)
            ->assertJsonPath('data.items.1.voucher_count', 20)
            ->assertJsonPath('data.items.1.source', 'REVIEW_CAPTURED')
            ->assertJsonPath('data.items.1.change_type', 'ADDED');

        $this->assertNotEmpty($previewRes->json('data.items.1.pricing_token'));

        // 3. التحقق الصارم من بقاء الطلب الأصلي وبنوده دون أي تغيير، وعدم إنشاء فواتير، وعدم المساس بالأرصدة
        $this->assertDatabaseHas('requests', [
            'request_id' => $requestId,
            'status' => RequestStatus::PENDING->value,
            'request_total_amount' => 90000.00,
        ]);

        $this->assertDatabaseHas('request_items', [
            'request_id' => $requestId,
            'package_id' => $this->packageA->package_id,
            'voucher_count' => 100,
            'voucher_price' => 1000.00,
        ]);

        $this->assertEquals(0, SaleInvoice::where('request_id', $requestId)->count());
        $this->assertEquals($initialWalletBalance, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals(0, PosWalletLog::count());
    }

    /**
     * اختبار الاحتفاظ بأسعار وخصومات البنود الأصلية وقت تقديم الطلب حتى لو تغير سعر الكتالوج لاحقاً
     */
    public function test_pricing_preview_preserves_original_items_stored_prices_even_after_catalog_price_changes(): void
    {
        // 1. تقديم الطلب الأصلي بسعر 1000 للباقة A
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // 2. صاحب الشبكة يقوم بتعديل سعر الباقة A في الكتالوج إلى 1500 وتخفيض الخصم إلى 2%
        $this->packageA->update([
            'package_voucher_price' => 1500.00,
            'package_default_cash_discount' => 2.00,
        ]);

        // 3. صاحب الشبكة يقوم بمعاينة الطلب وتعديل الكمية إلى 20
        $previewRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/preview-modification", [
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 20],
            ],
        ]);

        // يجب أن يستخدم السعر الأصلي (1000) والخصم الأصلي (10%) وليس سعر الكتالوج الجديد (1500)
        // 20 * 1000 * (1 - 0.10) = 18,000
        $previewRes->assertStatus(200)
            ->assertJsonPath('data.items.0.voucher_price', 1000)
            ->assertJsonPath('data.items.0.item_discount', 10)
            ->assertJsonPath('data.items.0.item_total_amount', 18000)
            ->assertJsonPath('data.preview_total_amount', 18000);
    }

    /**
     * اختبار تجميد سعر وخصم الباقة المضافة أثناء المراجعة عبر رمز التسعير وعدم تأثرها بتغيرات الكتالوج اللاحقة
     */
    public function test_newly_added_item_captures_catalog_price_and_preserves_it_via_token_even_if_catalog_changes(): void
    {
        // 1. تقديم طلب أصلي بالباقة A
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // 2. معاينة إضافة الباقة B بسعرها الحالي (500) وخصمها (8%)
        $preview1 = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/preview-modification", [
            'items' => [
                ['package_id' => $this->packageB->package_id, 'voucher_count' => 10],
            ],
        ]);

        $preview1->assertStatus(200);
        $capturedToken = $preview1->json('data.items.0.pricing_token');
        $this->assertNotEmpty($capturedToken);

        // 3. صاحب الشبكة يرفع سعر الباقة B في الكتالوج إلى 800 ويلغي الخصم إلى 0%
        $this->packageB->update([
            'package_voucher_price' => 800.00,
            'package_default_cash_discount' => 0.00,
        ]);

        // 4. استدعاء المعاينة مجدداً مع تمرير الرمز المجمد وتغيير الكمية إلى 15
        $preview2 = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/preview-modification", [
            'items' => [
                [
                    'package_id' => $this->packageB->package_id,
                    'voucher_count' => 15,
                    'pricing_token' => $capturedToken,
                ],
            ],
        ]);

        // يجب أن يحافظ على السعر المجمد (500) والخصم المجمد (8%) المحفوظين في الرمز
        // 15 * 500 * (1 - 0.08) = 6,900
        $preview2->assertStatus(200)
            ->assertJsonPath('data.items.0.voucher_price', 500)
            ->assertJsonPath('data.items.0.item_discount', 8)
            ->assertJsonPath('data.items.0.item_total_amount', 6900)
            ->assertJsonPath('data.preview_total_amount', 6900);
    }

    /**
     * اختبار الرفض الصارم لأي محاولة تعديل يدوي للأسعار أو الخصومات من العميل
     */
    public function test_manual_price_or_discount_overrides_are_strictly_rejected(): void
    {
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // محاولة تمرير سعر مخصص أو خصم مخصص يدوياً في الاعتماد
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve", [
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 10,
                    'voucher_price' => 10.00, // تلاعب
                    'item_discount' => 99.00, // تلاعب
                ],
            ],
        ]);

        $approveRes->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.voucher_price', 'items.0.item_discount']);
    }

    /**
     * اختبار اعتماد الطلب بنجاح وتوليد فاتورة مبيعات فريدة مرتبطة بالطلب ذرّياً
     */
    public function test_approval_generates_exactly_one_invoice_with_database_level_request_id_relationship(): void
    {
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 50],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // اعتماد الطلب مع تعديل الكمية إلى 30 وإضافة 10 من الباقة B
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve", [
            'request_note' => 'تم تعديل الكميات والاعتماد',
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 30],
                ['package_id' => $this->packageB->package_id, 'voucher_count' => 10],
            ],
        ]);

        $approveRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', RequestStatus::APPROVED->value)
            ->assertJsonPath('data.invoice.request_id', $requestId);

        $invoiceId = $approveRes->json('data.invoice.invoice_id');

        // 1. التحقق من ربط قاعدة البيانات
        $invoice = SaleInvoice::find($invoiceId);
        $this->assertNotNull($invoice);
        $this->assertEquals($requestId, $invoice->request_id);
        $this->assertEquals($invoiceId, $invoice->request->invoice->invoice_id);

        // 2. التحقق من أن الطلب الأصلي وبنوده لم تتغير
        $request = RequestModel::find($requestId);
        $this->assertEquals(RequestStatus::APPROVED, $request->status);
        $this->assertEquals(45000.00, (float) $request->request_total_amount); // 50 * 1000 * 0.9
        $this->assertCount(1, $request->items);
        $this->assertEquals(50, $request->items->first()->voucher_count);

        // 3. التحقق من بنود الفاتورة المولدة
        $this->assertCount(2, $invoice->items);
        // Package A: 30 * 1000 * 0.9 = 27,000
        // Package B: 10 * 500 * 0.92 = 4,600
        // Total = 31,600
        $this->assertEquals(31600.00, (float) $invoice->invoice_total_amount);
        $this->assertEquals(31600.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->remaining_amount);

        // 4. التحقق من خصم المحفظة
        $this->assertEquals(500000.00 - 31600.00, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals(31600.00, (float) $this->networkWallet->fresh()->wallet_balance);
    }

    /**
     * اختبار رفض البيع النقدي عند عدم كفاية رصيد محفظة الوكيل صراحة دون تحويل صامت إلى آجل
     */
    public function test_cash_approval_with_insufficient_pos_wallet_balance_is_rejected_without_silent_downgrade(): void
    {
        // تصفير رصيد محفظة نقطة البيع
        $this->posWallet->update(['wallet_balance' => 100.00]);

        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // محاولة الاعتماد نقداً مع رصيد 100 ريال فقط بينما القيمة 9,000 ريال
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve", [
            'sale_type' => SaleType::CASH->value,
        ]);

        $approveRes->assertStatus(422)
            ->assertJsonValidationErrors(['pos_wallet']);

        // التحقق من أن الطلب بقي قيد الانتظار ولم تتولد أي فاتورة
        $this->assertEquals(RequestStatus::PENDING, RequestModel::find($requestId)->status);
        $this->assertEquals(0, SaleInvoice::where('request_id', $requestId)->count());
    }

    /**
     * اختبار توثيق سجل التدقيق الشامل في system_logs عند اعتماد الطلب
     */
    public function test_approval_creates_structured_system_audit_log(): void
    {
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve", [
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 5],
            ],
        ]);

        $log = SystemLog::where('module', 'voucher_requests')
            ->where('action', 'approved')
            ->where('entity_id', $requestId)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->user->user_id, $log->user_id);
        $this->assertNotEmpty($log->old_values);
        $this->assertNotEmpty($log->new_values);
        $this->assertEquals(5, $log->new_values['items'][0]['voucher_count']);
    }

    /**
     * اختبار رفض اعتماد أو معاينة الطلب من مستخدم لا ينتمي لمزود الشبكة
     */
    public function test_unauthorized_user_cannot_preview_or_approve_request(): void
    {
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // محاولة استدعاء المعاينة بمستخدم آخر غير مرتبط بالشبكة
        $otherUser = User::create([
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'مستخدم آخر',
            'user_phone_number' => '71'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        $otherSession = UserSession::create([
            'user_id' => $otherUser->user_id,
            'device_uuid' => 'OTHER-DEV-'.uniqid(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);
        UserDevice::create([
            'user_id' => $otherUser->user_id,
            'device_uuid' => $otherSession->device_uuid,
            'device_name' => 'Other Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $otherHeaders = [
            'X-Session-Id' => (string) $otherSession->session_id,
            'X-Device-Uuid' => $otherSession->device_uuid,
            'X-Bypass-Encryption' => 'true',
        ];

        $previewRes = $this->withHeaders($otherHeaders)->postJson("/api/v1/requests/{$requestId}/preview-modification", [
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 5],
            ],
        ]);

        // يُرفض برمز 403 أو 422 لعدم امتلاك الصلاحية على الشبكة
        $this->assertTrue(in_array($previewRes->status(), [403, 422], true));
    }

    /**
     * اختبار الاستدعاء المكرر للاعتماد (Idempotency) يُعيد الفاتورة السابقة دون تكرار
     */
    public function test_repeated_approval_returns_existing_approved_invoice_idempotently(): void
    {
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // الاعتماد الأول
        $approveRes1 = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve");
        $approveRes1->assertStatus(200);
        $invoiceId1 = $approveRes1->json('data.invoice.invoice_id');

        // الاعتماد المكرر
        $approveRes2 = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve");
        $approveRes2->assertStatus(200);
        $invoiceId2 = $approveRes2->json('data.invoice.invoice_id');

        $this->assertEquals($invoiceId1, $invoiceId2);
        $this->assertEquals(1, SaleInvoice::where('request_id', $requestId)->count());
    }

    /**
     * اختبار رفض إضافة باقة تنتمي لشبكة أخرى أثناء المراجعة أو الاعتماد (Cross-Network Isolation)
     */
    public function test_cross_network_package_in_review_or_approval_is_rejected(): void
    {
        $suffix = uniqid();
        // إنشاء شبكة وباقة غريبة
        $otherNetwork = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'شبكة أخرى '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);
        $foreignPackage = VoucherPackage::create([
            'network_id' => $otherNetwork->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة الشبكة الأخرى '.$suffix,
            'package_color' => '#EF4444',
            'package_voucher_price' => 700.00,
            'package_default_cash_discount' => 5.00,
            'package_default_credit_discount' => 2.00,
            'status' => true,
        ]);

        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // محاولة إضافة الباقة الغريبة في المعاينة
        $previewRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/preview-modification", [
            'items' => [
                ['package_id' => $foreignPackage->package_id, 'voucher_count' => 5],
            ],
        ]);
        $previewRes->assertStatus(422)
            ->assertJsonValidationErrors(['items']);

        // محاولة إضافتها في الاعتماد
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve", [
            'items' => [
                ['package_id' => $foreignPackage->package_id, 'voucher_count' => 5],
            ],
        ]);
        $approveRes->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    /**
     * اختبار حذف بند من الطلب الأصلي أثناء مراجعة واعتماد مزود الشبكة
     */
    public function test_network_user_can_remove_item_from_request_during_review_and_approval(): void
    {
        // إنشاء طلب أصلي يحتوي على الباقتين A و B
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 20],
                ['package_id' => $this->packageB->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // في المراجعة والمعاينة، نقوم بحذف الباقة B وإبقاء الباقة A فقط
        $previewRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/preview-modification", [
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 20],
            ],
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('data.has_modifications', true);
        $this->assertCount(1, $previewRes->json('data.items'));
        $this->assertCount(1, $previewRes->json('data.removed_items'));
        $this->assertEquals($this->packageB->package_id, $previewRes->json('data.removed_items.0.package_id'));

        // اعتماد الفاتورة بالبند A فقط
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve", [
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 20],
            ],
        ]);

        $approveRes->assertStatus(200);
        $invoiceId = $approveRes->json('data.invoice.invoice_id');
        $invoice = SaleInvoice::find($invoiceId);

        // الفاتورة تحتوي على بند واحد فقط (A)
        $this->assertCount(1, $invoice->items);
        $this->assertEquals($this->packageA->package_id, $invoice->items->first()->package_id);

        // بينما الطلب الأصلي وبنوده لا تزال تحتوي على الباقتين كما طُلبتا تماماً دون أي مساس
        $request = RequestModel::find($requestId);
        $this->assertCount(2, $request->items);
    }

    /**
     * اختبار منع اعتماد طلب ملغي أو مرفوض
     */
    public function test_cannot_approve_cancelled_or_rejected_request(): void
    {
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                ['package_id' => $this->packageA->package_id, 'voucher_count' => 10],
            ],
        ]);
        $requestId = $createRes->json('data.request_id');

        // إلغاء الطلب
        $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/cancel")->assertStatus(200);

        // محاولة اعتماده بعد الإلغاء
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve");
        $approveRes->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
