<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\ProviderSubscriptionStatus;
use App\Enums\PaymentType;
use App\Enums\ValueType;
use App\Models\Feature;
use App\Models\Network;
use App\Models\ProviderSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SubscriptionSecurityTest extends TestCase
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

        $auth = $this->createAuthenticatedSession($this->user);
        $this->session = $auth['session'];
        $this->headers = $auth['headers'];

        $suffix = uniqid();

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'SecNet '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);
    }

    /**
     * اختبار تأمين عدم التكرار (Idempotency Key) وحماية العمليات من التكرار والازدواجية.
     */
    public function test_idempotency_middleware_prevents_duplicate_processing_and_returns_cached_response(): void
    {
        $idempotencyKey = 'idem-'.uniqid();
        $headersWithKey = array_merge($this->headers, [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        $featureName = 'IDEM_FEATURE_'.uniqid();

        // 1. الطلب الأول: ينفذ وينشئ الميزة بنجاح
        $response1 = $this->withHeaders($headersWithKey)->postJson('/api/v1/features', [
            'feature_name' => $featureName,
            'feature_value_type' => ValueType::INTEGER->value,
            'feature_value' => '100',
            'status' => true,
        ]);

        $response1->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertHeader('X-Idempotency-Key', $idempotencyKey);

        $featureId = $response1->json('data.feature_id');

        // 2. الطلب الثاني بنفس المفتاح: يجب ألا يعيد التنفيذ أو يسبب خطأ تكرار، بل يرجع نفس الاستجابة المخزنة
        $response2 = $this->withHeaders($headersWithKey)->postJson('/api/v1/features', [
            'feature_name' => $featureName,
            'feature_value_type' => ValueType::INTEGER->value,
            'feature_value' => '100',
            'status' => true,
        ]);

        $response2->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.feature_id', $featureId)
            ->assertHeader('X-Idempotency-Key', $idempotencyKey)
            ->assertHeader('X-Idempotency-Hit', 'true');

        // 3. محاولة التلاعب: إرسال نفس المفتاح مع بيانات وحمولة مختلفة (Payload Mismatch Attack) برمز 422
        $tamperedPayloadResponse = $this->withHeaders($headersWithKey)->postJson('/api/v1/features', [
            'feature_name' => 'TAMPERED_'.uniqid(),
            'feature_value_type' => ValueType::INTEGER->value,
            'feature_value' => '999',
            'status' => true,
        ]);

        $tamperedPayloadResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors']);

        // 4. محاكاة طلب قيد التنفيذ اللحظي المتزامن (Concurrent Lock) برمز 409
        $conflictKey = 'conflict-'.uniqid();
        $scope = (string) $this->session->session_id;
        $cacheKey = 'idempotency:'.sha1("{$scope}:POST:api/v1/features:{$conflictKey}");
        Cache::put($cacheKey, ['status' => 'processing'], 60);

        $conflictResponse = $this->withHeaders(array_merge($this->headers, [
            'X-Idempotency-Key' => $conflictKey,
        ]))->postJson('/api/v1/features', [
            'feature_name' => 'CONFLICT_FEATURE_'.uniqid(),
            'feature_value_type' => ValueType::INTEGER->value,
            'feature_value' => '100',
            'status' => true,
        ]);

        $conflictResponse->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors']);
    }

    /**
     * اختبار وسيط التحقق من حصص الاشتراكات (CheckSubscriptionFeatureQuota) والاستجابة الموحدة.
     */
    public function test_subscription_quota_middleware_enforces_active_subscription_and_feature_limits(): void
    {
        $featureName = 'PROTECTED_ACTION_'.uniqid();

        // تسجيل مسار اختباري محمي بوسيط الحصص
        Route::middleware(['session.auth', "subscription.quota:{$featureName},1"])
            ->post('/api/v1/test-protected-quota-action', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تنفيذ الإجراء المحمي بنجاح.',
                    'data' => ['executed' => true],
                    'errors' => [],
                ]);
            });

        // 1. محاولة استدعاء المسار دون وجود اشتراك للشبكة: يجب الرفض برمز 403 واستجابة موحدة
        $failResponse = $this->withHeaders($this->headers)->postJson('/api/v1/test-protected-quota-action', [
            'network_id' => $this->network->network_id,
        ]);

        $failResponse->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'data', 'errors']);

        // 2. إنشاء ميزة وباقة وتفعيل اشتراك للشبكة
        $feature = Feature::create([
            'feature_name' => $featureName,
            'feature_value_type' => ValueType::INTEGER,
            'feature_value' => '2',
            'status' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'plan_name' => 'باقة أمنية '.uniqid(),
            'plan_price' => 10000.00,
            'plan_validity' => 30,
            'status' => true,
        ]);

        $plan->features()->attach($feature->feature_id, [
            'plan_feature_value' => '2',
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        $req = $this->withHeaders($this->headers)->postJson('/api/v1/subscription-plan-requests', [
            'network_id' => $this->network->network_id,
            'plan_id' => $plan->plan_id,
            'request_payment_type' => PaymentType::CASH->value,
        ]);
        $reqId = (int) $req->json('data.request_id');
        $this->withHeaders($this->headers)->postJson("/api/v1/subscription-plan-requests/{$reqId}/approve");

        // 3. إعادة استدعاء المسار المحمي بعد تفعيل الباقة: يجب أن ينجح برمز 200
        $passResponse = $this->withHeaders($this->headers)->postJson('/api/v1/test-protected-quota-action', [
            'network_id' => $this->network->network_id,
        ]);

        $passResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.executed', true);
    }

    /**
     * اختبار مناعة النظام ضد التلاعب بزمن وتاريخ جهاز العميل (Server-Authoritative Clock).
     */
    public function test_server_authoritative_clock_ignores_client_tampered_time_headers(): void
    {
        $plan = SubscriptionPlan::create([
            'plan_name' => 'باقة اختبار الوقت '.uniqid(),
            'plan_price' => 20000.00,
            'plan_validity' => 30,
            'status' => true,
        ]);

        // العميل يحاول تزوير تاريخ البدء أو الصلاحية بتمرير ترويسات أو حقول وقت معدلة من جهازه
        $tamperedHeaders = array_merge($this->headers, [
            'Date' => 'Mon, 01 Jan 2030 00:00:00 GMT',
            'X-Client-Time' => '2030-01-01 00:00:00',
        ]);

        $req = $this->withHeaders($tamperedHeaders)->postJson('/api/v1/subscription-plan-requests', [
            'network_id' => $this->network->network_id,
            'plan_id' => $plan->plan_id,
            'request_payment_type' => PaymentType::CASH->value,
            'subscription_start_date' => '2010-01-01',
            'subscription_end_date' => '2050-01-01',
        ]);

        $requestId = (int) $req->json('data.request_id');

        $approveRes = $this->withHeaders($tamperedHeaders)->postJson("/api/v1/subscription-plan-requests/{$requestId}/approve");

        $approveRes->assertStatus(200);

        $subscriptionData = $approveRes->json('data.subscription');

        // تواريخ الاشتراك في قاعدة البيانات تحتسب حصرياً عبر ساعة الخادم الآن (وليس تلاعب العميل)
        $expectedStartDate = now('Asia/Aden')->format('Y-m-d');
        $expectedEndDate = now('Asia/Aden')->addDays(30)->format('Y-m-d');

        $this->assertStringStartsWith($expectedStartDate, $subscriptionData['subscription_start_date']);
        $this->assertStringStartsWith($expectedEndDate, $subscriptionData['subscription_end_date']);
    }

    /**
     * اختبار التحقق التلقائي الصارم من انتهاء صلاحية الاشتراكات ورفض العمليات فور فوات الأجل.
     */
    public function test_expired_subscription_is_blocked_and_triggers_auto_rotation_if_queued_plan_exists(): void
    {
        $feature = Feature::create([
            'feature_name' => 'EXPIRED_CHECK_'.uniqid(),
            'feature_value_type' => ValueType::INTEGER,
            'feature_value' => '10',
            'status' => true,
        ]);

        $plan1 = SubscriptionPlan::create([
            'plan_name' => 'باقة قديمة '.uniqid(),
            'plan_price' => 10000.00,
            'plan_validity' => 30,
            'status' => true,
        ]);
        $plan1->features()->attach($feature->feature_id, ['plan_feature_value' => '10', 'status' => true, 'created_by' => $this->user->user_id]);

        // تفعيل اشتراك مع تاريخ انتهاء في الماضي (منتهي الصلاحية)
        $expiredSub = ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $plan1->plan_id,
            'subscription_start_date' => now('Asia/Aden')->subDays(40),
            'subscription_end_date' => now('Asia/Aden')->subDays(10), // منتهي منذ 10 أيام
            'status' => ProviderSubscriptionStatus::ACTIVE,
            'created_by' => $this->user->user_id,
        ]);

        // التحقق من أن الموديل يعتبره منتهي الصلاحية فوراً
        $this->assertFalse($expiredSub->isActive());
        $this->assertTrue($expiredSub->isExpired());

        // محاولة استهلاك ميزة من الاشتراك منتهي الصلاحية: يجب الرفض فوراً
        $consumeResponse = $this->withHeaders($this->headers)->postJson("/api/v1/provider-subscriptions/network/{$this->network->network_id}/consume", [
            'feature_name' => $feature->feature_name,
            'amount' => 1,
        ]);

        $consumeResponse->assertStatus(422)
            ->assertJsonPath('success', false);

        // خطة جديدة في طابور الجدولة (QUEUED)
        $plan2 = SubscriptionPlan::create([
            'plan_name' => 'باقة التدوير المجدولة '.uniqid(),
            'plan_price' => 15000.00,
            'plan_validity' => 30,
            'status' => true,
        ]);
        $plan2->features()->attach($feature->feature_id, ['plan_feature_value' => '20', 'status' => true, 'created_by' => $this->user->user_id]);

        $queuedSub = ProviderSubscription::create([
            'network_id' => $this->network->network_id,
            'plan_id' => $plan2->plan_id,
            'subscription_start_date' => now('Asia/Aden')->addDays(1),
            'subscription_end_date' => now('Asia/Aden')->addDays(31),
            'status' => ProviderSubscriptionStatus::QUEUED,
            'created_by' => $this->user->user_id,
        ]);

        // الآن عند الاستعلام عن الاشتراك النشط getActiveForNetwork: يكتشف انتهاء الأول ويفعل المجدول تلقائياً
        $activeResponse = $this->withHeaders($this->headers)->getJson("/api/v1/provider-subscriptions/network/{$this->network->network_id}/active");

        $activeResponse->assertStatus(200)
            ->assertJsonPath('data.subscription_id', $queuedSub->subscription_id)
            ->assertJsonPath('data.status', ProviderSubscriptionStatus::ACTIVE->value);

        $expiredSub->refresh();
        $this->assertEquals(ProviderSubscriptionStatus::EXPIRED, $expiredSub->status);
    }
}
