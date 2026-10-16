<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\NetworkStatus;
use App\Enums\ProviderSubscriptionStatus;
use App\Enums\RequestPaymentType;
use App\Enums\RequestStatus;
use App\Enums\ValueType;
use App\Models\BankAccount;
use App\Models\Feature;
use App\Models\Network;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubscriptionSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected Network $network;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Bypass-Encryption' => 'true',
        ];

        $suffix = uniqid();

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'Net '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);
    }

    /**
     * اختبار إنشاء ميزة وباقة اشتراك وربط الحصص.
     */
    public function test_can_create_subscription_plan_with_features_and_quotas(): void
    {
        // 1. إنشاء ميزة في الكتالوج
        $featureResponse = $this->withHeaders($this->headers)->postJson('/api/v1/features', [
            'feature_name' => 'MAX_POS_COUNT_'.uniqid(),
            'feature_value_type' => ValueType::INTEGER->value,
            'feature_value' => '10',
            'feature_desc' => 'الحد الأقصى لنقاط البيع المسموحة',
            'status' => true,
        ]);

        $featureResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $featureId = (int) $featureResponse->json('data.feature_id');

        // 2. إنشاء باقة اشتراك مع ربط الميزة
        $planResponse = $this->withHeaders($this->headers)->postJson('/api/v1/subscription-plans', [
            'plan_name' => 'الباقة الفضية المتقدمة',
            'plan_price' => 50000.00,
            'plan_discount' => 10.00,
            'plan_validity' => 30,
            'plan_desc' => 'باقة متقدمة تشمل 10 نقاط بيع',
            'status' => true,
            'features' => [
                [
                    'feature_id' => $featureId,
                    'plan_feature_value' => '10',
                    'status' => true,
                ],
            ],
        ]);

        $planResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan_price', 50000)
            ->assertJsonPath('data.plan_validity', 30);

        $planId = (int) $planResponse->json('data.plan_id');

        // 3. التحقق في قاعدة البيانات
        $this->assertDatabaseHas('subscription_plans', [
            'plan_id' => $planId,
            'plan_price' => 50000.00,
        ]);

        $this->assertDatabaseHas('subscription_plan_features', [
            'plan_id' => $planId,
            'feature_id' => $featureId,
            'plan_feature_value' => '10',
        ]);
    }

    /**
     * اختبار إدارة الحسابات البنكية والمحافظ.
     */
    public function test_can_manage_bank_accounts(): void
    {
        $accountNum = 'ACC-'.rand(100000, 999999);

        // إنشاء حساب بنكي
        $response = $this->withHeaders($this->headers)->postJson('/api/v1/bank-accounts', [
            'bank_name' => 'بنك الكريمي للتمويل الأصغر',
            'account_holder_name' => 'شركة نت سيلز التقنية',
            'account_number' => $accountNum,
            'account_type' => BankAccountType::SAVING->value,
            'account_currency' => 'YER',
            'account_balance' => 100000.00,
            'status' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bank_name', 'بنك الكريمي للتمويل الأصغر');

        $accountId = (int) $response->json('data.account_id');

        // تبديل الحالة
        $toggleRes = $this->withHeaders($this->headers)->postJson("/api/v1/bank-accounts/{$accountId}/toggle-status");
        $toggleRes->assertStatus(200)->assertJsonPath('data.status', false);

        // جلب الحسابات النشطة
        $activeRes = $this->withHeaders($this->headers)->getJson('/api/v1/bank-accounts/active');
        $activeRes->assertStatus(200)->assertJsonPath('success', true);
    }

    /**
     * اختبار تقديم طلب اشتراك واعتماده وتوليد الاشتراك النشط ومحرك استهلاك الميزات.
     */
    public function test_can_submit_and_approve_subscription_request_with_quotas(): void
    {
        // 1. تجهيز ميزة، باقة، وحساب بنكي
        $feature = Feature::create([
            'feature_name' => 'MAX_INVOICE_MONTHLY_'.uniqid(),
            'feature_value_type' => ValueType::INTEGER,
            'feature_value' => '100',
            'status' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'plan_name' => 'باقة المؤسسات الذهبية '.uniqid(),
            'plan_price' => 100000.00,
            'plan_discount' => 10.00, // السعر الصافي 90,000
            'plan_validity' => 60,
            'status' => true,
        ]);

        $plan->features()->attach($feature->feature_id, [
            'plan_feature_value' => '100',
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        $bankAccount = BankAccount::create([
            'bank_name' => 'بنك التضامن الإسلامي',
            'account_holder_name' => 'نت سيلز للحلول الرقمية',
            'account_number' => 'TDM-'.rand(100000, 999999),
            'account_type' => BankAccountType::CURRENT,
            'account_currency' => 'YER',
            'account_balance' => 0.00,
            'status' => true,
        ]);

        // 2. تقديم طلب الاشتراك
        $requestResponse = $this->withHeaders($this->headers)->postJson('/api/v1/subscription-plan-requests', [
            'network_id' => $this->network->network_id,
            'plan_id' => $plan->plan_id,
            'request_payment_type' => RequestPaymentType::BANK->value,
            'account_id' => $bankAccount->account_id,
            'transaction_reference' => 'TX-REF-998811',
            'request_note' => 'طلب اشتراك وتفعيل باقة المؤسسات',
        ]);

        $requestResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request_total_amount', 90000)
            ->assertJsonPath('data.status', RequestStatus::PENDING->value);

        $requestId = (int) $requestResponse->json('data.request_id');

        // 3. اعتماد طلب الاشتراك
        $approveResponse = $this->withHeaders($this->headers)->postJson("/api/v1/subscription-plan-requests/{$requestId}/approve", [
            'note' => 'تم التحقق من الحوالة البنكية واعتماد التفعيل',
        ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request.status', RequestStatus::APPROVED->value)
            ->assertJsonPath('data.subscription.status', ProviderSubscriptionStatus::ACTIVE->value);

        $subscriptionId = (int) $approveResponse->json('data.subscription.subscription_id');

        // 4. التحقق من تحديث رصيد الحساب البنكي (زاد بمقدار 90,000)
        $bankAccount->refresh();
        $this->assertEquals(90000.00, (float) $bankAccount->account_balance);

        // 5. التحقق من وجود الاشتراك النشط في قاعدة البيانات
        $this->assertDatabaseHas('provider_subscriptions', [
            'subscription_id' => $subscriptionId,
            'network_id' => $this->network->network_id,
            'plan_id' => $plan->plan_id,
            'status' => ProviderSubscriptionStatus::ACTIVE->value,
        ]);

        // 6. التحقق من تهيئة سجل استهلاك الميزة
        $this->assertDatabaseHas('subscription_plan_feature_consumptions', [
            'subscription_id' => $subscriptionId,
            'consumption_value' => 0,
        ]);

        // 7. اختبار استهلاك جزء من الحصة
        $consumeResponse = $this->withHeaders($this->headers)->postJson("/api/v1/provider-subscriptions/network/{$this->network->network_id}/consume", [
            'feature_name' => $feature->feature_name,
            'amount' => 5,
        ]);

        $consumeResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.consumption_value', 5);

        // 8. التحقق من تمديد الاشتراك
        $extendResponse = $this->withHeaders($this->headers)->postJson("/api/v1/provider-subscriptions/{$subscriptionId}/extend", [
            'additional_days' => 15,
        ]);

        $extendResponse->assertStatus(200)->assertJsonPath('success', true);
    }

    /**
     * اختبار رفض طلب الاشتراك.
     */
    public function test_can_reject_subscription_request_with_reason(): void
    {
        $plan = SubscriptionPlan::create([
            'plan_name' => 'باقة تجريبية '.uniqid(),
            'plan_price' => 20000.00,
            'plan_validity' => 30,
            'status' => true,
        ]);

        $requestResponse = $this->withHeaders($this->headers)->postJson('/api/v1/subscription-plan-requests', [
            'network_id' => $this->network->network_id,
            'plan_id' => $plan->plan_id,
            'request_payment_type' => RequestPaymentType::CASH->value,
            'request_note' => 'طلب نقدي تجريبي',
        ]);

        $requestId = (int) $requestResponse->json('data.request_id');

        // رفض الطلب
        $rejectResponse = $this->withHeaders($this->headers)->postJson("/api/v1/subscription-plan-requests/{$requestId}/reject", [
            'reason' => 'عدم تسليم المبلغ النقدي للمحصل',
            'note' => 'يرجى مراجعة الإدارة المالية',
        ]);

        $rejectResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatus::REJECTED->value)
            ->assertJsonPath('data.rejection_reason', 'عدم تسليم المبلغ النقدي للمحصل');
    }

    /**
     * اختبار المسار الأول: التفعيل التلقائي المبكر للخطة التالية المجدولة عند نفاد حصة ميزة من الخطة الأولى.
     */
    public function test_early_activation_of_queued_subscription_when_feature_quota_exhausted(): void
    {
        $feature = Feature::create([
            'feature_name' => 'POS_TERMINALS_LIMIT_'.uniqid(),
            'feature_value_type' => ValueType::INTEGER,
            'feature_value' => '5',
            'status' => true,
        ]);

        // 1. الخطة الأولى: سارية لمدة 30 يوماً مع 5 نقاط بيع فقط
        $plan1 = SubscriptionPlan::create([
            'plan_name' => 'الخطة الابتدائية '.uniqid(),
            'plan_price' => 30000.00,
            'plan_validity' => 30,
            'status' => true,
        ]);
        $plan1->features()->attach($feature->feature_id, [
            'plan_feature_value' => '5',
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        // تفعيل الخطة الأولى
        $req1 = $this->withHeaders($this->headers)->postJson('/api/v1/subscription-plan-requests', [
            'network_id' => $this->network->network_id,
            'plan_id' => $plan1->plan_id,
            'request_payment_type' => RequestPaymentType::CASH->value,
        ]);
        $req1Id = (int) $req1->json('data.request_id');
        $this->withHeaders($this->headers)->postJson("/api/v1/subscription-plan-requests/{$req1Id}/approve");

        // التأكد من أن الخطة الأولى هي النشطة الحالية
        $activeSub1 = $this->withHeaders($this->headers)->getJson("/api/v1/provider-subscriptions/network/{$this->network->network_id}/active");
        $activeSub1->assertStatus(200)
            ->assertJsonPath('data.plan_id', $plan1->plan_id)
            ->assertJsonPath('data.status', ProviderSubscriptionStatus::ACTIVE->value);
        $sub1Id = (int) $activeSub1->json('data.subscription_id');

        // 2. الخطة الثانية: تم شراؤها مسبقاً وتنتظر في طابور الجدولة (20 نقطة بيع، 60 يوماً)
        $plan2 = SubscriptionPlan::create([
            'plan_name' => 'الخطة التوسعية المجدولة '.uniqid(),
            'plan_price' => 80000.00,
            'plan_validity' => 60,
            'status' => true,
        ]);
        $plan2->features()->attach($feature->feature_id, [
            'plan_feature_value' => '20',
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        $req2 = $this->withHeaders($this->headers)->postJson('/api/v1/subscription-plan-requests', [
            'network_id' => $this->network->network_id,
            'plan_id' => $plan2->plan_id,
            'request_payment_type' => RequestPaymentType::CASH->value,
        ]);
        $req2Id = (int) $req2->json('data.request_id');
        $approve2Res = $this->withHeaders($this->headers)->postJson("/api/v1/subscription-plan-requests/{$req2Id}/approve");

        // التأكد من أن الخطة الثانية دخلت طابور الانتظار (QUEUED) لأن الأولى ما تزال نشطة
        $approve2Res->assertStatus(200)
            ->assertJsonPath('data.subscription.status', ProviderSubscriptionStatus::QUEUED->value);
        $sub2Id = (int) $approve2Res->json('data.subscription.subscription_id');

        // 3. استهلاك كامل حصة الميزة في الخطة الأولى (5 من أصل 5)
        $this->withHeaders($this->headers)->postJson("/api/v1/provider-subscriptions/network/{$this->network->network_id}/consume", [
            'feature_name' => $feature->feature_name,
            'amount' => 5,
        ]);

        // 4. استهلاك نقطة إضافية (الخطة الأولى نفدت حصتها، والخطة الثانية لم يبدأ أجلها بعد)
        // النظام يجب أن يطبق المسار الأول: تفعيل الخطة الثانية فوراً وإنهاء الأولى
        $overflowConsumeRes = $this->withHeaders($this->headers)->postJson("/api/v1/provider-subscriptions/network/{$this->network->network_id}/consume", [
            'feature_name' => $feature->feature_name,
            'amount' => 1,
        ]);

        $overflowConsumeRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.consumption_value', 1);

        // 5. التحقق من تحول الخطة الأولى إلى منتهية EXPIRED والخطة الثانية أصبحت هي النشطة ACTIVE
        $this->assertDatabaseHas('provider_subscriptions', [
            'subscription_id' => $sub1Id,
            'status' => ProviderSubscriptionStatus::EXPIRED->value,
        ]);

        $this->assertDatabaseHas('provider_subscriptions', [
            'subscription_id' => $sub2Id,
            'status' => ProviderSubscriptionStatus::ACTIVE->value,
        ]);

        // 6. التحقق من أن الاشتراك النشط حالياً للشبكة أصبح الخطة الثانية
        $currentActive = $this->withHeaders($this->headers)->getJson("/api/v1/provider-subscriptions/network/{$this->network->network_id}/active");
        $currentActive->assertStatus(200)
            ->assertJsonPath('data.subscription_id', $sub2Id)
            ->assertJsonPath('data.plan_id', $plan2->plan_id);
    }
}
