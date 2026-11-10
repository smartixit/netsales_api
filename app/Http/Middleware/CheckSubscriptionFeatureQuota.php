<?php

namespace App\Http\Middleware;

use App\Models\Network;
use App\Services\Billing\BillingEntitlementService;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionFeatureQuota
{
    use ApiResponseTrait;

    public function __construct(
        private BillingEntitlementService $entitlements
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        ?string $featureName = null
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

        // Usage events must be recorded by the domain service in the same
        // transaction as the measured operation; never after an HTTP response.
        return $next($request);
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

        $networkMember = $user->networkUsers()
            ->where('network_id', $targetNetworkId)
            ->where('status', 'ACTIVE')
            ->exists();

        if ($networkMember) {
            return $targetNetworkId;
        }

        return null;
    }
}
