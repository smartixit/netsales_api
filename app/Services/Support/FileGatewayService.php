<?php

namespace App\Services\Support;

use App\Models\ExpenseAttachment;
use App\Models\SubscriptionPlanRequest;
use App\Models\ReportRun;
use App\Services\Reporting\ReportAuthorizationService;
use App\Models\User;
use App\Services\SessionValidatorService;
use App\Services\SystemLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * خدمة بوابة حراسة وتدفق ملفات النظام المؤسسية (Enterprise File Gateway Service).
 *
 * تتولى بشكل معزول ومستقل:
 * 1. تنقية وتطهير مسارات الملفات من هجمات حقن المحارف الصفرية وتخطي المجلدات (Directory Traversal).
 * 2. التحقق من القوائم البيضاء للوسائط العامة وامتداداتها المصرح بها.
 * 3. التحقق الصارم من التواقيع الرقمية المشفرة (HMAC-SHA256) للملفات الخاصة والمؤقتة.
 * 4. تطبيق العزل المؤسسي ومنع وصول المستخدمين لملفات غير مصرح بها.
 * 5. تشكيل استجابات التدفق (Streaming Responses) مع الترويسات الأمنية وترويسات التخزين المؤقت القياسية.
 */
class FileGatewayService
{
    /**
     * المسارات والأنماط المسموح بطلبها كوسائط عامة لواجهات المستخدم.
     */
    protected const ALLOWED_PUBLIC_PATTERNS = [
        '#^networks/[a-zA-Z0-9_\.-]+/logos/[a-zA-Z0-9_\.-]+$#',
        '#^networks/logos/[a-zA-Z0-9_\.-]+$#',
        '#^pos/[a-zA-Z0-9_\.-]+/logos/[a-zA-Z0-9_\.-]+$#',
        '#^pos/logos/[a-zA-Z0-9_\.-]+$#',
        '#^users/[a-zA-Z0-9_\.-]+/avatar/[a-zA-Z0-9_\.-]+$#',
        '#^users/avatars/[a-zA-Z0-9_\.-]+$#',
        '#^notifications/[a-zA-Z0-9_\.-]+/[a-zA-Z0-9_\.-]+$#',
    ];

