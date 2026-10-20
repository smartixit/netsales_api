<?php

namespace App\Http\Middleware;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleKey;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Models\Network;
use App\Models\Pos;
use App\Models\SecurityBan;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\SessionValidatorService;
use App\Services\SystemLoggerService;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * وسيط المصادقة المؤسسية عبر الجلسات النشطة (Enterprise Session Authentication Middleware).
 *
 * يعتمد النظام حصرياً على جدول user_sessions والترويسة X-Session-Id،
 * دون أي استخدام لـ Bearer Token أو التوكنات العشوائية.
 *
 * يقوم الوسيط بالتحقق متدرج المستويات:
 * 1. وجود وصلاحية الجلسة في user_sessions وعدم انتهاء مدتها أو إلغائها.
 * 2. وجود المستخدم وتفعيل حسابه (ACTIVE).
 * 3. عدم وجود حظر أمني نشط على حساب المستخدم في security_bans.
 * 4. التحقق من سلامة شبكة العمل التابع لها الحساب (إن وُجدت).
 * 5. فحص حالة وحظر الجهاز المستخدم (X-Device-Uuid).
 * 6. تسجيل دخول المستخدم في سياق Laravel Auth وربط معرف الجلسة بالطلب.
 */
class SessionAuthMiddleware
{
    use ApiResponseTrait;

    public function __construct(
        protected SessionValidatorService $sessionValidator
    ) {}

