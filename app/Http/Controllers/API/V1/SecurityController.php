<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\System\StoreSecurityBanRequest;
use App\Http\Resources\API\V1\System\SecurityBanResource;
use App\Http\Resources\API\V1\User\UserDeviceResource;
use App\Http\Resources\API\V1\User\UserSessionResource;
use App\Services\SecurityService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم الأمان المؤسسي، الحظر، والجلسات (Enterprise Security Controller).
 *
 * يلتزم بمعايير الكود النظيف (Clean Code):
 * - دور المتحكم مقتصر على استقبال الطلبات وتوجيهها لطبقة الخدمة SecurityService وتنسيق الاستجابة.
 * - عزل تام لكافة قواعد العمل، الحركات المالية، والمعاملات (DB Transactions) داخل طبقة الخدمة.
 */
#[OA\Tag(name: 'Security', description: 'إدارة الأمان، الحظر الأمني، الجلسات النشطة، والمفاتيح العامة للتشفير')]
class SecurityController extends BaseController
{
    public function __construct(
        protected SecurityService $securityService,
    ) {}

    /**
     * نشر المفاتيح العامة للتشفير غير المتماثل والتوقيع الرقمي (Public JWKS/Metadata).
     */
    #[OA\Get(
        path: '/api/v1/security/public-keys',
        summary: 'جلب المفاتيح العامة للتشفير والتوقيع الرقمي',
        description: 'يعيد المفاتيح العامة السيادية للخادم بصيغة JWKS/Metadata للتحقق من التوقيعات وتشفير الرسائل من جانب العميل.',
        tags: ['Security'],
        responses: [
            new OA\Response(response: 200, description: 'تم جلب المفاتيح العامة بنجاح'),
            new OA\Response(response: 500, description: 'خطأ داخلي في الخادم'),
        ]
    )]
    public function getPublicKeys(): JsonResponse
    {
        try {
            $metadata = $this->securityService->getPublicKeys();

            return $this->successResponse('تم استرجاع المفاتيح العامة بنجاح.', $metadata);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب المفاتيح العامة: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * جلب الجلسات النشطة لمستخدم محدد.
     */
    #[OA\Get(
        path: '/api/v1/security/users/{id}/sessions',
        summary: 'عرض الجلسات النشطة للمستخدم',
        description: 'استرجاع جميع الجلسات النشطة وغير المنتهية المرتبطة بحساب المستخدم المحدد.',
        tags: ['Security'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الجلسات بنجاح'),
            new OA\Response(response: 422, description: 'المستخدم غير موجود'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function getUserSessions(int $id): JsonResponse
    {
        try {
            $sessions = $this->securityService->getUserSessions($id);

            return $this->successResponse(
                'تم جلب الجلسات النشطة بنجاح.',
                UserSessionResource::collection($sessions)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الجلسات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب الأجهزة المسجلة والمرتبطة بمستخدم محدد.
     */
    #[OA\Get(
        path: '/api/v1/security/users/{id}/devices',
        summary: 'عرض الأجهزة المرتبطة بحساب المستخدم',
        description: 'استرجاع قائمة بكافة الأجهزة المسجلة للمستخدم (الأساسية والثانوية) وحالتها وتاريخ آخر استخدام.',
        tags: ['Security'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف المستخدم', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الأجهزة بنجاح'),
            new OA\Response(response: 422, description: 'المستخدم غير موجود'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function getUserDevices(int $id): JsonResponse
    {
        try {
            $devices = $this->securityService->getUserDevices($id);

            return $this->successResponse(
                'تم جلب أجهزة المستخدم بنجاح.',
                UserDeviceResource::collection($devices)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الأجهزة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حظر مستخدم أمنياً وإنهاء كافة جلساته النشطة فوراً.
     */
    #[OA\Post(
        path: '/api/v1/security/ban-user',
        summary: 'تطبيق حظر أمني على مستخدم',
        description: 'يفرض حظراً أمنياً دائماً أو مؤقتاً على المستخدم مع إبطال كافة جلساته المفتوحة وتحديث حالته إلى BANNED.',
        tags: ['Security'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ban_type'],
                    properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 1),
                    new OA\Property(property: 'device_id', type: 'integer', example: 1),
                    new OA\Property(property: 'ban_type', type: 'string'),
                    new OA\Property(property: 'ban_reason', type: 'string'),
                    new OA\Property(property: 'start_at', type: 'string', format: 'date', example: '2026-01-01'),
                    new OA\Property(property: 'end_at', type: 'string', format: 'date', example: '2026-01-01'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم حظر المستخدم بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
            new OA\Response(response: 403, description: 'غير مصرح'),
        ]
    )]
    public function banUser(StoreSecurityBanRequest $request): JsonResponse
    {
        try {
            $adminUserId = $this->currentUserId();
            $ban = $this->securityService->banUser($request, $adminUserId);

            return $this->successResponse(
                'تم تطبيق الحظر الأمني على المستخدم وإبطال كافة جلساته بنجاح.',
                new SecurityBanResource($ban)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تطبيق الحظر الأمني: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حظر جهاز محدد أمنياً ومنعه من الولوج للنظام.
     */
    #[OA\Post(
        path: '/api/v1/security/ban-device',
        summary: 'تطبيق حظر أمني على جهاز',
        description: 'يفرض حظراً أمنياً على معرف جهاز محدد ويغير حالته إلى BANNED لمنع أي محاولة دخول لاحقة عبره.',
        tags: ['Security'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ban_type'],
                    properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 1),
                    new OA\Property(property: 'device_id', type: 'integer', example: 1),
                    new OA\Property(property: 'ban_type', type: 'string'),
                    new OA\Property(property: 'ban_reason', type: 'string'),
                    new OA\Property(property: 'start_at', type: 'string', format: 'date', example: '2026-01-01'),
                    new OA\Property(property: 'end_at', type: 'string', format: 'date', example: '2026-01-01'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم حظر الجهاز بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function banDevice(StoreSecurityBanRequest $request): JsonResponse
    {
        try {
            $adminUserId = $this->currentUserId();
            $ban = $this->securityService->banDevice($request, $adminUserId);

            return $this->successResponse(
                'تم تطبيق الحظر الأمني على الجهاز بنجاح.',
                new SecurityBanResource($ban)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تطبيق حظر الجهاز: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * رفع الحظر الأمني عن مستخدم أو جهاز.
     */
    #[OA\Delete(
        path: '/api/v1/security/bans/{id}',
        summary: 'رفع الحظر الأمني (Unban)',
        description: 'إلغاء ورفع الحظر الأمني واستعادة الحالة الطبيعية للمستخدم أو الجهاز.',
        tags: ['Security'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف الحظر (ban_id)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم رفع الحظر بنجاح'),
            new OA\Response(response: 422, description: 'سجل الحظر غير موجود'),
        ]
    )]
    public function unban(int $id): JsonResponse
    {
        try {
            $adminUserId = $this->currentUserId();
            $this->securityService->unban($id, $adminUserId);

            return $this->successResponse('تم رفع الحظر الأمني واستعادة النشاط بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر رفع الحظر: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنهاء جلسة معينة إدارياً أو من قبل المستخدم.
     */
    #[OA\Post(
        path: '/api/v1/security/sessions/{id}/terminate',
        summary: 'إنهاء جلسة مستخدم محددة فوراً',
        description: 'إبطال صلاحية جلسة مستخدم محددة عبر معرف الجلسة لمنع أي طلبات إضافية من خلالها.',
        tags: ['Security'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'معرف الجلسة', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم إنهاء الجلسة بنجاح'),
            new OA\Response(response: 422, description: 'الجلسة غير موجودة'),
        ]
    )]
    public function terminateSession(int $id): JsonResponse
    {
        try {
            $adminUserId = $this->currentUserId();
            $this->securityService->terminateSession($id, $adminUserId);

            return $this->successResponse('تم إنهاء الجلسة المحددة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنهاء الجلسة: '.$e->getMessage(), [], 422);
        }
    }
}
