<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * وسيط منع تكرار العمليات وحالات السباق (Idempotency Key Middleware).
 *
 * يوفر حماية مطابقة لمعايير الشركات العالمية (مثل Stripe و AWS) لضمان:
 * 1. منع تنفيذ العمليات المالية، والخصومات، والتفعيلات مرتين عند انقطاع الاتصال أو تكرار النقر.
 * 2. إرجاع نفس الاستجابة المخزنة فوراً عند إعادة إرسال نفس الطلب بذات المفتاح دون تكرار أي أثر جانبي.
 * 3. قفل المعالجة المتزامنة برمز 409 لمنع هجمات التزامن وحالات السباق اللحظية.
 */
class IdempotencyMiddleware
{
    use ApiResponseTrait;

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

        // إذا لم يرسل العميل مفتاح التماثل، يمر الطلب بصورة اعتيادية
        if (empty($idempotencyKey)) {
            return $next($request);
        }

        $scope = $request->header('X-Session-Id')
            ?? $request->header('Authorization')
            ?? ($request->user()?->user_id ? 'user_'.$request->user()->user_id : 'ip_'.sha1($request->ip() ?? 'unknown'));
        $cacheKey = 'idempotency:'.sha1("{$scope}:{$request->method()}:{$request->path()}:{$idempotencyKey}");
        $payloadHash = sha1($request->getContent());

        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            // التحقق من تطابق محتوى الطلب مع الطلب الأصلي لمنع التلاعب بالبيانات عند استخدام نفس المفتاح
            if (isset($cachedData['payload_hash']) && $cachedData['payload_hash'] !== $payloadHash) {
                return $this->errorResponse(
                    'تم استخدام نفس مفتاح التماثل مع بيانات طلب مختلفة عن الطلب الأصلي.',
                    [
                        'idempotency' => ['عدم تطابق محتوى الطلب الحالي مع الطلب الأصلي المحجوز بنفس المفتاح.'],
                    ],
                    422
                )->header('X-Idempotency-Key', $idempotencyKey);
            }

            // إذا كانت العملية قيد التنفيذ حالياً، منع التزامن برمز 409 Conflict
            if (($cachedData['status'] ?? '') === 'processing') {
                return $this->errorResponse(
                    'هذه العملية قيد المعالجة حالياً من طلب سابق. يرجى الانتظار وتجنب تكرار الإرسال منعاً للازدواجية.',
                    [
                        'idempotency' => ['الطلب قيد التنفيذ اللحظي بالفعل.'],
                    ],
                    409
                )->header('X-Idempotency-Key', $idempotencyKey);
            }

            // إذا كانت العملية قد اكتملت بنجاح سابقاً، إرجاع النتيجة الأصلية المخزنة فوراً
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

        // حجز قفل المعالجة ذرياً مع بصمة البيانات لمدة 120 ثانية
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

            // إذا نجحت العملية (200..299)، حفظ الاستجابة وبصمة البيانات في الذاكرة المؤقتة لمدة 24 ساعة
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
                // في حال فشل الطلب بخطأ تحقق أو خادم، إلغاء القفل لتمكين العميل من إعادة المحاولة بعد التصحيح
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
