<?php

namespace App\Http\Middleware;

use App\Enums\BillingFeatureType;
use App\Models\Network;
use App\Services\Billing\{BillingEntitlementService, BillingUsageService};
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionFeatureQuota
{
    use ApiResponseTrait;

    public function __construct(
        private BillingEntitlementService $entitlements,
        private BillingUsageService $usage
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        ?string $featureName = null,
        int $amount = 1,
        bool|string $autoConsume = false
    ): Response {
        $user = $request->user();
        if (! $user) {
            throw new AuthorizationException('عذراً، يجب تسجيل الدخول أولاً للوصول لهذا الإجراء.');
        }

        $networkId = $this->resolveNetworkId($request, $user);
        if (! $networkId) {
            return $this->errorResponse(
                'تعذر تحديد شبكة تشغيلية مصرح بها للمستخدم الحالي.',
                ['network_id' => ['معرف الشبكة غير صالح أو لا يملك المستخدم عضوية نشطة فيها.']],
                403
            );
        }

        $subscription = $this->entitlements->activeSubscriptionForNetwork($networkId);
        if (! $subscription) {
            return $this->errorResponse(
                'لا يوجد اشتراك صالح لهذه الشبكة حالياً.',
                ['subscription' => ['لا يوجد اشتراك نشط أو تجربة سارية أو فترة سماح متاحة.']],
                403
            );
        }

        $request->attributes->set('subscription_network_id', $networkId);
        $request->attributes->set('active_subscription', $subscription);

        if ($featureName === null || trim($featureName) === '') {
            return $next($request);
        }

        $this->entitlements->assertEnabled($subscription, $featureName);
        $feature = $subscription->planVersion?->features->firstWhere('feature_key', $featureName);

        if (! $feature) {
            return $this->errorResponse(
                'الميزة المطلوبة غير مضمنة في نسخة الخطة الحالية.',
                ['feature' => ["الميزة [{$featureName}] غير معرفة في اشتراك الشبكة."]],
                403
            );
        }

        $request->attributes->set('subscription_feature_name', $featureName);
        $request->attributes->set('subscription_feature_amount', $amount);

        $response = $next($request);
        $shouldConsume = $autoConsume === true || $autoConsume === 'auto_consume' || $autoConsume === '1';

        if ($shouldConsume && $response->isSuccessful()) {
            if ($feature->feature_type !== BillingFeatureType::METERED) {
                report(new \LogicException(
                    "Auto-consumption middleware only supports METERED features; [{$featureName}] is [{$feature->feature_type->value}]."
                ));

                return $this->errorResponse(
                    'لا يمكن تسجيل الاستهلاك الآلي لهذه الميزة؛ يجب احتساب حدود الموارد داخل خدمة المجال التي تنشئ المورد.',
                    ['feature' => ['العداد الآلي مخصص للميزات من النوع METERED فقط.']],
                    500
                );
            }

            try {
                $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
                if ($idempotencyKey === '') {
                    $idempotencyKey = (string) Str::uuid();
                }

                $this->usage->record(
                    (int) $subscription->billing_subscription_id,
                    $featureName,
                    (string) $amount,
                    $idempotencyKey,
                    'HTTP_ROUTE',
                    null,
                    [
                        'route' => $request->route()?->getName(),
                        'path' => $request->path(),
                        'actor_id' => $user->user_id,
                    ]
                );
            } catch (Throwable $exception) {
                report($exception);
                return $this->errorResponse(
                    'نجحت العملية التشغيلية لكن تعذر تسجيل استهلاك الميزة؛ تم تسجيل الخطأ للتدقيق ويجب استكمال التسوية قبل إعادة المحاولة.',
                    ['subscription_usage' => ['تعذر تسجيل حدث الاستهلاك.']],
                    503
                );
            }
        }

        return $response;
    }

    private function resolveNetworkId(Request $request, $user): ?int
    {
        $targetNetworkId = null;

        foreach (['network_id', 'networkId'] as $parameter) {
            $routeValue = $request->route($parameter);
            if ($routeValue !== null && $routeValue !== '') {
                $targetNetworkId = (int) $routeValue;
                break;
            }

            if ($request->filled($parameter)) {
                $targetNetworkId = (int) $request->input($parameter);
                break;
            }
        }

        if (! $targetNetworkId && ! empty($user->network_id)) {
            $targetNetworkId = (int) $user->network_id;
        }

        if (! $targetNetworkId) {
            return null;
        }

        $network = Network::query()->find($targetNetworkId);
        if (! $network) {
            return null;
        }

        if ((int) $network->owner_id === (int) $user->user_id) {
            return $targetNetworkId;
        }

        $networkMember = $user->networks()
            ->where('networks.network_id', $targetNetworkId)
            ->exists()
            || $user->networkUsers()
                ->where('network_id', $targetNetworkId)
                ->where('status', 'ACTIVE')
                ->exists();

        if ($networkMember) {
            return $targetNetworkId;
        }

        if ($user->pos
            && method_exists($user->pos, 'networks')
            && $user->pos->networks()->where('networks.network_id', $targetNetworkId)->exists()) {
            return $targetNetworkId;
        }

        return null;
    }
}
