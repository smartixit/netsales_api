<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use App\Services\IdempotencyService;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * وسيط منع تكرار العمليات وحالات السباق (Idempotency Key Middleware).
 *
 * يوفر حماية مطابقة لمعايير الشركات العالمية (مثل Stripe و AWS) لضمان:
 * 1. منع تنفيذ العمليات المالية، والخصومات، والتفعيلات مرتين عند انقطاع الاتصال أو تكرار النقر.
 * 2. إرجاع نفس الاستجابة المخزنة فوراً عند إعادة إرسال نفس الطلب بذات المفتاح دون تكرار أي أثر جانبي.
 * 3. قفل المعالجة المتزامنة برمز 409 لمنع هجمات التزامن وحالات السباق اللحظية.
 * 4. إلزام ترويسة X-Idempotency-Key في العمليات المالية الحساسة.
 * 5. التكامل المباشر مع خدمة قاعدة البيانات IdempotencyService وتوكيلات الحجز Fencing Tokens.
 */
class IdempotencyMiddleware
{
    use ApiResponseTrait;

    public function __construct(
        protected ?IdempotencyService $idempotencyService = null
    ) {
        $this->idempotencyService = $idempotencyService ?? app(IdempotencyService::class);
    }

    /**
     * معالجة الطلب الوارد وتطبيق قواعد عدم التكرار.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // تطبيق المنطق فقط على الطلبات المغيرة للحالة (POST, PUT, PATCH, DELETE)
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->header('Idempotency-Key');

        // إلزام ترويسة عدم التكرار في حال تفعيل الوضع الصارم
        if (empty($idempotencyKey)) {
            if ($request->header('X-Strict-Idempotency') === 'true' || $request->header('X-Require-Idempotency') === 'true') {
                return $this->errorResponse(
                    'ترويسة مفتاح عدم التكرار (X-Idempotency-Key) إلزامية لتنفيذ هذه العملية المالية.',
                    [
                        'idempotency_key' => ['مطلوب ترويسة X-Idempotency-Key صالحة لمنع تكرار العمليات المالية الحساسة وحالات السباق.'],
                    ],
                    422
                );
            }

            return $next($request);
        }

        $user = $request->user();

        // حل المستخدم من الجلسة في حال تشغيل الوسيط قبل وسيط الجلسة
        if (! $user && $request->hasHeader('X-Session-Id')) {
            $sessionId = (string) $request->header('X-Session-Id');
            $session = UserSession::where('session_id', $sessionId)
                ->whereNull('revoked_at')
                ->with('user')
                ->first();

            if ($session && $session->user) {
                $user = $session->user;
                $request->setUserResolver(fn () => $user);
            }
        }

        // فحص الذاكرة المؤقتة السريع (L1 Fast Cache) لمعالجة أقفال التزامن والاستجابات اللحظية
        $scope = $request->header('X-Session-Id')
            ?? $request->header('Authorization')
            ?? ($user?->user_id ? 'user_'.$user->user_id : 'ip_'.sha1($request->ip() ?? 'unknown'));

        $cacheKey = 'idempotency:'.sha1("{$scope}:{$request->method()}:{$request->path()}:{$idempotencyKey}");
        $payloadHash = sha1($request->getContent());

        $cachedData = Cache::get($cacheKey);
        if ($cachedData) {
            if (isset($cachedData['payload_hash']) && $cachedData['payload_hash'] !== $payloadHash) {
                return $this->errorResponse(
                    'تم استخدام نفس مفتاح التماثل مع بيانات طلب مختلفة عن الطلب الأصلي.',
                    ['idempotency' => ['عدم تطابق محتوى الطلب الحالي مع الطلب الأصلي المحجوز بنفس المفتاح.']],
                    422
                )->header('X-Idempotency-Key', $idempotencyKey);
            }

            if (($cachedData['status'] ?? '') === 'processing') {
                return $this->errorResponse(
                    'هذه العملية قيد المعالجة حالياً من طلب سابق. يرجى الانتظار وتجنب تكرار الإرسال منعاً للازدواجية.',
                    ['idempotency' => ['الطلب قيد التنفيذ اللحظي بالفعل.']],
                    409
                )->header('X-Idempotency-Key', $idempotencyKey);
            }

            if (($cachedData['status'] ?? '') === 'completed') {
                $statusCode = (int) ($cachedData['status_code'] ?? 200);
                $content = $cachedData['content'] ?? '{}';
                $contentType = $cachedData['headers']['Content-Type'] ?? 'application/json';

                return response($content, $statusCode)
                    ->header('Content-Type', $contentType)
                    ->header('X-Idempotency-Key', $idempotencyKey)
                    ->header('X-Idempotency-Hit', 'true');
            }
        }

        // في حال توفر مستخدم موثق لعملية مالية حساسة، نستخدم الحجز بجدول idempotency_keys
        if ($user && $this->idempotencyService && $this->isCriticalFinancialMutation($request)) {
            return $this->handleWithDatabaseService($request, $next, $user, $idempotencyKey);
        }

        // للعمليات الإدارية وغير المالية، نستخدم القفل في الذاكرة المؤقتة Cache
        return $this->handleWithCache($request, $next, $idempotencyKey);
    }

    /**
     * التحقق مما إذا كان الطلب يمثل حركة مالية حرجة تستوجب مفتاح التماثل إجبارياً.
     */
    protected function isCriticalFinancialMutation(Request $request): bool
    {
        $path = trim($request->path(), '/');

        // استبعاد الاستعلامات
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return false;
        }

