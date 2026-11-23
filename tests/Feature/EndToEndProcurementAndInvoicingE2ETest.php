<?php

namespace Tests\Feature;

use App\Enums\InvoicePaymentStatus;
use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Enums\SaleType;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\PosWallet;
use App\Models\Role;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
use App\Services\ProcurementPricingSnapshotService;
use App\Services\VoucherPackageDiscountService;
use App\Support\DecimalAmount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * اختبار تكاملي شامل ومستقل لدورة حياة طلبات التوريد وفواتير المبيعات (E2E Integration Test Suite).
 *
 * يختبر كافة سيناريوهات المستخدم الحقيقية، والقيود المحاسبية، والحماية من التجاوزات:
 * 1. احتساب عروض الأسعار مع تطبيق الخصومات التعاقدية للوكيل.
 * 2. إنشاء طلب توريد نقدي باستخدام توكن التعرفة الرسمي الموثق.
 * 3. حظر طلب باقة لا تنتمي لمزود الشبكة الموجه له الطلب (422).
 * 4. إلغاء الطلب من قِبل الوكيل وهو في حالة الانتظار (PENDING).
 * 5. رفض الطلب من قِبل الشبكة مع إلزامية بيان سبب الرفض المبرر.
 * 6. منع الانتقال غير القانوني بين الحالات (تعديل/إلغاء/رفض طلب معتمد أو ملغي مسبقاً).
 * 7. معاينة تعديل كميات الطلب من قبل الشبكة واعتماده نقداً مع الفوترة والخصم من المحفظة ذرياً.
 * 8. اعتماد طلب آجل (CREDIT) مع دفعة نقدية جزئية وتحديث سقف المديونية وحالة الفاتورة (PARTIALLY_PAID).
 * 9. رفض اعتماد طلب آجل يتجاوز السقف الائتماني المعتمد لنقطة البيع (422 Credit Limit Exceeded).
 * 10. إصدار فاتورة مبيعات مباشرة (كاش وآجل) خارج مسار الطلبات مع التدقيق المالي.
 * 11. عزل المستأجرين (Tenant Isolation): حظر تدخل مستخدم شبكة غريبة في طلبات الشبكة المستهدفة (403/422).
 * 12. الحماية الصارمة من سباق الاعتماد المزدوج (Double-Approval Race Condition).
 * 13. التحقق من إرجاع مسميات الـ Enums التفسيرية بالعربية (*_label) ومطابقة وثيقة العقد.
 */
class EndToEndProcurementAndInvoicingE2ETest extends TestCase
{
    use DatabaseTransactions;

    protected User $networkOwnerUser;

    protected User $posAgentUser;

    protected User $rogueNetworkUser;

    protected Network $network;

    protected Pos $pos;

    protected NetworkPos $networkPos;

    protected NetworkWallet $networkWallet;

    protected PosWallet $posWallet;

    protected VoucherPackage $packageA;

    protected VoucherPackage $packageB;

    protected VoucherPackage $roguePackage;

    protected array $networkHeaders;

    protected array $posHeaders;

    protected array $rogueHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $networkOwnerRole = Role::firstOrCreate(
            ['role_key' => RoleKey::NETWORK_OWNER->value],
            ['role_name' => 'مزود شبكة', 'role_scope' => RoleScope::NETWORK->value]
        );

        $posAgentRole = Role::firstOrCreate(
            ['role_key' => RoleKey::POS_AGENT->value],
            ['role_name' => 'وكيل نقطة بيع', 'role_scope' => RoleScope::POS->value]
        );

