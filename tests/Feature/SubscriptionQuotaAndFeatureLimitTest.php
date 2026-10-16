<?php

namespace Tests\Feature;

use App\Enums\ProviderSubscriptionStatus;
use App\Enums\RequestPaymentType;
use App\Enums\ValueType;
use App\Models\Feature;
use App\Models\Network;
use App\Models\ProviderSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use App\Models\SubscriptionPlanRequest;
use App\Models\User;
use App\Models\UserSession;
use App\Services\ProviderSubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * حزمة اختبارات الاشتراكات والحصص ومحرك التفعيل التلقائي (Subscription Quota & Feature Limit Suite).
 *
 * مصممة للتحقق من:
 * 1. تفعيل الاشتراك كـ ACTIVE فوراً للشبكة التي لا تملك اشتراكاً سارياً.
 * 2. جدولة الاشتراك الجديد في طابور الانتظار (QUEUED) عند وجود اشتراك نشط.
 * 3. دقة محرك احتساب استهلاك ميزات الباقة (Feature Quota Tracking).
 * 4. حظر العمليات عند استنفاد الحصة المحددة للميزة وعدم وجود باقة مجدولة.
 * 5. محرك التفعيل المبكر التلقائي للباقة المجدولة التالية عند نفاد حصة الباقة الحالية (Early Rollover).
 * 6. دعم الميزات اللامحدودة (Unlimited Features = -1).
 * 7. انتهاء الصلاحية والحرمان من الاستهلاك عند فوات تاريخ الاشتراك (Expiry Enforcement).
 */
class SubscriptionQuotaAndFeatureLimitTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected UserSession $session;
    protected Network $network;
    protected SubscriptionPlan $basicPlan;
    protected SubscriptionPlan $proPlan;
    protected Feature $posQuotaFeature;
    protected SubscriptionPlanFeature $basicPosFeature;
    protected SubscriptionPlanFeature $proPosFeature;
    protected ProviderSubscriptionService $subscriptionService;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();
        $this->subscriptionService = app(ProviderSubscriptionService::class);

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Subscription Test',
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
            'network_name' => 'SubNet '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => 'ACTIVE',
        ]);

        // ميزة عدد نقاط البيع المسموح بها
        $this->posQuotaFeature = Feature::create([
            'feature_name' => 'pos_limit_'.$suffix,
            'feature_value_type' => ValueType::INTEGER,
            'feature_value' => '10',
            'feature_desc' => 'حد نقاط البيع المتاحة للباقة',
            'status' => true,
        ]);

        // خطة أساسية: سارية لمدة 30 يوماً وتسمح بـ 5 نقاط بيع فقط
        $this->basicPlan = SubscriptionPlan::create([
            'plan_name' => 'Basic Plan '.$suffix,
            'plan_price' => 5000.00,
            'plan_validity' => 30,
            'status' => true,
        ]);

        $this->basicPosFeature = SubscriptionPlanFeature::create([
            'plan_id' => $this->basicPlan->plan_id,
            'feature_id' => $this->posQuotaFeature->feature_id,
            'plan_feature_value' => '5', // 5 POS
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        // خطة احترافية: سارية لمدة 60 يوماً وتسمح بـ 20 نقطة بيع
        $this->proPlan = SubscriptionPlan::create([
            'plan_name' => 'Pro Plan '.$suffix,
            'plan_price' => 15000.00,
            'plan_validity' => 60,
            'status' => true,
        ]);

        $this->proPosFeature = SubscriptionPlanFeature::create([
            'plan_id' => $this->proPlan->plan_id,
            'feature_id' => $this->posQuotaFeature->feature_id,
            'plan_feature_value' => '20', // 20 POS
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);
    }

    /**
     * اختبار: تفعيل الاشتراك النشط فوراً عند عدم وجود اشتراك سارٍ مسبقاً
     */
    public function test_initial_subscription_request_approval_becomes_active_immediately(): void
    {
        // 1. تقديم طلب اشتراك للخطة الأساسية
        $subRequest = SubscriptionPlanRequest::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $this->basicPlan->plan_id,
            'requested_by' => $this->user->user_id,
            'request_payment_type' => RequestPaymentType::CASH,
            'request_total_amount' => 5000.00,
            'request_date' => now('Asia/Aden'),
            'status' => 'PENDING',
        ]);

        // 2. اعتماد الطلب
        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v1/subscription-plan-requests/{$subRequest->request_id}/approve");

        $response->assertStatus(200);

        // 3. التحقق من تفعيل الاشتراك بحالة ACTIVE وتطابق مدة الصلاحية
        $subscription = ProviderSubscription::where('network_id', $this->network->network_id)
            ->latest('subscription_id')
            ->firstOrFail();

        $this->assertEquals(ProviderSubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->isActive());
        $this->assertGreaterThanOrEqual(29, $subscription->daysRemaining());
        $this->assertLessThanOrEqual(30, $subscription->daysRemaining());
    }

    /**
     * اختبار: جدولة الاشتراك الجديد في طابور الانتظار (QUEUED) عند وجود اشتراك نشط حالياً
     */
    public function test_subsequent_subscription_approval_is_queued_behind_active_subscription(): void
    {
        // 1. إنشاء اشتراك نشط حالياً
        $activeSub = ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $this->basicPlan->plan_id,
            'subscription_start_date' => now('Asia/Aden'),
            'subscription_end_date' => now('Asia/Aden')->addDays(30),
            'status' => ProviderSubscriptionStatus::ACTIVE,
            'created_by' => $this->user->user_id,
        ]);

        // 2. تقديم واعتماد طلب اشتراك جديد للخطة الاحترافية
        $subRequest = SubscriptionPlanRequest::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $this->proPlan->plan_id,
            'requested_by' => $this->user->user_id,
            'request_payment_type' => RequestPaymentType::CASH,
            'request_total_amount' => 15000.00,
            'request_date' => now('Asia/Aden'),
            'status' => 'PENDING',
        ]);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/v1/subscription-plan-requests/{$subRequest->request_id}/approve");

        $response->assertStatus(200);

        // 3. التحقق من أن الاشتراك الجديد وُضع في طابور الانتظار (QUEUED)
        $queuedSub = ProviderSubscription::where('network_id', $this->network->network_id)
            ->where('plan_id', $this->proPlan->plan_id)
            ->firstOrFail();

        $this->assertEquals(ProviderSubscriptionStatus::QUEUED, $queuedSub->status);
        $this->assertFalse($queuedSub->isActive());

        // والاشتراك الأساسي يظل نشطاً كما هو
        $this->assertTrue($activeSub->refresh()->isActive());
    }

    /**
     * اختبار: دقة احتساب استهلاك الميزات وتحديث دفتر الحصص (Quota Tracking)
     */
    public function test_feature_consumption_tracks_usage_accurately(): void
    {
        $subscription = ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $this->basicPlan->plan_id,
            'subscription_start_date' => now('Asia/Aden'),
            'subscription_end_date' => now('Asia/Aden')->addDays(30),
            'status' => ProviderSubscriptionStatus::ACTIVE,
            'created_by' => $this->user->user_id,
        ]);

        $featureName = $this->posQuotaFeature->feature_name;

        // التحقق من إمكانية الاستهلاك
        $this->assertTrue($this->subscriptionService->canConsume($this->network->network_id, $featureName, 2));

        // استهلاك 2 من أصل 5
        $consumption = $this->subscriptionService->consume($this->network->network_id, $featureName, 2);
        $this->assertEquals(2, $consumption->consumption_value);

        // استهلاك 2 إضافية -> الإجمالي 4
        $consumption = $this->subscriptionService->consume($this->network->network_id, $featureName, 2);
        $this->assertEquals(4, $consumption->consumption_value);

        // بقي 1 متاح فقط من أصل 5
        $this->assertTrue($this->subscriptionService->canConsume($this->network->network_id, $featureName, 1));
        $this->assertFalse($this->subscriptionService->canConsume($this->network->network_id, $featureName, 2), 'لا يمكن استهلاك 2 لأن المتبقي 1 فقط');
    }

    /**
     * اختبار: رفض الاستهلاك عند تجاوز الحصة المحددة في ظل عدم وجود خطة مجدولة
     */
    public function test_consumption_strictly_fails_when_quota_exhausted_without_queued_plan(): void
    {
        ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $this->basicPlan->plan_id,
            'subscription_start_date' => now('Asia/Aden'),
            'subscription_end_date' => now('Asia/Aden')->addDays(30),
            'status' => ProviderSubscriptionStatus::ACTIVE,
            'created_by' => $this->user->user_id,
        ]);

        $featureName = $this->posQuotaFeature->feature_name;

        // استهلاك 5 (كامل الحصة)
        $this->subscriptionService->consume($this->network->network_id, $featureName, 5);

        // محاولة استهلاك 1 إضافي يجب أن ترمي استثناء تحقق 422
        $this->expectException(ValidationException::class);
        $this->subscriptionService->consume($this->network->network_id, $featureName, 1);
    }

    /**
     * اختبار استثنائي: التفعيل المبكر التلقائي للباقة المجدولة فور استنفاد ميزة الباقة الحالية
     */
    public function test_early_activation_of_queued_plan_upon_feature_exhaustion(): void
    {
        // 1. اشتراك أساسي نشط (حصة 5)
        $activeSub = ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $this->basicPlan->plan_id,
            'subscription_start_date' => now('Asia/Aden'),
            'subscription_end_date' => now('Asia/Aden')->addDays(30),
            'status' => ProviderSubscriptionStatus::ACTIVE,
            'created_by' => $this->user->user_id,
        ]);

        // 2. اشتراك احترافي مجدول في الانتظار (حصة 20)
        $queuedSub = ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $this->proPlan->plan_id,
            'subscription_start_date' => now('Asia/Aden')->addDays(30),
            'subscription_end_date' => now('Asia/Aden')->addDays(90),
            'status' => ProviderSubscriptionStatus::QUEUED,
            'created_by' => $this->user->user_id,
        ]);

        $featureName = $this->posQuotaFeature->feature_name;

        // استهلاك 5 (استنفاد حصة الباقة الأولى بالكامل)
        $this->subscriptionService->consume($this->network->network_id, $featureName, 5);

        // التحقق من أن canConsume يعيد true بفضل وجود الخطة المجدولة التالية
        $this->assertTrue($this->subscriptionService->canConsume($this->network->network_id, $featureName, 1));

        // 3. استهلاك إضافي 1 يتجاوز حصة الخطة الأولى:
        // يجب أن يُنهي الخطة الأولى ويُفعل الخطة المجدولة مبكراً فوراً
        $consumption = $this->subscriptionService->consume($this->network->network_id, $featureName, 1);

        $activeSub->refresh();
        $queuedSub->refresh();

        // الخطة الأولى أصبحت منتهية الصلاحية
        $this->assertEquals(ProviderSubscriptionStatus::EXPIRED, $activeSub->status);

        // الخطة المجدولة تم ترقيتها وتفعيلها فوراً
        $this->assertEquals(ProviderSubscriptionStatus::ACTIVE, $queuedSub->status);
        $this->assertTrue($queuedSub->isActive());

        // تم تسجيل الاستهلاك على الخطة الجديدة
        $this->assertEquals(1, $consumption->consumption_value);
    }

    /**
     * اختبار: دعم الميزات غير المحدودة (Unlimited Value = -1)
     */
    public function test_unlimited_feature_value_allows_infinite_consumption(): void
    {
        // خطة غير محدودة
        $unlimitedPlan = SubscriptionPlan::create([
            'plan_name' => 'Unlimited Plan '.uniqid(),
            'plan_price' => 50000.00,
            'plan_validity' => 365,
            'status' => true,
        ]);

        SubscriptionPlanFeature::create([
            'plan_id' => $unlimitedPlan->plan_id,
            'feature_id' => $this->posQuotaFeature->feature_id,
            'plan_feature_value' => '-1', // -1 تعني غير محدود
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $unlimitedPlan->plan_id,
            'subscription_start_date' => now('Asia/Aden'),
            'subscription_end_date' => now('Asia/Aden')->addYear(),
            'status' => ProviderSubscriptionStatus::ACTIVE,
            'created_by' => $this->user->user_id,
        ]);

        $featureName = $this->posQuotaFeature->feature_name;

        $this->assertTrue($this->subscriptionService->canConsume($this->network->network_id, $featureName, 999999));
    }

    /**
     * اختبار: انتهاء صلاحية الاشتراك بالتاريخ يمنع الاستهلاك
     */
    public function test_expired_subscription_by_date_blocks_consumption(): void
    {
        ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $this->basicPlan->plan_id,
            'subscription_start_date' => now('Asia/Aden')->subDays(40),
            'subscription_end_date' => now('Asia/Aden')->subDays(10), // انتهى قبل 10 أيام
            'status' => ProviderSubscriptionStatus::ACTIVE,
            'created_by' => $this->user->user_id,
        ]);

        $featureName = $this->posQuotaFeature->feature_name;

        $this->assertFalse($this->subscriptionService->canConsume($this->network->network_id, $featureName, 1));
    }
}
