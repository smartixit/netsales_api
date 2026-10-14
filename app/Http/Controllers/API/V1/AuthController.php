<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterPosRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\RequestPasswordResetRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ToggleTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Auth\VerifyResetOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Http\Resources\API\V1\User\UserDeviceResource;
use App\Http\Resources\API\V1\User\UserResource;
use App\Services\AuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم المصادقة والجلسات وإدارة الحسابات (Enterprise Authentication Controller).
 *
 * يوفر دورة حياة متكاملة للمصادقة تعتمد حصرياً على ترويسة X-Session-Id وجدول user_sessions.
 */
#[OA\Tag(name: 'Auth', description: 'إدارة المصادقة والجلسات وأمان الحسابات والتسجيل وكلمات المرور')]
class AuthController extends BaseController
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * تسجيل مزود شبكة جديد بحالة PENDING.
     */
    #[OA\Post(
        path: '/api/v1/auth/register-network',
        summary: 'تقديم طلب تسجيل مزود شبكة جديد',
        description: 'إنشاء شبكة جديدة وحساب مسؤول الشبكة وجهازه بحالة معلقة (PENDING) في انتظار اعتماد الإدارة.',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 201, description: 'تم استلام طلب التسجيل بنجاح وهو قيد المراجعة والاعتماد'),
            new OA\Response(response: 422, description: 'خطأ في صحة البيانات المدخلة'),
            new OA\Response(response: 500, description: 'خطأ في الخادم الداخلي'),
        ]
    )]
    public function registerNetwork(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->registerNetwork($request);

            return $this->successResponse(
                $result['message'],
                [
                    'network' => new NetworkResource($result['network']),
                    'user' => new UserResource($result['user']),
                ],
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('عذراً، تعذر تقديم طلب تسجيل الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * اسم بديل متوافق مع المسار العام register.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->registerNetwork($request);
    }

    /**
     * تسجيل نقطة بيع جديدة بحالة PENDING.
     */
    #[OA\Post(
        path: '/api/v1/auth/register-pos',
        summary: 'تقديم طلب تسجيل وكيل / نقطة بيع جديدة',
        description: 'إنشاء نقطة بيع جديدة وحساب الوكيل وجهازه بحالة معلقة (PENDING) في انتظار اعتماد الإدارة.',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 201, description: 'تم استلام طلب التسجيل بنجاح وهو قيد المراجعة والاعتماد'),
            new OA\Response(response: 422, description: 'خطأ في صحة البيانات المدخلة'),
            new OA\Response(response: 500, description: 'خطأ في الخادم الداخلي'),
        ]
    )]
    public function registerPos(RegisterPosRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->registerPos($request);

            return $this->successResponse(
                $result['message'],
                [
                    'pos' => new PosResource($result['pos']),
                    'user' => new UserResource($result['user']),
                ],
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('عذراً، تعذر تقديم طلب تسجيل نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تسجيل الدخول للنظام والتحقق من المستخدم والأجهزة والجلسات.
     */
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'تسجيل الدخول للنظام وإنشاء الجلسة',
        description: 'التحقق من بيانات الاعتماد وحالة الحساب والشبكة/نقطة البيع والاشتراك والأجهزة، وإنشاء الجلسة في user_sessions.',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم تسجيل الدخول بنجاح أو طلب كود 2FA'),
            new OA\Response(response: 422, description: 'بيانات الاعتماد غير صحيحة أو الحساب معلق/محظور'),
            new OA\Response(response: 500, description: 'خطأ في الخادم الداخلي'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request);

            if (! empty($result['require_2fa'])) {
                return $this->successResponse($result['message'], [
                    'require_2fa' => true,
                    'is_new_device' => $result['is_new_device'] ?? false,
                    'is_first_device' => $result['is_first_device'] ?? false,
                    'user_phone_number' => $result['user_phone_number'],
                    'device_uuid' => $result['device_uuid'] ?? $request->input('device_uuid'),
                ]);
            }

            $user = $result['user'];
            $session = $result['session'];

            return $this->successResponse('تم تسجيل الدخول بنجاح.', [
                'session_id' => (string) $session->session_id,
                'user' => new UserResource($user),
                'permissions' => $result['permissions'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('عذراً، فشل تسجيل الدخول: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إتمام التحقق الثنائي أو تأكيد ربط الجهاز الجديد وإنشاء الجلسة.
     */
    #[OA\Post(
        path: '/api/v1/auth/2fa/verify',
        summary: 'التحقق من كود OTP وإتمام الدخول للأجهزة الجديدة',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم التحقق بنجاح وإنشاء الجلسة'),
            new OA\Response(response: 422, description: 'كود التحقق غير صحيح أو منتهي'),
        ]
    )]
    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->verifyTwoFactor($request);

            return $this->successResponse('تم التحقق بنجاح وإنشاء جلسة الدخول.', [
                'session_id' => (string) $result['session']->session_id,
                'user' => new UserResource($result['user']),
                'permissions' => $result['permissions'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل التحقق من كود الـ OTP: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب بيانات الحساب الحالي والصلاحيات.
     */
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'جلب بيانات الملف الشخصي للمستخدم الحالي',
        security: [['sessionAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'بيانات الحساب الحالي والصلاحيات'),
            new OA\Response(response: 401, description: 'غير مصادق عليه'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return $this->errorResponse('غير مصادق عليه.', [], 401);
        }

        $result = $this->authService->me($user);

        return $this->successResponse('تم جلب بيانات الملف الشخصي بنجاح.', [
            'user' => new UserResource($result['user']),
            'is_super_admin' => $result['is_super_admin'],
            'role_key' => $result['role_key'],
            'permissions' => $result['permissions'],
        ]);
    }

    /**
     * تسجيل الخروج وإنهاء الجلسة الحالية.
     */
    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'تسجيل الخروج وإنهاء الجلسة الحالية',
        security: [['sessionAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم تسجيل الخروج بنجاح'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $sessionId = (int) $request->attributes->get('session_id');

        if ($user) {
            $this->authService->logout($user, $sessionId);
        }

        return $this->successResponse('تم تسجيل الخروج بنجاح وإلغاء الجلسة.');
    }

    /**
     * تسجيل الخروج من كافة الأجهزة الأخرى.
     */
    #[OA\Post(
        path: '/api/v1/auth/logout-other-devices',
        summary: 'تسجيل الخروج من كافة الأجهزة الأخرى',
        security: [['sessionAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم إنهاء كافة الجلسات الأخرى'),
        ]
    )]
    public function logoutOtherDevices(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $currentSessionId = (int) $request->attributes->get('session_id');

        if (! $user || ! $currentSessionId) {
            return $this->errorResponse('غير مصادق عليه.', [], 401);
        }

        $count = $this->authService->logoutOtherDevices($user, $currentSessionId);

        return $this->successResponse("تم تسجيل الخروج من {$count} جلسة نشطة أخرى بنجاح.");
    }

    /**
     * طلب استعادة كلمة المرور عبر الرمز المؤقت.
     */
    #[OA\Post(
        path: '/api/v1/auth/password/request-reset',
        summary: 'طلب إرسال رمز استعادة كلمة المرور عبر الهاتف',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم إرسال رمز التحقق بنجاح'),
        ]
    )]
    public function requestPasswordReset(RequestPasswordResetRequest $request): JsonResponse
    {
        try {
            $this->authService->requestPasswordReset($request);

            return $this->successResponse('تم إرسال رمز التحقق إلى رقم هاتفك بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل إرسال كود الاستعادة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * التحقق من رمز استعادة كلمة المرور.
     */
    #[OA\Post(
        path: '/api/v1/auth/password/verify-otp',
        summary: 'التحقق من صحة كود استعادة كلمة المرور',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'كود التحقق صحيح'),
        ]
    )]
    public function verifyResetOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->verifyResetOtp($request);

            return $this->successResponse($result['message'] ?? 'تم التحقق بنجاح وإرسال كلمة المرور المؤقتة الجديدة إلى هاتفك عبر رسالة SMS.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('كود التحقق غير صالح: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * اعتماد كلمة المرور الجديدة بعد التحقق.
     */
    #[OA\Post(
        path: '/api/v1/auth/password/reset',
        summary: 'إعادة تعيين كلمة المرور بعد التحقق',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث كلمة المرور بنجاح'),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword($request);

            return $this->successResponse('تم إعادة تعيين كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل إعادة تعيين كلمة المرور: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث كلمة المرور للحساب الحالي.
     */
    #[OA\Post(
        path: '/api/v1/auth/update-password',
        summary: 'تحديث كلمة المرور للمستخدم المصادق عليه',
        security: [['sessionAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث كلمة المرور بنجاح'),
        ]
    )]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('غير مصادق عليه.', [], 401);
            }

            $this->authService->updatePassword($user, $request);

            return $this->successResponse('تم تحديث كلمة المرور بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث كلمة المرور: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تفعيل أو تعطيل المصادقة الثنائية (2FA).
     */
    #[OA\Post(
        path: '/api/v1/auth/toggle-2fa',
        summary: 'تفعيل أو تعطيل المصادقة الثنائية 2FA',
        security: [['sessionAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث حالة المصادقة الثنائية بنجاح'),
        ]
    )]
    public function toggleTwoFactor(ToggleTwoFactorRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('غير مصادق عليه.', [], 401);
            }

            $this->authService->toggleTwoFactor($user, $request);

            $msg = $request->enable ? 'تم تفعيل المصادقة الثنائية بنجاح.' : 'تم تعطيل المصادقة الثنائية بنجاح.';

            return $this->successResponse($msg);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تعديل المصادقة الثنائية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب أجهزة المستخدم المقترنة بالحساب.
     */
    #[OA\Get(
        path: '/api/v1/auth/devices',
        summary: 'جلب قائمة أجهزة المستخدم المقترنة',
        security: [['sessionAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'قائمة الأجهزة المقترنة'),
        ]
    )]
    public function getDevices(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return $this->errorResponse('غير مصادق عليه.', [], 401);
        }

        $devices = $this->authService->getUserDevices($user);

        return $this->successResponse('تم جلب قائمة الأجهزة المقترنة بنجاح.', UserDeviceResource::collection($devices));
    }

    /**
     * إلغاء إقران وفصل جهاز محدد.
     */
    #[OA\Delete(
        path: '/api/v1/auth/devices/{id}',
        summary: 'إلغاء إقران جهاز محدد للمستخدم',
        security: [['sessionAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم فصل الجهاز بنجاح'),
        ]
    )]
    public function disconnectDevice(Request $request, int $id): JsonResponse
    {
        $user = $this->currentUser();
        if (! $user) {
            return $this->errorResponse('غير مصادق عليه.', [], 401);
        }

        $disconnected = $this->authService->disconnectDevice($user, $id);

        if (! $disconnected) {
            return $this->errorResponse('لم يتم العثور على الجهاز أو أنه مفصول مسبقاً.', [], 404);
        }

        return $this->successResponse('تم فصل الجهاز وإلغاء إقرانه بنجاح.');
    }
}
