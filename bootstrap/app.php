<?php

// توحيد المنطقة الزمنية لتوقيت اليمن (Asia/Aden, UTC+3) إجبارياً عبر محرك PHP
date_default_timezone_set('Asia/Aden');

// توجيه مجلدات الملفات المؤقتة إلى storage/app/temp داخل المشروع
// لتفادي قيود open_basedir وصلاحيات مجلد /tmp على خوادم الاستضافة المشتركة
$appTempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'temp';
if (!is_dir($appTempDir)) {
    @mkdir($appTempDir, 0775, true);
}
putenv("TMPDIR={$appTempDir}");
putenv("TEMP={$appTempDir}");
putenv("TMP={$appTempDir}");
$_ENV['TMPDIR'] = $appTempDir;
$_ENV['TEMP'] = $appTempDir;
$_ENV['TMP'] = $appTempDir;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            \App\Http\Middleware\EncryptDecryptPayload::class,
            \App\Http\Middleware\InputSanitization::class,
        ]);

        $middleware->alias([
            'session.auth' => \App\Http\Middleware\SessionAuthMiddleware::class,
            'permission' => \App\Http\Middleware\EnsurePermissionMiddleware::class,
            'app.admin' => \App\Http\Middleware\EnsureAppAdmin::class,
        ]);

        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return null;
            }
            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // نعرض أول رسالة تحقق فعلية (مترجمة بالعربية عبر lang/ar) بدل
                // رسالة عامة مبهمة؛ طالما لا يوجد خطأ نبقى على النص الاحتياطي.
                $message = collect($e->errors())->flatten()->first()
                    ?? 'عذراً، حدث خطأ في المدخلات المزودة.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data'    => (object)[],
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $retryAfter = $e->getHeaders()['Retry-After'] ?? 60;
                return response()->json([
                    'success'     => false,
                    'message'     => 'تم تجاوز الحد المسموح من الطلبات. حاول مرة أخرى بعد قليل.',
                    'data'        => (object)[],
                    'errors'      => [],
                    'retry_after' => (int) $retryAfter,
                ], 429)->header('Retry-After', $retryAfter);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، يجب تسجيل الدخول أولاً للوصول لهذا المسار.',
                    'data'    => (object)[],
                    'errors'  => [],
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'عذراً، ليس لديك الصلاحية الكافية للوصول لهذا المسار.',
                    'data'    => (object)[],
                    'errors'  => [],
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، العنصر المطلوب غير موجود أو ربما تم حذفه.',
                    'data'    => (object)[],
                    'errors'  => [],
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، المسار المطلوب غير موجود، يرجى التأكد من الرابط.',
                    'data'    => (object)[],
                    'errors'  => [],
                ], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'عذراً، الطريقة المستخدمة في الطلب غير مدعومة لهذا المسار.',
                    'data'    => (object)[],
                    'errors'  => [],
                ], 405);
            }
        });

        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => config('app.debug') ? $e->getMessage() : 'عذراً، حدث خطأ أثناء معالجة البيانات في قاعدة البيانات.',
                    'data'    => (object)[],
                    'errors'  => [],
                ], 500);
            }
        });

        $exceptions->render(function (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            return $e->getResponse();
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $statusCode = 500;
                $message = 'عذراً، حدث خطأ تقني داخلي في السيرفر، يرجى المحاولة لاحقاً.';

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $statusCode = $e->getStatusCode();
                    $message = $e->getMessage() ?: \Symfony\Component\HttpFoundation\Response::$statusTexts[$statusCode] ?? 'خطأ تقني';
                } elseif (config('app.debug')) {
                    $message = $e->getMessage();
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'data'    => (object)[],
                    'errors'  => config('app.debug') ? ['exception' => get_class($e), 'file' => $e->getFile(), 'line' => $e->getLine()] : [],
                ], $statusCode);
            }
        });
    })->create();