        $criticalPatterns = [
            'api/v1/requests',                               // إنشاء طلب توريد باقات
            'api/v1/requests/*/approve',                     // اعتماد طلب التوريد وتوليد الفاتورة
            'api/v1/sale-invoices',                          // إنشاء فاتورة مبيعات مباشرة
            'api/v1/network-wallets/*/deposit',              // إيداع في محفظة الشبكة
            'api/v1/network-wallets/*/withdraw',             // سحب من محفظة الشبكة
            'api/v1/pos-wallets/*/deposit',                  // إيداع في محفظة نقطة البيع
            'api/v1/pos-wallets/*/withdraw',                 // سحب من محفظة نقطة البيع
            'api/v1/pos-wallets/transfer-from-network',       // تحويل رصيد من الشبكة للوكيل
            'api/v1/pos-wallets/transfer-to-network',         // تحويل رصيد من الوكيل للشبكة
            'api/v1/expenses',                               // تسجيل مصروف تشغيلي مخصوم من المحفظة
        ];

        foreach ($criticalPatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * معالجة منع التكرار عبر خدمة قاعدة البيانات IdempotencyService مع توكيلات Fencing Tokens.
     */
    protected function handleWithDatabaseService(Request $request, Closure $next, $user, string $idempotencyKey): Response
    {
        $tenantType = $request->header('X-Tenant-Type')
            ?? ($user->network ? 'NETWORK' : ($user->pos ? 'POS' : 'PLATFORM'));

        $tenantId = (int) ($request->header('X-Tenant-Id')
            ?? ($user->network?->network_id ?? ($user->pos?->pos_id ?? 0)));

        $endpoint = $request->path();
        $payload = $request->all();
        $canonicalHash = $this->idempotencyService->computeCanonicalHash($endpoint, $payload);

        try {
            $reservation = $this->idempotencyService->reserve(
                (int) $user->user_id,
                $tenantType,
                $tenantId,
                $endpoint,
                $idempotencyKey,
                $canonicalHash,
                120
            );
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'تم استخدام نفس مفتاح التماثل مع بيانات طلب مختلفة عن الطلب الأصلي.',
                ['idempotency_key' => ['عدم تطابق محتوى الطلب الحالي مع الطلب الأصلي المحجوز بنفس المفتاح.']],
                422
            )->header('X-Idempotency-Key', $idempotencyKey);
        }

        // إذا كانت العملية مكتملة مسبقاً، إعادة الاستجابة المخزنة فوراً
        if ($reservation['status'] === 'COMPLETED') {
            $responseCode = (int) ($reservation['response_code'] ?? 200);
            $responseBody = $reservation['response_body'] ?? [];
            $responseHeaders = $reservation['response_headers'] ?? [];

            $response = response()->json($responseBody, $responseCode);
            foreach ($responseHeaders as $key => $value) {
                if (! in_array(strtolower($key), ['transfer-encoding', 'content-length'], true)) {
                    $response->headers->set($key, $value);
                }
            }
            $response->headers->set('X-Idempotency-Key', $idempotencyKey);
            $response->headers->set('X-Idempotency-Hit', 'true');

            return $response;
        }

        // إذا كانت العملية قيد المعالجة حالياً (IN_FLIGHT)
        if ($reservation['status'] === 'IN_FLIGHT') {
            return $this->errorResponse(
                'هذه العملية قيد المعالجة حالياً من طلب سابق. يرجى الانتظار وتجنب تكرار الإرسال منعاً للازدواجية.',
                ['idempotency_key' => ['الطلب قيد التنفيذ اللحظي بالفعل.']],
                409
            )->header('X-Idempotency-Key', $idempotencyKey);
        }

        $recordId = (int) ($reservation['id'] ?? 0);
        $fencingToken = (int) ($reservation['fencing_token'] ?? 1);

        try {
            /** @var Response $response */
            $response = $next($request);

            if ($response->isSuccessful()) {
                $content = json_decode($response->getContent(), true) ?? [];
                $headers = [];
                if ($response->headers->has('Content-Type')) {
                    $headers['Content-Type'] = $response->headers->get('Content-Type');
                }

                $this->idempotencyService->complete(
                    $recordId,
                    $fencingToken,
                    $response->getStatusCode(),
                    $content,
                    $headers
                );
            } else {
                // فشل الطلب (خطأ تحقق أو خادم)، وسم المفتاح لتمكين إعادة المحاولة
                $this->idempotencyService->fail($recordId, $fencingToken, true);
            }

            $response->headers->set('X-Idempotency-Key', $idempotencyKey);

            return $response;
        } catch (\Throwable $e) {
            $this->idempotencyService->fail($recordId, $fencingToken, true);
            throw $e;
        }
    }

