<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\RequestPasswordResetRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ToggleTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Auth\VerifyResetOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\Api\V1\CenterResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Auth', description: 'إدارة المصادقة والجلسات وأجهزة المستخدمين وكلمات المرور')]
class AuthController extends BaseController
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'تقديم طلب تسجيل مركز ومسؤول مركز جديد',
        description: 'تسجيل مركز خدمة جديد وإنشاء حساب المسؤول عنه بحالة قيد المراجعة (PENDING) بانتظار موافقة إدارة النظام',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['center_name', 'governorate_id', 'directorate_id', 'user_phone_number', 'user_password', 'device_uuid'],
                    properties: [
                        new OA\Property(property: 'center_name', type: 'string', example: 'مركز الأمل للخدمات الطلابية'),
                        new OA\Property(property: 'governorate_id', type: 'integer', example: 1),
                        new OA\Property(property: 'directorate_id', type: 'integer', example: 1),
                        new OA\Property(property: 'center_phone_number', type: 'string', example: '777112233', nullable: true),
                        new OA\Property(property: 'center_address', type: 'string', example: 'صنعاء - شارع حدة', nullable: true),
                        new OA\Property(property: 'center_description', type: 'string', example: 'مركز خدمات وطباعة', nullable: true),
                        new OA\Property(property: 'center_profile_picture', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'user_phone_number', type: 'string', example: '777000005'),
                        new OA\Property(property: 'user_password', type: 'string', example: 'Secret123!'),
                        new OA\Property(property: 'user_email', type: 'string', example: 'owner@alamal.ye', nullable: true),
                        new OA\Property(property: 'user_profile_picture', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'device_uuid', type: 'string', example: 'uuid-reg-123'),
                        new OA\Property(property: 'device_name', type: 'string', example: 'Chrome Desktop', nullable: true),
                        new OA\Property(property: 'device_type', type: 'string', example: 'WINDOWS', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم تقديم طلب التسجيل بنجاح وهو قيد المراجعة والاعتماد'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة أو رقم الهاتف/اسم المركز مسجل مسبقاً'),
            new OA\Response(response: 500, description: 'خطأ في الخادم'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $userPic = $request->file('user_profile_picture') ?? $request->file('profile_picture');

            $result = $this->authService->register(
                $request->validated(),
                $request->file('center_profile_picture'),
                $userPic
            );

            return $this->successResponse('تم تقديم طلب تسجيل المركز والحساب بنجاح، وطلبك حالياً قيد المراجعة والاعتماد من قِبل إدارة النظام.', [
                'center' => new CenterResource($result['center']),
                'user' => new UserResource($result['user']),
            ], 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('عذراً، فشل تقديم طلب التسجيل: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'تسجيل الدخول للنظام',
        description: 'التحقق من بيانات المستخدم وإنشاء الجلسة النشطة في قاعدة البيانات وفحص الجهاز والمصادقة الثنائية',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_phone_number', 'user_password'],
                properties: [
                    new OA\Property(property: 'user_phone_number', type: 'string', example: '777000001'),
                    new OA\Property(property: 'user_password', type: 'string', example: 'Password123!'),
                    new OA\Property(property: 'device_uuid', type: 'string', example: 'uuid-123-abc', nullable: true),
                    new OA\Property(property: 'device_name', type: 'string', example: 'Chrome Windows', nullable: true),
                    new OA\Property(property: 'device_type', type: 'string', example: 'WINDOWS', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تسجيل الدخول بنجاح أو طلب كود 2FA'),
            new OA\Response(response: 422, description: 'بيانات الاعتماد غير صحيحة أو الحساب معطل'),
            new OA\Response(response: 500, description: 'خطأ في الخادم'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            if ($result['require_2fa']) {
                return $this->successResponse($result['message'], [
                    'require_2fa' => true,
                    'user_phone_number' => $result['user_phone_number'],
                ]);
            }

            $user = $result['user'];
            $session = $result['session'];

            return $this->successResponse('تم تسجيل الدخول بنجاح', [
                'token' => (string) $session->session_id,
                'token_type' => 'Bearer',
                'session_id' => $session->session_id,
                'user' => new UserResource($user),
                'permissions' => $result['permissions'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('عذراً، فشل تسجيل الدخول: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/2fa/verify',
        summary: 'التحقق من كود المصادقة الثنائية (2FA)',
        description: 'إتمام تسجيل الدخول من جهاز جديد بعد مطابقة رمز التحقق',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_phone_number', 'otp', 'device_uuid'],
                properties: [
                    new OA\Property(property: 'user_phone_number', type: 'string', example: '777000001'),
                    new OA\Property(property: 'otp', type: 'string', example: '123456'),
                    new OA\Property(property: 'device_uuid', type: 'string', example: 'uuid-123-abc'),
                    new OA\Property(property: 'device_name', type: 'string', example: 'Chrome Windows', nullable: true),
                    new OA\Property(property: 'device_type', type: 'string', example: 'WINDOWS', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم التحقق وتسجيل الدخول بنجاح'),
            new OA\Response(response: 422, description: 'رمز التحقق غير صحيح أو منتهي'),
        ]
    )]
    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->verifyTwoFactor($request->validated());
            $user = $result['user'];
            $session = $result['session'];

            return $this->successResponse('تم التحقق وتسجيل الدخول بنجاح', [
                'token' => (string) $session->session_id,
                'token_type' => 'Bearer',
                'session_id' => $session->session_id,
                'user' => new UserResource($user),
                'permissions' => $result['permissions'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء التحقق: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'الملف الشخصي للمستخدم الحالي',
        description: 'استرجاع بيانات المستخدم الحالي مع الدور والمركز وقائمة الصلاحيات',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'بيانات الملف الشخصي'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function me(): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً.', [], 401);
            }

            $profile = $this->authService->me($user);

            return $this->successResponse('بيانات الملف الشخصي.', [
                'user' => new UserResource($profile['user']),
                'is_super_admin' => $profile['is_super_admin'],
                'is_center_user' => $profile['is_center_user'],
                'permissions' => $profile['permissions'],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء جلب الملف الشخصي.', [], 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'تسجيل الخروج',
        description: 'إنهاء الجلسة النشطة الحالية للمستخدم',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم تسجيل الخروج بنجاح'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً.', [], 401);
            }

            $sessionId = $request->header('X-Session-Id') ? (int) $request->header('X-Session-Id') : null;
            $this->authService->logout($user, $sessionId);

            return $this->successResponse('تم تسجيل الخروج بنجاح.');
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء تسجيل الخروج.', [], 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/logout-other-devices',
        summary: 'إنهاء الجلسات على الأجهزة الأخرى',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم إنهاء الجلسات الأخرى بنجاح'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function logoutOtherDevices(Request $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً.', [], 401);
            }

            $sessionId = $request->header('X-Session-Id') ? (int) $request->header('X-Session-Id') : 0;
            $count = $this->authService->logoutOtherDevices($user, $sessionId);

            return $this->successResponse("تم إنهاء {$count} من الجلسات النشطة على الأجهزة الأخرى بنجاح.");
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء إنهاء الجلسات الأخرى.', [], 500);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/password/request-reset',
        summary: 'طلب استعادة كلمة المرور',
        description: 'إرسال رمز OTP عبر رسالة قصيرة SMS لرقم هاتف المستخدم المسجل',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_phone_number'],
                properties: [
                    new OA\Property(property: 'user_phone_number', type: 'string', example: '777000001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم إرسال كود التحقق بنجاح'),
            new OA\Response(response: 422, description: 'رقم الهاتف غير مسجل أو الحساب معطل'),
        ]
    )]
    public function requestPasswordReset(RequestPasswordResetRequest $request): JsonResponse
    {
        try {
            $this->authService->requestPasswordReset($request->validated()['user_phone_number']);

            return $this->successResponse('تم إرسال رمز التحقق إلى رقم هاتفك بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء طلب استعادة كلمة المرور: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/password/verify-otp',
        summary: 'التحقق من كود استعادة كلمة المرور',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_phone_number', 'otp'],
                properties: [
                    new OA\Property(property: 'user_phone_number', type: 'string', example: '777000001'),
                    new OA\Property(property: 'otp', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'كود التحقق صحيح'),
            new OA\Response(response: 422, description: 'كود التحقق غير صحيح أو منتهي'),
        ]
    )]
    public function verifyResetOtp(VerifyResetOtpRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $this->authService->verifyResetOtp($validated['user_phone_number'], $validated['otp']);

            return $this->successResponse('رمز التحقق صحيح، يمكنك الآن تعيين كلمة المرور الجديدة.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء التحقق من الرمز: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/password/reset',
        summary: 'اعتماد كلمة المرور الجديدة',
        description: 'إعادة تعيين كلمة المرور وإنهاء كافة الجلسات النشطة لحماية الحساب',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_phone_number', 'otp', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'user_phone_number', type: 'string', example: '777000001'),
                    new OA\Property(property: 'otp', type: 'string', example: '123456'),
                    new OA\Property(property: 'password', type: 'string', example: 'NewSecret123!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', example: 'NewSecret123!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تعيين كلمة المرور بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $this->authService->resetPassword(
                $validated['user_phone_number'],
                $validated['otp'],
                $validated['password']
            );

            return $this->successResponse('تمت إعادة تعيين كلمة المرور بنجاح، يرجى تسجيل الدخول بكلمة المرور الجديدة.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء إعادة تعيين كلمة المرور: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/update-password',
        summary: 'تحديث كلمة المرور للمستخدم الحالي',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', example: 'OldPassword123!'),
                    new OA\Property(property: 'new_password', type: 'string', example: 'NewPassword123!'),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', example: 'NewPassword123!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث كلمة المرور بنجاح'),
            new OA\Response(response: 422, description: 'كلمة المرور الحالية غير صحيحة'),
        ]
    )]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً.', [], 401);
            }

            $validated = $request->validated();
            $this->authService->updatePassword($user, $validated['current_password'], $validated['new_password']);

            return $this->successResponse('تم تحديث كلمة المرور بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء تحديث كلمة المرور: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/auth/toggle-2fa',
        summary: 'تفعيل أو تعطيل المصادقة الثنائية (2FA)',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['enable'],
                properties: [
                    new OA\Property(property: 'enable', type: 'boolean', example: true),
                    new OA\Property(property: 'otp', type: 'string', example: '123456', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تعديل حالة المصادقة الثنائية بنجاح'),
            new OA\Response(response: 422, description: 'خطأ في التحقق من البيانات'),
        ]
    )]
    public function toggleTwoFactor(ToggleTwoFactorRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً.', [], 401);
            }

            $validated = $request->validated();
            $this->authService->toggleTwoFactor($user, (bool) $validated['enable'], $validated['otp'] ?? null);

            $statusText = $validated['enable'] ? 'تفعيل' : 'تعطيل';

            return $this->successResponse("تم {$statusText} المصادقة الثنائية بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء تعديل المصادقة الثنائية: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Get(
        path: '/api/v1/auth/devices',
        summary: 'قائمة الأجهزة المقترنة بحساب المستخدم',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'تم جلب قائمة الأجهزة بنجاح'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function getDevices(): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً.', [], 401);
            }

            $devices = $this->authService->getUserDevices($user);

            return $this->successResponse('تم جلب قائمة الأجهزة بنجاح.', $devices);
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء جلب قائمة الأجهزة.', [], 500);
        }
    }

    #[OA\Delete(
        path: '/api/v1/auth/devices/{id}',
        summary: 'إلغاء إقران جهاز محدد',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        parameters: [
            new OA\Parameter(name: 'id', description: 'معرف الجهاز', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم إلغاء إقران الجهاز بنجاح'),
            new OA\Response(response: 404, description: 'الجهاز غير موجود'),
        ]
    )]
    public function disconnectDevice(int $id): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('يجب تسجيل الدخول أولاً.', [], 401);
            }

            $success = $this->authService->disconnectDevice($user, $id);
            if (! $success) {
                return $this->errorResponse('الجهاز غير موجود أو لا يتبع حسابك.', [], 404);
            }

            return $this->successResponse('تم إلغاء إقران الجهاز بنجاح.');
        } catch (Exception $e) {
            return $this->errorResponse('حدث خطأ أثناء محاولة إلغاء إقران الجهاز.', [], 500);
        }
    }
}