    /**
     * الامتدادات المسموح ببثها للوسائط العامة.
     */
    protected const ALLOWED_MEDIA_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'svg', 'gif', 'ico',
    ];

    public function __construct(
        protected SessionValidatorService $sessionValidator
    ) {}

    /**
     * التحقق من المسار وبث الوسائط العامة المصرح بها.
     *
     * @throws HttpException
     */
    public function streamPublicMedia(string $rawPath): Response
    {
        $cleanPath = $this->sanitizePath($rawPath);

        if (! $this->isWhitelistedPublicMedia($cleanPath)) {
            SystemLoggerService::warning('UNAUTHORIZED_FILE_ACCESS', ['path' => $cleanPath]);
            throw new HttpException(403, 'الوصول إلى هذا الملف محظور أو يتطلب صلاحيات مسبقة.');
        }

        $extension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_MEDIA_EXTENSIONS, true)) {
            throw new HttpException(403, 'نوع الملف المطلوب غير مسموح بالعرض المباشر.');
        }

        return $this->buildStreamResponse($cleanPath, isPublic: true);
    }

    /**
     * التحقق من التوقيع الرقمي وصلاحيات المستأجر وبث الملفات المحمية.
     *
     * @throws HttpException
     */
    public function streamSignedFile(Request $request, string $rawPath): Response
    {
        if (! $request->hasValidSignature()) {
            SystemLoggerService::warning('INVALID_OR_EXPIRED_FILE_SIGNATURE', [
                'path' => $rawPath,
                'ip' => $request->ip(),
            ]);

            throw new HttpException(403, 'رابط الملف منتهي الصلاحية أو غير صالح أو تم التلاعب به.');
        }

        $cleanPath = $this->sanitizePath($rawPath);
        $this->enforceMultiTenantIsolation($request, $cleanPath);

        return $this->buildStreamResponse($cleanPath, isPublic: false);
    }

    /**
     * تطهير وتنقية مسار الملف من المحارف المريبة وهجمات تخطي المجلدات.
     *
     * @throws HttpException
     */
    public function sanitizePath(string $path): string
    {
        $clean = ltrim(str_replace(["\0", '../', '..\\'], '', $path), '/');

        if (empty($clean) || str_contains($clean, '..')) {
            throw new HttpException(400, 'مسار الملف غير صالح.');
        }

        return $clean;
    }

    /**
     * التحقق من شروط الوصول والعزل المؤسسي للمستخدم المصادق عليه.
     *
     * @throws HttpException
     */
    protected function enforceMultiTenantIsolation(Request $request, string $cleanPath): void
    {
        $user = $this->resolveAuthenticatedUser($request);
        if (! $user) {
            throw new HttpException(401, 'يجب تسجيل الدخول للوصول إلى هذا الملف المحمي.');
        }

        // Report artifacts are immutable, protected, and re-authorized at
        // download time against the exact tenant and permission saved on run.
        // The platform super-admin bypass below must not cross scope boundaries.
        if (str_starts_with($cleanPath, 'report-artifacts/')) {
            $reportRun = ReportRun::query()
                ->where('artifact_path', $cleanPath)
                ->where('status', 'COMPLETED')
                ->first();

            if (! $reportRun ||
                ($reportRun->expires_at !== null && $reportRun->expires_at->isPast())) {
                throw new HttpException(404, 'ملف التقرير غير موجود أو انتهت صلاحيته.');
            }

            app(ReportAuthorizationService::class)->authorizeRun($user, $reportRun);

            return;
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        // عزل ملفات مرفقات المصروفات
        if (str_starts_with($cleanPath, 'expenses/')) {
            $attachment = ExpenseAttachment::where('file_path', $cleanPath)->with('expense')->first();
            if ($attachment && $attachment->expense) {
                $networkId = $attachment->expense->network_id;
                $hasAccess = $user->networks()->where('networks.network_id', $networkId)->exists()
                    || (method_exists($user, 'networkUsers') && $user->networkUsers()->where('network_id', $networkId)->exists());

                if (! $hasAccess) {
                    throw new HttpException(403, 'غير مصرح لك بالوصول إلى مرفقات مصروفات هذه الشبكة.');
                }
            }
        }

        // عزل ملفات إيصالات سداد الاشتراكات
        if (str_starts_with($cleanPath, 'subscription_receipts/')) {
            $planRequest = SubscriptionPlanRequest::where('payment_receipt', $cleanPath)->first();
            if ($planRequest) {
                $hasAccess = (int) $planRequest->requested_by === (int) $user->user_id
                    || $user->networks()->where('networks.network_id', $planRequest->network_id)->exists();

                if (! $hasAccess) {
                    throw new HttpException(403, 'غير مصرح لك بالوصول إلى إيصال سداد هذا الاشتراك.');
                }
            }
        }
    }

    /**
     * استخراج وتحديد المستخدم المصادق عليه من الجلسة أو ترويسات الطلب.
     */
    protected function resolveAuthenticatedUser(Request $request): ?User
    {
        if ($user = Auth::user()) {
            return $user;
        }

        $token = $request->header('X-Session-Id') ?: $request->bearerToken();
        $deviceUuid = (string) ($request->header('X-Device-Uuid') ?? '');
        if ($token && $deviceUuid) {
            $session = $this->sessionValidator->validateSession($token, $deviceUuid);
            if ($session) {
                $session->loadMissing('user');

                return $session->user;
            }
        }

        return null;
    }

    /**
     * بناء وتجهيز استجابة تدفق الملف من القرص المحمي مع الترويسات الملائمة.
     *
     * @throws HttpException
     */
    protected function buildStreamResponse(string $cleanPath, bool $isPublic): Response
    {
        $protectedDisk = Storage::disk('protected');
        if (! $protectedDisk->exists($cleanPath)) {
            throw new HttpException(404, 'الملف المطلوب غير موجود.');
        }

        $headers = $isPublic
            ? [
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
                'X-Content-Type-Options' => 'nosniff',
            ]
            : [
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
            ];

        return $protectedDisk->response($cleanPath, null, $headers);
    }

    /**
     * التحقق مما إذا كان المسار يندرج ضمن القائمة البيضاء للوسائط العامة.
     */
    public function isWhitelistedPublicMedia(string $path): bool
    {
        foreach (self::ALLOWED_PUBLIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
