<?php

namespace App\Http\Middleware;

use App\Services\ProviderSubscriptionService;
use App\Traits\ApiResponseTrait;
use Closure;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * وسيط التحقق من سريان اشتراك المزود وتوافر رصيد الميزة (Subscription & Quota Enforcement Middleware).
 *
 * يوفر حماية مركزية على مستوى بوابات الـ HTTP لضمان:
 * 1. وجود اشتراك نشط وسارٍ للمزود قبل تنفيذ العملية.
 * 2. كفاية رصيد الميزة المطلوبة أو إمكانية التفعيل المبكر التلقائي للباقة المجدولة التالية (المسار الأول).
 * 3. حماية الموارد والعمليات التشغيلية (مثل إنشاء نقاط البيع، توليد الكروت، إلخ) من الاستهلاك غير المصرح.
 */
class CheckSubscriptionFeatureQuota
{
    use ApiResponseTrait;

    public function __construct(
        protected ProviderSubscriptionService $subscriptionService
    ) {}

    /**
     * معالجة الطلب الوارد وفحص صلاحية الاشتراك وسريانه وحصة الميزة.
     *
     * الاستخدام في تعريف المسارات:
     * ->middleware('subscription.active') // للتحقق من سريان وصلاحية الاشتراك العام
     * ->middleware('subscription.quota:pos_limit,1') // للتحقق من الصلاحية والحصة
     * ->middleware('subscription.quota:card_generation,1,auto_consume')
     */
    public function handle(Request $request, Closure $next, ?string $featureName = null, int $amount = 1, bool|string $autoConsume = false): Response
    {
        $user = $request->user();
        if (! $user) {
            throw new AuthorizationException('عذراً، يجب تسجيل الدخول أولاً للوصول لهذا الإجراء.');
        }

        // استخراج معرف الشبكة المستهدفة وفق الأولوية الأمنية
        $networkId = $this->resolveNetworkId($request, $user);

        if (! $networkId) {
            return $this->errorResponse(
                'عذراً، لم يتم تحديد شبكة المزود المرتبطة بالعملية للتحقق من حصة الاشتراك.',
                [
                    'network_id' => ['معرف شبكة المزود مفقود في سياق الطلب الحالي.'],
                ],
                422
            );
        }

        // 1. إذا لم يُحدد اسم ميزة، يتم التحقق حصرياً من سريان وصلاحية الاشتراك العام
        if (empty($featureName)) {
            $subscription = $this->subscriptionService->getActiveForNetwork($networkId);
            if (! $subscription || ! $subscription->isActive()) {
                return $this->errorResponse(
                    'عذراً، لا يوجد اشتراك سارٍ ونشط لهذه الشبكة أو أن فترة صلاحية الاشتراك قد انتهت.',
                    [
                        'subscription' => ['فترة صلاحية الاشتراك منتهية ولا توجد باقة مفعلة.'],
                    ],
                    403
                );
            }

            $request->attributes->set('subscription_network_id', $networkId);
            $request->attributes->set('active_subscription', $subscription);

            return $next($request);
        }

        // 2. فحص إمكانية الاستهلاك وفق المسار الأول (الباقة النشطة الحالية أو الباقة المجدولة التالية)
        $canConsume = $this->subscriptionService->canConsume($networkId, $featureName, $amount);

        if (! $canConsume) {
            return $this->errorResponse(
                "عذراً، تم استنفاد الحصة المسموح بها للميزة [{$featureName}] أو أن فترة صلاحية الاشتراك قد انتهت.",
                [
                    'subscription_quota' => ["رصيد الميزة [{$featureName}] غير كافٍ أو أن الاشتراك منتهٍ، ولا توجد باقة اشتراك مجدولة للتفعيل المبكر."],
                ],
                403
            );
        }

        // تخزين معلومات الحصة في خصائص الطلب لاستخدامها في وحدات التحكم أو مراحل لاحقة
        $request->attributes->set('subscription_network_id', $networkId);
        $request->attributes->set('subscription_feature_name', $featureName);
        $request->attributes->set('subscription_feature_amount', $amount);

        $response = $next($request);

        // إذا كان خيار الاستهلاك التلقائي مفعلاً ونجحت العملية (200..299)، يتم قيد الاستهلاك تلقائياً
        $shouldConsume = ($autoConsume === true || $autoConsume === 'auto_consume' || $autoConsume === '1');
        if ($shouldConsume && $response->isSuccessful()) {
            try {
                $this->subscriptionService->consume($networkId, $featureName, $amount);
            } catch (Exception) {
                // في حال حدوث استثناء نادر عند القيد البعدي يتم تسجيله
            }
        }

        return $response;
    }

    /**
     * استخراج معرف الشبكة الموثوقة من سياق الطلب والمستخدم.
     */
    protected function resolveNetworkId(Request $request, $user): ?int
    {
        // 1. إذا كان المستخدم يحمل معرف شبكة صريحاً
        if (! empty($user->network_id)) {
            return (int) $user->network_id;
        }

        // 2. إذا كان المسار يحتوي على معامل الشبكة {network_id}
        if ($request->route('network_id')) {
            return (int) $request->route('network_id');
        }

        // 3. إذا كان المسار يحتوي على {networkId}
        if ($request->route('networkId')) {
            return (int) $request->route('networkId');
        }

        // 4. قراءة الحقل من جسم الطلب
        if ($request->filled('network_id')) {
            return (int) $request->input('network_id');
        }

        return null;
    }
}
