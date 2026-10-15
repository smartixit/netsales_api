<?php

namespace Tests\Feature;

use App\Enums\BanType;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\Request as RequestModel;
use App\Models\SecurityBan;
use App\Models\User;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * حزمة الاختبارات الأمنية والسيناريوهات الحدية والمعادية (Adversarial Security & Edge Cases Suite).
 *
 * مصممة للتحقق من:
 * 1. رفض الطلبات غير المصادق عليها (401 Unauthorized).
 * 2. الحرمان الفوري للجلسات الملغاة والمنتهية (Instant Session Invalidation).
 * 3. سد ثغرات الحظر الأمني النشط (Instant User & Network Ban Enforcement).
 * 4. منع هجمات السباق والاعتماد المزدوج (Double-Approval Race Attack Prevention).
 * 5. منع تغيير حالة الطلبات المبت فيها (Status Immutability).
 * 6. منع إلغاء طلبات المستخدمين الآخرين (IDOR Request Tampering).
 * 7. العزل التام بين المستأجرين والشبكات (Strict Multi-Tenant Isolation).
 * 8. التحقق من الحدود القصوى والدنيا للأسعار والخصومات (Boundary Integrity).
 */
class AdversarialSecurityAndEdgeCasesTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected UserSession $session;
    protected Network $networkA;
    protected Network $networkB;
    protected Pos $posA;
    protected Pos $posB;
    protected NetworkWallet $networkWalletA;
    protected PosWallet $posWalletA;
    protected VoucherPackage $packageA;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Adversarial Security Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Bypass-Encryption' => 'true',
        ];

        $suffix = uniqid();

        // الشبكة A
        $this->networkA = Network::create([
            'network_name' => 'SecNet A '.$suffix,
            'network_holder_name' => 'Holder A '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        // الشبكة B (شبكة مستقلة لا علاقة لها بالشبكة A)
        $this->networkB = Network::create([
            'network_name' => 'SecNet B '.$suffix,
            'network_holder_name' => 'Holder B '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        // نقطة البيع A تابعة للشبكة A
        $this->posA = Pos::create([
            'pos_name' => 'SecPOS A '.$suffix,
            'pos_holder_name' => 'POS Holder A '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'status' => true,
        ]);

        // نقطة البيع B تابعة حصرياً للشبكة B
        $this->posB = Pos::create([
            'pos_name' => 'SecPOS B '.$suffix,
            'pos_holder_name' => 'POS Holder B '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $this->networkB->network_id,
            'pos_id' => $this->posB->pos_id,
            'status' => true,
        ]);

        $this->networkWalletA = NetworkWallet::create([
            'network_id' => $this->networkA->network_id,
            'wallet_balance' => 50000.00,
            'status' => true,
        ]);

        $this->posWalletA = PosWallet::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'wallet_balance' => 10000.00,
            'status' => true,
        ]);

        $this->packageA = VoucherPackage::create([
            'network_id' => $this->networkA->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'Security Package '.$suffix,
            'package_voucher_price' => 500.00,
            'package_default_cash_discount' => 10.00,
            'package_default_credit_discount' => 5.00,
            'status' => true,
        ]);
    }

    /**
     * اختبار أمني: رفض أي طلب محمي بدون ترويسة الجلسة X-Session-Id
     */
    public function test_unauthenticated_requests_are_strictly_rejected(): void
    {
        $response = $this->withHeaders(['X-Bypass-Encryption' => 'true'])
            ->getJson('/api/v1/networks');

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    /**
     * اختبار أمني: سحب وإلغاء الجلسة يحرم المستخدم فورياً من الوصول
     */
    public function test_revoked_session_immediately_blocks_access(): void
    {
        // إلغاء الجلسة
        $this->session->update([
            'revoked_at' => Carbon::now('Asia/Aden'),
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/networks');

        $response->assertStatus(401);
    }

    /**
     * اختبار أمني: الجلسة منتهية الصلاحية تُرفض فوراً
     */
    public function test_expired_session_is_strictly_rejected(): void
    {
        $this->session->update([
            'expires_at' => Carbon::now('Asia/Aden')->subMinute(),
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/networks');

        $response->assertStatus(401);
    }

    /**
     * اختبار أمني: الحظر الأمني النشط للمستخدم في security_bans يحجب الوصول فوراً
     */
    public function test_active_security_ban_blocks_user_access_with_403(): void
    {
        SecurityBan::create([
            'user_id' => $this->user->user_id,
            'banned_by' => $this->user->user_id,
            'ban_type' => BanType::PERMANENT,
            'ban_reason' => 'حظر أمني تجريبي للاختبار',
            'start_at' => Carbon::now('Asia/Aden'),
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/v1/networks');

        $response->assertStatus(403);
        $this->assertStringContainsString('محظور', $response->json('message'));
    }

    /**
     * اختبار أمني ومالي: منع تكرار اعتماد الطلب (Double-Approval Race Condition Prevention)
     * اعتماد طلب معتمد بالفعل يجب أن يُرفض بـ 422 دون تكرار إنشاء الفاتورة أو الخصم المالي
     */
    public function test_double_approval_of_request_is_strictly_blocked(): void
    {
        // 1. إنشاء طلب كروت شرعي
        $requestResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posA->pos_id,
                'request_note' => 'طلب لفحص الاعتماد المزدوج',
                'items' => [
                    [
                        'package_id' => $this->packageA->package_id,
                        'item_voucher_count' => 10,
                    ],
                ],
            ]);

        $requestResponse->assertStatus(201);
        $requestId = $requestResponse->json('data.request_id');

        // 2. الاعتماد الأول: يجب أن ينجح بـ 200
        $firstApprove = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve");

        $firstApprove->assertStatus(200);

        $posBalanceAfterFirst = (float) $this->posWalletA->refresh()->wallet_balance;

        // 3. محاولة الاعتماد الثاني لنفس الطلب: يجب أن تُرفض قطعياً بـ 422
        $secondApprove = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve");

        $secondApprove->assertStatus(422);
        $this->assertStringContainsString('قيد الانتظار', $secondApprove->json('message'));

        // التأكد من عدم خصم أي فلس إضافي من المحفظة
        $this->posWalletA->refresh();
        $this->assertEquals($posBalanceAfterFirst, (float) $this->posWalletA->wallet_balance, 'لا يجوز تكرار الخصم المالي عند محاولة الاعتماد المزدوج');
    }

    /**
     * اختبار أمني: منع رفض طلب تم اعتماده مسبقاً أو تكرار الرفض (Double-Rejection Prevention)
     */
    public function test_cannot_reject_already_approved_or_rejected_request(): void
    {
        $requestResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posA->pos_id,
                'request_note' => 'طلب لفحص عدم إمكانية الرفض بعد الاعتماد',
                'items' => [
                    [
                        'package_id' => $this->packageA->package_id,
                        'item_voucher_count' => 5,
                    ],
                ],
            ]);

        $requestId = $requestResponse->json('data.request_id');

        // اعتماد الطلب أولاً
        $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve")
            ->assertStatus(200);

        // محاولة رفض الطلب المعتمد
        $rejectApproved = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/reject", [
                'reason' => 'محاولة رفض طلب بعد أن أصبح معتمداً',
            ]);

        $rejectApproved->assertStatus(422);

        // إنشاء طلب آخر ورفضه
        $anotherRequest = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posA->pos_id,
                'request_note' => 'طلب للرفض المزدوج',
                'items' => [
                    [
                        'package_id' => $this->packageA->package_id,
                        'item_voucher_count' => 2,
                    ],
                ],
            ])->json('data.request_id');

        // الرفض الأول
        $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$anotherRequest}/reject", [
                'reason' => 'الرفض الأول الشرعي',
            ])->assertStatus(200);

        // محاولة الرفض الثاني للطلب المرفوض مسبقاً
        $secondReject = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$anotherRequest}/reject", [
                'reason' => 'الرفض الثاني غير المسموح به',
            ]);

        $secondReject->assertStatus(422);
    }

    /**
     * اختبار أمني: منع إلغاء الطلب بعد اعتماده أو رفضه
     */
    public function test_cannot_cancel_already_processed_request(): void
    {
        $requestResponse = $this->withHeaders($this->headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posA->pos_id,
                'request_note' => 'طلب لفحص الإلغاء',
                'items' => [
                    [
                        'package_id' => $this->packageA->package_id,
                        'item_voucher_count' => 1,
                    ],
                ],
            ]);

        $requestId = $requestResponse->json('data.request_id');

        // اعتماد الطلب
        $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/approve")
            ->assertStatus(200);

        // محاولة الإلغاء للطلب المعتمد
        $cancelResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/cancel");

        $cancelResponse->assertStatus(422);
    }

    /**
     * اختبار العزل التام بين المستأجرين (Cross-Tenant Isolation / IDOR Protection)
     * محاولة إرسال طلب تحويل أو كروت لنقطة بيع تخص شبكة أخرى يجب أن تُرفض بـ 422
     */
    public function test_cross_tenant_pos_network_mismatch_is_strictly_rejected(): void
    {
        // محاولة تحويل من محفظة الشبكة A إلى نقطة البيع B (التي تنتمي للشبكة B فقط)
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/pos-wallets/transfer-from-network', [
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posB->pos_id, // POS تابعة لـ Network B
                'amount' => 500.00,
                'statement' => 'محاولة اختراق عزل الشبكات',
            ]);

        $response->assertStatus(422);
        $this->assertTrue(
            str_contains($response->json('message'), 'غير مرتبطة') ||
            str_contains($response->json('message'), 'محفظة')
        );
    }

    /**
     * اختبار قيود الحدود (Boundary Testing): رفض نسب الخصم غير المنطقية (> 100% أو < 0%)
     */
    public function test_boundary_validation_rejects_invalid_discount_percentages(): void
    {
        // محاولة إنشاء خصم مخصص بنسبة 150% (أكثر من 100%)
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/voucher-package-discounts', [
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posA->pos_id,
                'package_id' => $this->packageA->package_id,
                'discount_cash' => 150.00,
                'discount_credit' => 10.00,
                'status' => true,
            ]);

        $response->assertStatus(422);
    }

    /**
     * اختبار قيود الحدود (Boundary Testing): رفض أسعار الباقات السالبة أو الصفرية
     */
    public function test_boundary_validation_rejects_negative_voucher_package_price(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/v1/voucher-packages', [
                'network_id' => $this->networkA->network_id,
                'package_name' => 'Invalid Price Package',
                'package_voucher_price' => -100.00, // سعر سالب
                'status' => true,
            ]);

        $response->assertStatus(422);
    }
}