        // 1. إنشاء المستخدمين الثلاثة بنطاقات واضحة
        $this->networkOwnerUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Network Owner VIP',
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_email' => 'net_owner_'.uniqid().'@netsales.local',
            'user_password' => bcrypt('Secret@123'),
            'status' => 'ACTIVE',
        ]);

        $this->posAgentUser = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'POS Agent Master',
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_email' => 'pos_agent_'.uniqid().'@netsales.local',
            'user_password' => bcrypt('Secret@123'),
            'status' => 'ACTIVE',
        ]);

        $this->rogueNetworkUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Rogue Network Owner',
            'user_phone_number' => '71'.rand(1000000, 9999999),
            'user_email' => 'rogue_owner_'.uniqid().'@netsales.local',
            'user_password' => bcrypt('Secret@123'),
            'status' => 'ACTIVE',
        ]);

        // 2. إنشاء الكيانات التجارية
        $this->network = Network::create([
            'owner_id' => $this->networkOwnerUser->user_id,
            'network_uuid' => 'NET-'.uniqid(),
            'network_name' => 'الأفق فايبر التجريبية',
            'network_phone_number' => $this->networkOwnerUser->user_phone_number,
            'network_email' => $this->networkOwnerUser->user_email,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        $rogueNetwork = Network::create([
            'owner_id' => $this->rogueNetworkUser->user_id,
            'network_uuid' => 'NET-ROGUE-'.uniqid(),
            'network_name' => 'شبكة المنافس المجهولة',
            'network_phone_number' => $this->rogueNetworkUser->user_phone_number,
            'network_email' => $this->rogueNetworkUser->user_email,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->posAgentUser->user_id,
            'pos_uuid' => 'POS-'.uniqid(),
            'pos_name' => 'مركز النصر للخدمات',
            'pos_phone_number' => $this->posAgentUser->user_phone_number,
            'pos_email' => $this->posAgentUser->user_email,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        // 3. ربط العضوية التشغيلية (Tenant Memberships)
        NetworkUser::create([
            'network_id' => $this->network->network_id,
            'user_id' => $this->networkOwnerUser->user_id,
            'role_id' => $networkOwnerRole->role_id,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::create([
            'network_id' => $rogueNetwork->network_id,
            'user_id' => $this->rogueNetworkUser->user_id,
            'role_id' => $networkOwnerRole->role_id,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $this->pos->pos_id,
            'user_id' => $this->posAgentUser->user_id,
            'role_id' => $posAgentRole->role_id,
            'status' => 'ACTIVE',
        ]);

        // 4. العلاقة التجارية بين الشبكة ونقطة البيع (NetworkPos)
        $this->networkPos = NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        // 5. المحافظ المالية لكلا الطرفين (Wallets)
        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_uuid' => 'NW-'.uniqid(),
            'wallet_balance' => 1000000.00,
            'opening_balance' => 1000000.00,
            'currency_code' => 'YER',
            'status' => true,
        ]);

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_uuid' => 'PW-'.uniqid(),
            'wallet_balance' => 50000.00,
            'opening_balance' => 50000.00,
            'credit_limit' => 100000.00, // سقف ائتماني 100,000 ريال
            'currency_code' => 'YER',
            'status' => true,
        ]);

        // 6. باقات الكروت (VoucherPackages)
        // الباقة A: السعر الافتراضي 1000، خصم افتراضي كاش 10%، آجل 5%
        $this->packageA = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->networkOwnerUser->user_id,
            'package_name' => 'باقة السرعة الفائقة 1000',
            'package_color' => '#10B981',
            'package_balance' => 10,
            'package_uptime' => 24,
            'package_validity' => 30,
            'package_voucher_count' => 100,
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.00,
            'package_default_credit_discount' => 5.00,
            'status' => true,
        ]);

        // الباقة B: السعر 500، خصم كاش 8%، آجل 3%
        $this->packageB = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->networkOwnerUser->user_id,
            'package_name' => 'باقة التوفير 500',
            'package_color' => '#3B82F6',
            'package_balance' => 5,
            'package_uptime' => 12,
            'package_validity' => 15,
            'package_voucher_count' => 50,
            'package_voucher_price' => 500.00,
            'package_default_cash_discount' => 8.00,
            'package_default_credit_discount' => 3.00,
            'status' => true,
        ]);

        // باقة تخص الشبكة الغريبة (Rogue Package)
        $this->roguePackage = VoucherPackage::create([
            'network_id' => $rogueNetwork->network_id,
            'created_by' => $this->rogueNetworkUser->user_id,
            'package_name' => 'باقة الشبكة الغريبة 2000',
            'package_color' => '#EF4444',
            'package_balance' => 20,
            'package_uptime' => 48,
            'package_validity' => 60,
            'package_voucher_count' => 200,
            'package_voucher_price' => 2000.00,
            'package_default_cash_discount' => 5.00,
            'package_default_credit_discount' => 2.00,
            'status' => true,
        ]);

        // 7. تخصيص خصم تعاقدي استثنائي للوكيل على الباقة A (15% كاش، 8% آجل)
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'package_id' => $this->packageA->package_id,
            'pos_id' => $this->pos->pos_id,
            'created_by' => $this->networkOwnerUser->user_id,
            'discount_cash' => 15.00,
            'discount_credit' => 8.00,
            'status' => true,
            'discount_start_date' => Carbon::now()->subDays(1),
            'discount_end_date' => Carbon::now()->addDays(30),
        ]);

        // 8. تجهيز جلسات التوثيق والترويسات الرسمية
        $this->networkHeaders = $this->createSessionHeaders($this->networkOwnerUser);
        $this->posHeaders = $this->createSessionHeaders($this->posAgentUser);
        $this->rogueHeaders = $this->createSessionHeaders($this->rogueNetworkUser);
    }

    protected function createSessionHeaders(User $user): array
    {
        $deviceUuid = 'DEV-'.uniqid();
        UserDevice::create([
            'user_id' => $user->user_id,
            'device_uuid' => $deviceUuid,
            'device_name' => 'Test Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $session = UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $deviceUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'IntegrationTestSuite',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        return [
            'X-Session-Id' => (string) $session->session_id,
            'X-Device-Uuid' => $deviceUuid,
            'X-Bypass-Encryption' => 'true',
            'Accept' => 'application/json',
        ];
    }

    /**
     * توليد توكن تعرفة سيادي للباقة عبر خدمة اللقطات السعرية المعتمدة.
     */
    protected function generatePricingSnapshotToken(VoucherPackage $package, ?Pos $pos = null): string
    {
        $posId = $pos ? (int) $pos->pos_id : (int) $this->pos->pos_id;
        $networkId = (int) $package->network_id;

        $discount = app(VoucherPackageDiscountService::class)->getEffectiveDiscount(
            $networkId,
            $posId,
            (int) $package->package_id
        );

        return app(ProcurementPricingSnapshotService::class)->issue(
            $networkId,
            $posId,
            (int) $package->package_id,
            DecimalAmount::toMinorUnits($package->package_voucher_price),
            DecimalAmount::toMinorUnits($discount['discount_cash'] ?? 0),
            DecimalAmount::toMinorUnits($discount['discount_credit'] ?? 0)
        );
    }

    /**
     * سيناريو 1: طلب عرض أسعار رسمي من الوكيل والتحقق من حساب الخصومات التعاقدية.
     */
    public function test_01_pos_requests_official_quote_with_contractual_discounts(): void
    {
        $payload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 10, // 10 كروت * 1000 = 10,000 إجمالي
                ],
                [
                    'package_id' => $this->packageB->package_id,
                    'voucher_count' => 20, // 20 كرت * 500 = 10,000 إجمالي
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/requests/quote', $payload, $this->posHeaders);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // الباقة A خاضعة لخصم تعاقدي 15% -> صافي 8500
        // الباقة B خاضعة للخصم الافتراضي 8% -> صافي 9200
        // الإجمالي: 10,000 + 10,000 = 20,000
        // صافي الإجمالي: 8500 + 9200 = 17,700
        // مجموع الخصم: 2300
        $data = $response->json('data');
        $this->assertEquals(20000.00, (float) $data['gross_total']);
        $this->assertEquals(2300.00, (float) $data['discount_total']);
        $this->assertEquals(17700.00, (float) $data['net_total']);
        $this->assertNotEmpty($data['pricing_token']);
        $this->assertEquals('نقدي', $data['sale_type_label']);
    }

    /**
     * سيناريو 2: إنشاء طلب توريد نقدي باستخدام لقطات التعرفة السعرية المعتمدة.
     */
    public function test_02_pos_creates_cash_procurement_request_with_valid_snapshot(): void
    {
        $tokenA = $this->generatePricingSnapshotToken($this->packageA);
        $tokenB = $this->generatePricingSnapshotToken($this->packageB);

        $payload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'request_note' => 'طلب توريد أسبوعي عاجل نقداً',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 10,
                    'pricing_snapshot_token' => $tokenA,
                ],
                [
                    'package_id' => $this->packageB->package_id,
                    'voucher_count' => 10,
                    'pricing_snapshot_token' => $tokenB,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/requests', $payload, $this->posHeaders);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));

        // إجمالي الباقة A = 10 * 850 = 8500
        // إجمالي الباقة B = 10 * 460 = 4600
        // إجمالي الطلب = 13100.00
        $requestId = $response->json('data.request_id');
        $this->assertDatabaseHas('requests', [
            'request_id' => $requestId,
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'status' => 'PENDING',
            'request_total_amount' => 13100.00,
            'created_by' => $this->posAgentUser->user_id,
        ]);

        $this->assertDatabaseCount('request_items', 2);
    }

    /**
     * سيناريو 3: حظر طلب باقة تابعة لشبكة أخرى (Cross-Network Package Injection Prevention).
     */
    public function test_03_pos_cannot_order_package_from_unrelated_network(): void
    {
        $rogueToken = $this->generatePricingSnapshotToken($this->roguePackage);

        $payload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->roguePackage->package_id, // باقة تنتمي لـ rogueNetwork
                    'voucher_count' => 5,
                    'pricing_snapshot_token' => $rogueToken,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/requests', $payload, $this->posHeaders);

        // يجب رفض الطلب فوراً لأن الباقة لا تنتمي للشبكة المحددة
        $response->assertStatus(422);
    }

    /**
     * سيناريو 4: إلغاء الطلب من قِبل نقطة البيع وهو لا يزال في حالة الانتظار (PENDING).
     */
    public function test_04_pos_can_cancel_pending_request(): void
    {
        $token = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 2,
                    'pricing_snapshot_token' => $token,
                ],
            ],
        ], $this->posHeaders);

        $requestId = $createResponse->json('data.request_id');

        $cancelResponse = $this->postJson("/api/v1/requests/{$requestId}/cancel", [], $this->posHeaders);

        $cancelResponse->assertStatus(200);
        $this->assertDatabaseHas('requests', [
            'request_id' => $requestId,
            'status' => 'CANCELLED',
        ]);
    }

    /**
     * سيناريو 5: رفض الطلب من قِبل الشبكة مع التحقق من إلزامية بيان سبب الرفض المبرر.
     */
    public function test_05_network_rejects_pending_request_with_mandatory_reason(): void
    {
        $token = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 5,
                    'pricing_snapshot_token' => $token,
                ],
            ],
        ], $this->posHeaders);

        $requestId = $createResponse->json('data.request_id');

        // محاولة الرفض بدون سبب يجب أن تفشل (422)
        $invalidRejectResponse = $this->postJson("/api/v1/requests/{$requestId}/reject", [
            'rejection_reason' => '',
        ], $this->networkHeaders);
        $invalidRejectResponse->assertStatus(422);

        // الرفض مع سبب معتمد
        $rejectResponse = $this->postJson("/api/v1/requests/{$requestId}/reject", [
            'rejection_reason' => 'عدم توفر مخزون كافي لهذه الفئة حالياً',
        ], $this->networkHeaders);

        $rejectResponse->assertStatus(200);
        $this->assertDatabaseHas('requests', [
            'request_id' => $requestId,
            'status' => 'REJECTED',
            'reviewed_by' => $this->networkOwnerUser->user_id,
        ]);
    }

    /**
     * سيناريو 6: منع الانتقال غير القانوني بين الحالات (تعديل أو إلغاء طلب بعد البت فيه).
     */
    public function test_06_illegal_state_transitions_are_blocked(): void
    {
        $token = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 3,
                    'pricing_snapshot_token' => $token,
                ],
            ],
        ], $this->posHeaders);

        $requestId = $createResponse->json('data.request_id');

        // إلغاء الطلب أولاً
        $this->postJson("/api/v1/requests/{$requestId}/cancel", [], $this->posHeaders)->assertStatus(200);

        // محاولة اعتماد طلب ملغي يجب أن تفشل
        $approveResponse = $this->postJson("/api/v1/requests/{$requestId}/approve", [], $this->networkHeaders);
        $approveResponse->assertStatus(422);

        // محاولة إلغاء طلب تم إلغاؤه مسبقاً يجب أن تفشل
        $secondCancelResponse = $this->postJson("/api/v1/requests/{$requestId}/cancel", [], $this->posHeaders);
        $secondCancelResponse->assertStatus(422);
    }

    /**
     * سيناريو 7: معاينة التعديلات واعتماد الطلب نقداً (CASH) وتوليد الفاتورة والخصم اللحظي ذرياً.
     */
    public function test_07_network_previews_modifications_and_approves_cash_request(): void
    {
        // 1. الوكيل يطلب 10 كروت من الباقة A
        $tokenA = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 10,
                    'pricing_snapshot_token' => $tokenA,
                ],
            ],
        ], $this->posHeaders);

        $requestId = $createResponse->json('data.request_id');

        // 2. الشبكة تراجع وتعاود تخفيض الكمية إلى 5 كروت نظراً لضغط المبيعات
        $previewResponse = $this->postJson("/api/v1/requests/{$requestId}/preview-modification", [
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 5,
                ],
            ],
        ], $this->networkHeaders);

        $previewResponse->assertStatus(200);
        $previewData = $previewResponse->json('data');
        // 5 كروت * 1000 - 15% خصم تعاقدي = 4250 صافي
        $this->assertEquals(4250.00, (float) $previewData['preview_total_amount']);

        // تسجيل الأرصدة قبل الاعتماد
        $posBalanceBefore = (float) $this->posWallet->fresh()->wallet_balance;
        $netBalanceBefore = (float) $this->networkWallet->fresh()->wallet_balance;

        // 3. الشبكة تعتمد الطلب بالكمية المعدلة (5 كروت)
        $approveResponse = $this->postJson("/api/v1/requests/{$requestId}/approve", [
            'request_note' => 'تم اعتماد 5 كروت بدلاً من 10 لتغطية كافة الوكلاء',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 5,
                ],
            ],
        ], $this->networkHeaders);

        $approveResponse->assertStatus(200);
        $this->assertTrue($approveResponse->json('success'));

        // التحقق من تحديث حالة الطلب إلى APPROVED
        $this->assertDatabaseHas('requests', [
            'request_id' => $requestId,
            'status' => 'APPROVED',
            'reviewed_by' => $this->networkOwnerUser->user_id,
        ]);

        // التحقق من توليد فاتورة مبيعات مرتبطة بالطلب
        $invoice = SaleInvoice::where('request_id', $requestId)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(SaleType::CASH, $invoice->sale_type);
        $this->assertEquals(InvoicePaymentStatus::PAID, $invoice->payment_status);
        $this->assertEquals(4250.00, (float) $invoice->invoice_total_amount);
        $this->assertEquals(4250.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->remaining_amount);

        // التحقق من تسوية الأرصدة الذرية: في البيع النقدي، الرصيد لا يتغير لأن الدفع كاش يداً بيد
        // ولكن محفظة الشبكة تزداد بإيداع إيراد المبيعات المحصل
        $this->assertEquals($posBalanceBefore, (float) $this->posWallet->fresh()->wallet_balance);
        $this->assertEquals($netBalanceBefore + 4250.00, (float) $this->networkWallet->fresh()->wallet_balance);

        // التحقق من قيود اليومية المالية في سجل المحفظة
        $this->assertDatabaseHas('pos_wallet_logs', [
            'wallet_id' => $this->posWallet->wallet_id,
            'log_amount' => 4250.00,
            'log_operation_type' => 'WITHDRAW',
        ]);

        $this->assertDatabaseHas('pos_wallet_logs', [
            'wallet_id' => $this->posWallet->wallet_id,
            'log_amount' => 4250.00,
            'log_operation_type' => 'DEPOSIT',
        ]);

        $this->assertDatabaseHas('network_wallet_logs', [
            'wallet_id' => $this->networkWallet->wallet_id,
            'log_amount' => 4250.00,
            'log_operation' => 'DEPOSIT',
        ]);
    }

    /**
     * سيناريو 8: اعتماد طلب آجل (CREDIT) مع دفعة نقدية جزئية وتحديث مديونية الوكيل.
     */
    public function test_08_pos_credit_request_approved_with_partial_cash_payment(): void
    {
        // باقة A بسعر 1000، خصم آجل تعاقدي 8% -> سعر الكرت الآجل الصافي 920 ريال
        // طلب 20 كرت آجل -> إجمالي الصافي = 18,400 ريال
        $tokenA = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CREDIT',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 20,
                    'pricing_snapshot_token' => $tokenA,
                ],
            ],
        ], $this->posHeaders);

        $requestId = $createResponse->json('data.request_id');

        // تصفير رصيد محفظة الوكيل للتأكد من عدم تغطية الرصيد التلقائية للمبلغ المتبقي
        $this->posWallet->update(['wallet_balance' => 0.00]);

        // الشبكة تعتمد الطلب مع تسليم الوكيل دفعة نقدية مقدمة قدرها 5000 ريال
        $approveResponse = $this->postJson("/api/v1/requests/{$requestId}/approve", [
            'paid_amount' => 5000.00,
        ], $this->networkHeaders);

        $approveResponse->assertStatus(200);

        $invoice = SaleInvoice::where('request_id', $requestId)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(SaleType::CREDIT, $invoice->sale_type);
        $this->assertEquals(InvoicePaymentStatus::PARTIALLY_PAID, $invoice->payment_status);
        $this->assertEquals(18400.00, (float) $invoice->invoice_total_amount);
        $this->assertEquals(5000.00, (float) $invoice->paid_amount);
        $this->assertEquals(13400.00, (float) $invoice->remaining_amount);

        // محفظة الوكيل تصبح مدينة بالمبلغ المتبقي (-13,400)
        $this->assertEquals(-13400.00, (float) $this->posWallet->fresh()->wallet_balance);
    }

    /**
     * سيناريو 9: رفض اعتماد طلب آجل يتجاوز السقف الائتماني المعتمد لنقطة البيع (422).
     */
    public function test_09_pos_credit_request_exceeding_credit_limit_is_rejected(): void
    {
        // تعيين سقف ائتماني صغير 5,000 ريال فقط
        $this->posWallet->update([
            'wallet_balance' => 0.00,
            'credit_limit' => 5000.00,
        ]);

        // طلب 20 كرت * 920 = 18,400 ريال (يتجاوز سقف 5000)
        $tokenA = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CREDIT',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 20,
                    'pricing_snapshot_token' => $tokenA,
                ],
            ],
        ], $this->posHeaders);

        $requestId = $createResponse->json('data.request_id');

        // محاولة الاعتماد بدون دفعة نقدية كافية
        $approveResponse = $this->postJson("/api/v1/requests/{$requestId}/approve", [
            'paid_amount' => 1000.00, // متبقي 17,400 وهو أكبر بكثير من 5000
        ], $this->networkHeaders);

        $approveResponse->assertStatus(422);
    }

    /**
     * سيناريو 10: إصدار فواتير مبيعات مباشرة (CASH و CREDIT) خارج مسار الطلبات.
     */
    public function test_10_direct_sale_invoice_issuance_cash_and_credit(): void
    {
        // 1. فاتورة كاش مباشرة
        $cashPayload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 2,
                ],
            ],
        ];

        $cashResponse = $this->postJson('/api/v1/sale-invoices', $cashPayload, $this->networkHeaders);
        $cashResponse->assertStatus(201);
        $this->assertTrue($cashResponse->json('success'));
        $cashInvoiceId = $cashResponse->json('data.invoice_id');

        $this->assertDatabaseHas('sale_invoices', [
            'invoice_id' => $cashInvoiceId,
            'sale_type' => 'CASH',
            'payment_status' => 'PAID',
        ]);

        // 2. فاتورة آجل مباشرة مع دفعة نقدية جزئية
        $creditPayload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CREDIT',
            'paid_amount' => 500.00,
            'items' => [
                [
                    'package_id' => $this->packageB->package_id,
                    'voucher_count' => 4,
                ],
            ],
        ];

        $creditResponse = $this->postJson('/api/v1/sale-invoices', $creditPayload, $this->networkHeaders);
        $creditResponse->assertStatus(201);
        $this->assertTrue($creditResponse->json('success'));
    }

    /**
     * سيناريو 11: العزل الأمني التام بين المستأجرين (Tenant Isolation).
     */
    public function test_11_cross_tenant_isolation_strictly_enforced(): void
    {
        // إنشاء طلب مشروع للشبكة الأولى
        $token = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 3,
                    'pricing_snapshot_token' => $token,
                ],
            ],
        ], $this->posHeaders);

        $requestId = $createResponse->json('data.request_id');

        // محاولة مراجع شبكة منافسة استعراض تفاصيل الطلب
        $rogueShowResponse = $this->getJson("/api/v1/requests/{$requestId}", $this->rogueHeaders);
        $this->assertTrue(in_array($rogueShowResponse->status(), [403, 404, 422]));

        // محاولة مراجع شبكة منافسة اعتماد الطلب
        $rogueApproveResponse = $this->postJson("/api/v1/requests/{$requestId}/approve", [], $this->rogueHeaders);
        $this->assertTrue(in_array($rogueApproveResponse->status(), [403, 422]));

        // محاولة مراجع شبكة منافسة رفض الطلب
        $rogueRejectResponse = $this->postJson("/api/v1/requests/{$requestId}/reject", [
            'rejection_reason' => 'محاولة اختراق غير مصرح بها',
        ], $this->rogueHeaders);
        $this->assertTrue(in_array($rogueRejectResponse->status(), [403, 422]));
    }

    /**
     * سيناريو 12: الحماية الصارمة من سباق الاعتماد المزدوج (Double-Approval Race Protection).
     */
    public function test_12_double_approval_race_protection(): void
    {
        $token = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 2,
                    'pricing_snapshot_token' => $token,
                ],
            ],
        ], $this->posHeaders);

        $requestId = $createResponse->json('data.request_id');

        // الاعتماد الأول ينجح
        $firstApprove = $this->postJson("/api/v1/requests/{$requestId}/approve", [], $this->networkHeaders);
        $firstApprove->assertStatus(200);

        // محاولة الاعتماد مرة ثانية يجب أن تفشل قطعياً لأن حالة الطلب تغيرت
        $secondApprove = $this->postJson("/api/v1/requests/{$requestId}/approve", [], $this->networkHeaders);
        $secondApprove->assertStatus(422);
    }

    /**
     * سيناريو 13: التحقق من التزام الردود بإرجاع مسميات الـ Enums بالعربية وفق وثيقة العقد.
     */
    public function test_13_arabic_enum_companion_labels_returned(): void
    {
        $token = $this->generatePricingSnapshotToken($this->packageA);
        $createResponse = $this->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 1,
                    'pricing_snapshot_token' => $token,
                ],
            ],
        ], $this->posHeaders);

        $createResponse->assertStatus(201);
        $requestId = $createResponse->json('data.request_id');

        $showResponse = $this->getJson("/api/v1/requests/{$requestId}", $this->posHeaders);
        $showResponse->assertStatus(200);

        $data = $showResponse->json('data');
        $this->assertEquals('PENDING', $data['status']);
        $this->assertEquals('قيد الانتظار', $data['status_label']);
        $this->assertEquals('CASH', $data['sale_type']);
        $this->assertEquals('نقدي', $data['sale_type_label']);
    }
}