    /**
     * معالجة الطلب الوارد والتحقق من مصادقة الجلسة.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. حماية ضد هجمات القوة الغاشمة وتخمين الجلسات (Session Brute Force Rate Limiting)
        $rateLimitKey = 'session_fail:'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return $this->errorResponse(
                "تم حظر المحاولات مؤقتاً لتكرار محاولات جلسات غير صالحة. يرجى المحاولة بعد {$seconds} ثانية.",
                [],
                429
            );
        }

        // 2. استخراج معرف أو رمز الجلسة حصرياً من الترويسة X-Session-Id
        $sessionId = $request->header('X-Session-Id')
            ?? $request->header('session-id')
            ?? $request->bearerToken()
            ?? $request->input('session_id')
            ?? $request->query('session_id');

        if (! $sessionId) {
            return $this->errorResponse('غير مصادق عليه. رمز الجلسة مفقود.', [], 401);
        }

        $deviceUuid = $request->header('X-Device-Uuid') ?? $request->header('X-Device-Id');

        if (empty($deviceUuid)) {
            RateLimiter::hit($rateLimitKey, 60);

            return $this->errorResponse('غير مصادق عليه. ترويسة معرف الجهاز (X-Device-Uuid) مطلوبة.', [], 401);
        }

        // 3. التحقق من صلاحية وسريان الجلسة ومطابقة الجهاز في جدول user_sessions
        $session = $this->sessionValidator->validateSession($sessionId, (string) $deviceUuid);

        if (! $session) {
            RateLimiter::hit($rateLimitKey, 60);

            return $this->errorResponse(
                'انتهت صلاحية الجلسة أو أنها غير صالحة لهذا الجهاز.',
                ['session' => ['الجلسة غير صالحة أو تم إبطالها أو لا تتطابق مع هذا الجهاز.']],
                401
            );
        }

        RateLimiter::clear($rateLimitKey);

        // 3. جلب المستخدم والتحقق من حالته
        $user = User::find($session->user_id);

        if (! $user) {
            return $this->errorResponse('لم يتم العثور على المستخدم المصادق عليه.', [], 404);
        }

        // 3.1 التحقق من حالة المستخدم (PENDING, ACTIVE, INACTIVE, BANNED)
        $isActive = ($user->status instanceof UserStatus)
            ? $user->status === UserStatus::ACTIVE
            : ($user->status === 'ACTIVE' || $user->status === true);

        if (! $isActive) {
            SystemLoggerService::warning('ACCOUNT_INACTIVE', ['status' => $user->status?->value ?? $user->status], $user->user_id);

            return $this->errorResponse('حسابك غير مفعّل، يرجى التواصل مع الإدارة.', [], 403);
        }

        // 3.2 التحقق من الحظر الأمني النشط للمستخدم في جدول security_bans
        $activeBan = SecurityBan::where('user_id', $user->user_id)
            ->active()
            ->latest('ban_id')
            ->first();

        if ($activeBan) {
            $reason = $activeBan->ban_reason ?: 'تم حظر الحساب من قِبل الإدارة.';
            SystemLoggerService::warning('ACCOUNT_BANNED', ['reason' => $reason], $user->user_id);

            return $this->errorResponse('حسابك محظور. السبب: '.$reason, [], 403);
        }

        // 3.3 التحقق من حالة الشبكة أو نقطة البيع التابع لها الحساب
        if ($user->hasRole(RoleKey::NETWORK_OWNER)) {
            $network = $user->networks()->first();

            if (! $network) {
                SystemLoggerService::warning('NETWORK_NOT_FOUND', ['owner_id' => $user->user_id], $user->user_id);

                return $this->errorResponse('لم يتم العثور على مزود الشبكة التابع له الحساب أو تم حذفه.', [], 403);
            }

            $networkStatus = $network->status instanceof NetworkStatus
                ? $network->status
                : NetworkStatus::tryFrom((string) $network->status);

            if ($networkStatus !== NetworkStatus::ACTIVE) {
                SystemLoggerService::warning('NETWORK_INACTIVE', ['network_id' => $network->network_id, 'status' => $networkStatus?->value], $user->user_id);

                return match ($networkStatus) {
                    NetworkStatus::PENDING => $this->errorResponse('طلب مزود الشبكة لا يزال قيد المراجعة والاعتماد من قبل إدارة النظام.', [], 403),
                    NetworkStatus::REJECTED => $this->apiResponse(
                        false,
                        'تم رفض طلب مزود الشبكة.',
                        ['rejection_reason' => $network->rejection_reason],
                        ['rejection_reason' => [$network->rejection_reason]],
                        403
                    ),
                    NetworkStatus::BANNED => $this->errorResponse('مزود الشبكة محظور من قِبل إدارة النظام.', [], 403),
                    default => $this->errorResponse('مزود الشبكة غير مفعّل، يرجى التواصل مع الإدارة.', [], 403),
                };
            }
        } elseif ($user->hasRole(RoleKey::POS_AGENT)) {
            $pos = $user->pos;

            if (! $pos) {
                SystemLoggerService::warning('POS_NOT_FOUND', ['owner_id' => $user->user_id], $user->user_id);

                return $this->errorResponse('لم يتم العثور على نقطة البيع التابعة لهذا الحساب أو تم حذفها.', [], 403);
            }

            $posStatus = $pos->status instanceof PosStatus
                ? $pos->status
                : PosStatus::tryFrom((string) $pos->status);

            if ($posStatus !== PosStatus::ACTIVE) {
                SystemLoggerService::warning('POS_INACTIVE', ['pos_id' => $pos->pos_id, 'status' => $posStatus?->value], $user->user_id);

                return match ($posStatus) {
                    PosStatus::PENDING => $this->errorResponse('طلب نقطة البيع لا يزال قيد المراجعة والاعتماد من قبل إدارة النظام.', [], 403),
                    PosStatus::REJECTED => $this->apiResponse(
                        false,
                        'تم رفض طلب نقطة البيع.',
                        ['rejection_reason' => $pos->rejection_reason],
                        ['rejection_reason' => [$pos->rejection_reason]],
                        403
                    ),
                    PosStatus::BANNED => $this->errorResponse('نقطة البيع محظورة من قِبل إدارة النظام.', [], 403),
                    default => $this->errorResponse('نقطة البيع غير مفعّلة، يرجى التواصل مع الإدارة.', [], 403),
                };
            }
        }

        // 3.4 التحقق من سلامة وحظر الجهاز المستخدم (X-Device-Uuid)
        $device = UserDevice::where('user_id', $user->user_id)
            ->where(function ($q) use ($deviceUuid) {
                $q->where('device_uuid', $deviceUuid);
                if (is_numeric($deviceUuid)) {
                    $q->orWhere('device_id', (int) $deviceUuid);
                }
            })
            ->first();

        if (! $device) {
            SystemLoggerService::warning('DEVICE_NOT_REGISTERED', ['device_uuid' => $deviceUuid], $user->user_id);

            return $this->errorResponse('هذا الجهاز غير مسجل بحساب المستخدم.', [], 403);
        }

        $deviceStatus = $device->status instanceof UserDeviceStatus
            ? $device->status
            : UserDeviceStatus::tryFrom((string) $device->status);

        if ($deviceStatus !== UserDeviceStatus::ACTIVE) {
            SystemLoggerService::warning('DEVICE_INACTIVE', ['device_id' => $device->device_id, 'status' => $deviceStatus?->value], $user->user_id);

            return match ($deviceStatus) {
                UserDeviceStatus::PENDING => $this->errorResponse('هذا الجهاز قيد المراجعة والاعتماد من قبل إدارة النظام.', [], 403),
                UserDeviceStatus::BANNED => $this->errorResponse('تم حظر هذا الجهاز من الدخول إلى النظام.', [], 403),
                UserDeviceStatus::INACTIVE => $this->errorResponse('تم تعطيل هذا الجهاز من قِبل إدارة النظام.', [], 403),
                default => $this->errorResponse('هذا الجهاز غير مفعّل، يرجى التواصل مع الإدارة.', [], 403),
            };
        }

        $activeDeviceBan = SecurityBan::where('device_id', $device->device_id)
            ->active()
            ->latest('ban_id')
            ->first();

        if ($activeDeviceBan) {
            $reason = $activeDeviceBan->ban_reason ?: 'تم حظر هذا الجهاز من قِبل الإدارة.';
            SystemLoggerService::warning('DEVICE_BANNED', ['device_id' => $device->device_id, 'reason' => $reason], $user->user_id);

            return $this->errorResponse('هذا الجهاز محظور. السبب: '.$reason, [], 403);
        }

        $request->attributes->set('device_id', $device->device_id);

        // 4. تسجيل دخول المستخدم في دورة حياة الطلب وتخزين معرف الجلسة
        Auth::login($user);
        $request->attributes->set('session_id', $session->session_id);

        return $next($request);
    }
}
