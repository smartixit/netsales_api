<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\SaleType;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\Request as RequestModel;
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

    public function test_authorized_pos_can_create_procurement_request_with_server_authoritative_pricing(): void
    {
        $initialPosBalance = (float) $this->posWallet->fresh()->wallet_balance;
        $initialNetworkBalance = (float) $this->networkWallet->fresh()->wallet_balance;

        // العميل يرسل فقط معرف الباقة والكمية (مع محاولة تزوير سعر وخصم محلي لاختبار رفضها من الخادم)
        $payload = [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CASH',
            'request_note' => 'طلب توريد تجريبي للمرحلة 4A',
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 50,
                    // الحقول التالية يجب أن يتجاهلها الخادم كلياً ويستخدم الكتالوج والخصم الفعال
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

    public function test_pos_user_can_cancel_pending_request(): void
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

        // 2. إلغاء الطلب من قبل نقطة البيع
        $cancelResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/cancel");

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertEquals(RequestStatus::CANCELLED, RequestModel::findOrFail($requestId)->status);
        $this->assertEquals($initialPosBalance, (float) $this->posWallet->fresh()->wallet_balance);

        // 3. محاولة إلغاء طلب تم إلغاؤه مسبقاً يجب أن تفشل (حالة نهائية)
        $reCancelResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/cancel");

        $reCancelResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_network_owner_can_reject_pending_request_with_reason(): void
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

        // 3. محاولة رفض طلب مرفوض مسبقاً تفشل
        $reRejectResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$requestId}/reject", [
                'rejection_reason' => 'تكرار الرفض',
            ]);

        $reRejectResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cancelled_or_rejected_request_cannot_be_approved(): void
    {
        // 1. طلب ملغى
        $cancelledReq = RequestModel::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH,
            'request_total_amount' => 10000,
            'status' => RequestStatus::CANCELLED,
            'created_by' => $this->user->user_id,
        ]);

        $approveResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$cancelledReq->request_id}/approve");

        $approveResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // 2. طلب مرفوض
        $rejectedReq = RequestModel::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH,
            'request_total_amount' => 10000,
            'status' => RequestStatus::REJECTED,
            'created_by' => $this->user->user_id,
        ]);

        $approveRejectedResponse = $this->withHeaders($this->headers)
            ->postJson("/api/v1/requests/{$rejectedReq->request_id}/approve");

        $approveRejectedResponse->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