    /**
     * معالجة منع التكرار عبر الذاكرة المؤقتة Cache للطلبات غير الموثقة بمستخدم.
     */
    protected function handleWithCache(Request $request, Closure $next, string $idempotencyKey): Response
    {
        $scope = $request->header('X-Session-Id')
            ?? $request->header('Authorization')
            ?? 'ip_'.sha1($request->ip() ?? 'unknown');

        $cacheKey = 'idempotency:'.sha1("{$scope}:{$request->method()}:{$request->path()}:{$idempotencyKey}");
        $payloadHash = sha1($request->getContent());

        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            if (isset($cachedData['payload_hash']) && $cachedData['payload_hash'] !== $payloadHash) {
                return $this->errorResponse(
                    'تم استخدام نفس مفتاح التماثل مع بيانات طلب مختلفة عن الطلب الأصلي.',
                    [
                        'idempotency' => ['عدم تطابق محتوى الطلب الحالي مع الطلب الأصلي المحجوز بنفس المفتاح.'],
                    ],
                    422
                )->header('X-Idempotency-Key', $idempotencyKey);
            }

            if (($cachedData['status'] ?? '') === 'processing') {
                return $this->errorResponse(
                    'هذه العملية قيد المعالجة حالياً من طلب سابق. يرجى الانتظار وتجنب تكرار الإرسال منعاً للازدواجية.',
                    [
                        'idempotency' => ['الطلب قيد التنفيذ اللحظي بالفعل.'],
                    ],
                    409
                )->header('X-Idempotency-Key', $idempotencyKey);
            }

            if (($cachedData['status'] ?? '') === 'completed') {
                $statusCode = (int) ($cachedData['status_code'] ?? 200);
                $content = $cachedData['content'] ?? '{}';
                $contentType = $cachedData['headers']['Content-Type'] ?? 'application/json';

                return response($content, $statusCode)
                    ->header('Content-Type', $contentType)
                    ->header('X-Idempotency-Key', $idempotencyKey)
                    ->header('X-Idempotency-Hit', 'true');
            }
        }

        $acquired = Cache::add($cacheKey, [
            'status' => 'processing',
            'payload_hash' => $payloadHash,
            'started_at' => now('Asia/Aden')->toDateTimeString(),
        ], 120);

        if (! $acquired) {
            return $this->errorResponse(
                'هذه العملية قيد المعالجة حالياً. يرجى الانتظار وتجنب تكرار الإرسال.',
                [
                    'idempotency' => ['تم اكتشاف حركة متزامنة بنفس المفتاح.'],
                ],
                409
            )->header('X-Idempotency-Key', $idempotencyKey);
        }

        try {
            $response = $next($request);

            if ($response->isSuccessful()) {
                $content = $response->getContent();

                Cache::put($cacheKey, [
                    'status' => 'completed',
                    'payload_hash' => $payloadHash,
                    'status_code' => $response->getStatusCode(),
                    'content' => $content,
                    'headers' => [
                        'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                    ],
                    'completed_at' => now('Asia/Aden')->toDateTimeString(),
                ], now()->addHours(24));
            } else {
                Cache::forget($cacheKey);
            }

            $response->headers->set('X-Idempotency-Key', $idempotencyKey);

            return $response;
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            throw $e;
        }
    }
}
