<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Auth\LoginRequest;
use App\Http\Requests\API\V1\Auth\RegisterPosRequest;
use App\Http\Requests\API\V1\Auth\RegisterNetworkRequest;
use App\Http\Requests\API\V1\Auth\RequestPasswordResetRequest;
use App\Http\Requests\API\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\API\V1\Auth\ToggleTwoFactorRequest;
use App\Http\Requests\API\V1\Auth\UpdatePasswordRequest;
use App\Http\Requests\API\V1\Auth\VerifyResetOtpRequest;
use App\Http\Requests\API\V1\Auth\VerifyTwoFactorRequest;
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات تسجيل مزود الشبكة وحساب المدير والجهاز بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_name', 'governorate_id', 'directorate_id', 'user_full_name', 'user_phone_number', 'user_password', 'device_uuid'],
                    properties: [
                        new OA\Property(property: 'network_name', type: 'string', example: 'شبكة سمارتكس'),
                        new OA\Property(property: 'governorate_id', type: 'integer', example: 1),
                        new OA\Property(property: 'directorate_id', type: 'integer', example: 1),
                        new OA\Property(property: 'network_email', type: 'string', format: 'email', example: 'network@example.com'),
                        new OA\Property(property: 'network_address', type: 'string', example: 'شارع حدة، صنعاء'),
                        new OA\Property(property: 'network_profile_logo', type: 'string', format: 'binary', description: 'شعار الشبكة'),
                        new OA\Property(property: 'user_full_name', type: 'string', example: 'حسام خالد سعيد علي'),
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '772957881'),
                        new OA\Property(property: 'user_password', type: 'string', format: 'password', example: 'Secret@123'),
                        new OA\Property(property: 'user_email', type: 'string', format: 'email', example: 'admin@example.com'),
                        new OA\Property(property: 'user_profile_logo', type: 'string', format: 'binary', description: 'صورة المستخدم الشخصية'),
                        new OA\Property(property: 'device_uuid', type: 'string', example: 'SPA-251DHS'),
                        new OA\Property(property: 'device_name', type: 'string', example: 'Samsung S22'),
                        new OA\Property(property: 'device_type', type: 'string', enum: ['ANDROID', 'LINUX', 'IOS', 'WINDOWS', 'MAC'], example: 'ANDROID'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم استلام طلب التسجيل بنجاح وهو قيد المراجعة والاعتماد'),
            new OA\Response(response: 422, description: 'خطأ في صحة البيانات المدخلة'),
            new OA\Response(response: 500, description: 'خطأ في الخادم الداخلي'),
        ]
    )]
    public function registerNetwork(RegisterNetworkRequest $request): JsonResponse
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
     * تسجيل نقطة بيع جديدة بحالة PENDING.
     */
    #[OA\Post(
        path: '/api/v1/auth/register-pos',
        summary: 'تقديم طلب تسجيل وكيل / نقطة بيع جديدة',
        description: 'إنشاء نقطة بيع جديدة وحساب الوكيل وجهازه بحالة معلقة (PENDING) في انتظار اعتماد الإدارة.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات تسجيل نقطة البيع وحساب الوكيل والجهاز بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['pos_name', 'governorate_id', 'directorate_id', 'user_full_name', 'user_phone_number', 'user_password', 'device_uuid'],
                    properties: [
                        new OA\Property(property: 'pos_name', type: 'string', example: 'نقطة بيع النور'),
                        new OA\Property(property: 'governorate_id', type: 'integer', example: 1),
                        new OA\Property(property: 'directorate_id', type: 'integer', example: 1),
                        new OA\Property(property: 'pos_email', type: 'string', format: 'email', example: 'pos@example.com'),
                        new OA\Property(property: 'pos_address', type: 'string', example: 'حي الصافية، صنعاء'),
                        new OA\Property(property: 'pos_profile_logo', type: 'string', format: 'binary', description: 'شعار نقطة البيع'),
                        new OA\Property(property: 'network_id', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'user_full_name', type: 'string', example: 'خالد عبد الله صالح'),
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '771234567'),
                        new OA\Property(property: 'user_password', type: 'string', format: 'password', example: 'Secret@123'),
                        new OA\Property(property: 'user_email', type: 'string', format: 'email', example: 'pos.admin@example.com'),
                        new OA\Property(property: 'user_profile_logo', type: 'string', format: 'binary', description: 'صورة المستخدم الشخصية'),
                        new OA\Property(property: 'device_uuid', type: 'string', example: 'e5c18495-e224-57e5-b10c-7c7f95e302f1'),
                        new OA\Property(property: 'device_name', type: 'string', example: 'Sunmi V2 POS'),
                        new OA\Property(property: 'device_type', type: 'string', enum: ['ANDROID', 'LINUX', 'IOS', 'WINDOWS', 'MAC'], example: 'ANDROID'),
                    ]
                )
            )
        ),
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات تسجيل الدخول ومعلومات الجهاز بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_phone_number', 'user_password', 'device_uuid'],
                    properties: [
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '772957881'),
                        new OA\Property(property: 'user_password', type: 'string', format: 'password', example: 'Admin@123456'),
                        new OA\Property(property: 'device_uuid', type: 'string', example: 'SPA-251DHS'),
                        new OA\Property(property: 'device_name', type: 'string', nullable: true, example: 'Chrome Browser Windows'),
                        new OA\Property(property: 'device_type', type: 'string', enum: ['ANDROID', 'LINUX', 'IOS', 'WINDOWS', 'MAC'], example: 'ANDROID'),
                    ]
                )
            )
        ),
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
                    'is_primary' => $result['is_primary'] ?? false,
                    'user_phone_number' => $result['user_phone_number'],
                    'device_uuid' => $result['device_uuid'] ?? $request->input('device_uuid'),
                ]);
            }

            $user = $result['user'];
            $session = $result['session'];

            return $this->successResponse('تم تسجيل الدخول بنجاح.', [
                'session_id' => (string) ($session->session_token ?: $session->session_id),
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات التحقق الثنائي OTP وتأكيد الجهاز بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_phone_number', 'otp', 'device_uuid'],
                    properties: [
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '772957881'),
                        new OA\Property(property: 'otp', type: 'string', example: '123456'),
                        new OA\Property(property: 'device_uuid', type: 'string', example: 'SPA-251DHS'),
                        new OA\Property(property: 'device_name', type: 'string', nullable: true, example: 'Samsung S22'),
                        new OA\Property(property: 'device_type', type: 'string', enum: ['ANDROID', 'LINUX', 'IOS', 'WINDOWS', 'MAC'], example: 'ANDROID'),
                    ]
                )
            )
        ),
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
                'session_id' => (string) ($result['session']->session_token ?: $result['session']->session_id),
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
        try {
            $user = $this->currentUser();
            $sessionId = (int) $request->attributes->get('session_id');
            $deviceUuid = (string) ($request->header('X-Device-Uuid') ?: $request->header('X-Device-Id') ?: $request->input('device_uuid'));

            if (empty($deviceUuid)) {
                return $this->errorResponse('معرف الجهاز مطلوب لتسجيل الخروج.', [], 422);
            }

            if ($user) {
                $this->authService->logout($user, $sessionId, $deviceUuid);
            }

            return $this->successResponse('تم تسجيل الخروج بنجاح وإلغاء الجلسة.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تسجيل الخروج: '.$e->getMessage(), [], 422);
        }
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'رقم هاتف المستخدم لطلب استعادة كلمة المرور بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_phone_number'],
                    properties: [
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '772957881'),
                    ]
                )
            )
        ),
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'التحقق من رمز OTP لاستعادة كلمة المرور بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_phone_number', 'otp'],
                    properties: [
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '772957881'),
                        new OA\Property(property: 'otp', type: 'string', example: '123456'),
                    ]
                )
            )
        ),
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'إعادة تعيين كلمة المرور الجديدة مع رمز التحقق بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_phone_number', 'otp', 'password', 'password_confirmation'],
                    properties: [
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '772957881'),
                        new OA\Property(property: 'otp', type: 'string', example: '123456'),
                        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NewSecret@123'),
                        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NewSecret@123'),
                    ]
                )
            )
        ),
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'تحديث كلمة المرور الحالية بكلمة مرور جديدة بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['current_password', 'new_password', 'new_password_confirmation'],
                    properties: [
                        new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'OldSecret@123'),
                        new OA\Property(property: 'new_password', type: 'string', format: 'password', example: 'NewSecret@123'),
                        new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password', example: 'NewSecret@123'),
                    ]
                )
            )
        ),
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'تفعيل أو تعطيل ميزة التحقق الثنائي بصيغة النموذج (Form Data)',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['enable'],
                    properties: [
                        new OA\Property(property: 'enable', type: 'boolean', example: true),
                        new OA\Property(property: 'otp', type: 'string', nullable: true, example: '123456'),
                    ]
                )
            )
        ),
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
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'معرف الجهاز المراد فصله وإلغاء اقترانه',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
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

    public function healthCheck(Request $request)
    {
        return response()->json([
            'isAlive' => true,
            'status' => 'ok',
            'message' => 'الخادم يعمل بشكل صحيح',
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }
}
