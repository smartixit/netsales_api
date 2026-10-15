<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Notification\StoreNotificationRequest;
use App\Http\Requests\API\V1\Notification\UpdateFcmTokenRequest;
use App\Http\Requests\API\V1\Notification\UpdateNotificationRequest;
use App\Http\Resources\API\V1\Notification\NotificationResource;
use App\Services\NotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة الإشعارات والتنبيهات الموجهة (Notification Controller).
 *
 * متحكم نحيف صارم (Strict Lean Controller) يلتزم بالمعايير:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة مع ApiResponseTrait.
 * 3. حظر كتابة أي استعلامات مباشرة أو DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Notifications', description: 'إدارة الإشعارات والتنبيهات الموجهة والتفاعلية')]
class NotificationController extends BaseController
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * عرض قائمة الإشعارات العامة مع الفلترة والترقيم بالمؤشر (إداري).
     */
    #[OA\Get(
        path: '/api/v1/notifications',
        summary: 'عرض قائمة الإشعارات العامة',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'notification_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_read', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الإشعارات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['user_id', 'notification_type', 'is_read', 'search', 'from_date', 'to_date', 'per_page', 'all']);
            $notifications = $this->notificationService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة الإشعارات بنجاح.', NotificationResource::collection($notifications));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الإشعارات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض إشعارات المستخدم الحالي المسجل دخوله.
     */
    #[OA\Get(
        path: '/api/v1/notifications/my',
        summary: 'عرض إشعارات المستخدم الحالي',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'is_read', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع إشعارات المستخدم بنجاح'),
        ]
    )]
    public function myNotifications(Request $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()->user_id;
            $filters = $request->only(['notification_type', 'is_read', 'search', 'per_page', 'all']);
            $notifications = $this->notificationService->getMyNotifications($userId, $filters);

            return $this->successResponse('تم استرجاع إشعاراتك بنجاح.', NotificationResource::collection($notifications));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب إشعاراتك: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * جلب عدد الإشعارات غير المقروءة للمستخدم الحالي.
     */
    #[OA\Get(
        path: '/api/v1/notifications/unread-count',
        summary: 'عدد الإشعارات غير المقروءة',
        
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'تم جلب عدد الإشعارات غير المقروءة بنجاح'),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()->user_id;
            $count = $this->notificationService->getUnreadCount($userId);

            return $this->successResponse('تم جلب عدد الإشعارات غير المقروءة بنجاح.', ['unread_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب عدد الإشعارات غير المقروءة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة الإشعارات المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/notifications/trashed',
        summary: 'عرض الإشعارات المحذوفة',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'notification_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الإشعارات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['user_id', 'notification_type', 'search', 'per_page', 'all']);
            $notifications = $this->notificationService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع الإشعارات المحذوفة بنجاح.', NotificationResource::collection($notifications));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الإشعارات المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء إشعار وتوجيهه لمستخدم محدد (إداري).
     */
    #[OA\Post(
        path: '/api/v1/notifications',
        summary: 'إنشاء وإرسال إشعار جديد',
        
        tags: ['Notifications'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_id', 'notification_type', 'notification_title', 'notification_message'],
                    properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 1),
                    new OA\Property(property: 'notification_type', type: 'string'),
                    new OA\Property(property: 'notification_title', type: 'string'),
                    new OA\Property(property: 'notification_message', type: 'string'),
                    new OA\Property(property: 'notification_image', type: 'string'),
                    new OA\Property(property: 'is_read', type: 'boolean', example: true),
                    new OA\Property(property: 'read_at', type: 'string', format: 'date', example: '2026-01-01'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء وإرسال الإشعار بنجاح'),
        ]
    )]
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        try {
            $notification = $this->notificationService->create($request);

            return $this->successResponse('تم إنشاء وإرسال الإشعار بنجاح.', new NotificationResource($notification), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء الإشعار: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل إشعار محدد.
     */
    #[OA\Get(
        path: '/api/v1/notifications/{id}',
        summary: 'عرض تفاصيل إشعار',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الإشعار بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $notification = $this->notificationService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل الإشعار بنجاح.', new NotificationResource($notification));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب تفاصيل الإشعار: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات إشعار قائم (إداري).
     */
    #[OA\Put(
        path: '/api/v1/notifications/{id}',
        summary: 'تحديث بيانات إشعار',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [],
                    properties: [
                    new OA\Property(property: 'user_id', type: 'integer', example: 1),
                    new OA\Property(property: 'notification_type', type: 'string'),
                    new OA\Property(property: 'notification_title', type: 'string'),
                    new OA\Property(property: 'notification_message', type: 'string'),
                    new OA\Property(property: 'notification_image', type: 'string'),
                    new OA\Property(property: 'is_read', type: 'boolean', example: true),
                    new OA\Property(property: 'read_at', type: 'string', format: 'date', example: '2026-01-01'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث الإشعار بنجاح'),
        ]
    )]
    public function update(UpdateNotificationRequest $request, int $id): JsonResponse
    {
        try {
            $notification = $this->notificationService->update($request, $id);

            return $this->successResponse('تم تحديث بيانات الإشعار بنجاح.', new NotificationResource($notification));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث الإشعار: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف إشعار ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/notifications/{id}',
        summary: 'حذف إشعار ناعماً',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الإشعار بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->notificationService->delete($id);

            return $this->successResponse('تم حذف الإشعار بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف الإشعار: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة إشعار محذوف ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/notifications/{id}/restore',
        summary: 'استعادة إشعار محذوف',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الإشعار بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $notification = $this->notificationService->restore($id);

            return $this->successResponse('تم استعادة الإشعار بنجاح.', new NotificationResource($notification));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة الإشعار: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لإشعار من قاعدة البيانات.
     */
    #[OA\Delete(
        path: '/api/v1/notifications/{id}/force',
        summary: 'الحذف النهائي لإشعار',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي للإشعار بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->notificationService->forceDelete($id);

            return $this->successResponse('تم الحذف النهائي للإشعار بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للإشعار: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع للإشعارات ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/notifications/batch-delete',
        summary: 'الحذف المجمع للإشعارات',
        
        tags: ['Notifications'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                    new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف المجمع للإشعارات بنجاح'),
        ]
    )]
    public function batchDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->notificationService->bulkDelete($request);

            return $this->successResponse("تم حذف [{$count}] إشعار بنجاح.", ['deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة للإشعارات المحذوفة ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/notifications/batch-restore',
        summary: 'الاستعادة المجمعة للإشعارات',
        
        tags: ['Notifications'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                    new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الإشعارات المحددة بنجاح'),
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->notificationService->bulkRestore($request);

            return $this->successResponse("تم استعادة [{$count}] إشعار بنجاح.", ['restored_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع للإشعارات من قاعدة البيانات.
     */
    #[OA\Post(
        path: '/api/v1/notifications/batch-force-delete',
        summary: 'الحذف النهائي المجمع للإشعارات',
        
        tags: ['Notifications'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids'],
                    properties: [
                    new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي للإشعارات بنجاح'),
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->notificationService->bulkForceDelete($request);

            return $this->successResponse("تم الحذف النهائي لـ [{$count}] إشعار بنجاح.", ['force_deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تمييز إشعار محدد كمقروء للمستخدم الحالي.
     */
    #[OA\Post(
        path: '/api/v1/notifications/{id}/read',
        summary: 'تمييز إشعار كمقروء',
        
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تمييز الإشعار كمقروء بنجاح'),
        ]
    )]
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $userId = (int) $request->user()->user_id;
            $this->notificationService->markAsRead($id, $userId);

            return $this->successResponse('تم تمييز الإشعار كمقروء بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تمييز الإشعار كمقروء: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تمييز كافة إشعارات المستخدم الحالي كمقروءة دفعة واحدة.
     */
    #[OA\Post(
        path: '/api/v1/notifications/mark-all-read',
        summary: 'تمييز كافة الإشعارات كمقروءة',
        
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'تم تمييز جميع الإشعارات كمقروءة بنجاح'),
        ]
    )]
    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()->user_id;
            $count = $this->notificationService->markAllAsRead($userId);

            return $this->successResponse("تم تمييز [{$count}] إشعار كمقروء بنجاح.", ['marked_read_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تمييز الإشعارات كمقروءة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تسجيل أو تحديث رمز الإشعارات الفورية (FCM Token) للجهاز الحالي.
     */
    #[OA\Post(
        path: '/api/v1/notifications/fcm-token',
        summary: 'تسجيل أو تحديث رمز إشعارات الدفع (FCM Token)',
        security: [['sessionAuth' => [], 'deviceAuth' => []]],
        tags: ['Notifications'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الجهاز ورمز FCM',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['device_uuid', 'fcm_token'],
                    properties: [
                        new OA\Property(property: 'device_uuid', type: 'string', example: 'uuid-12345'),
                        new OA\Property(property: 'fcm_token', type: 'string', example: 'fcm-token-xyz...'),
                        new OA\Property(property: 'device_name', type: 'string', example: 'Samsung S24 Ultra'),
                        new OA\Property(property: 'device_type', type: 'string', example: 'MOBILE'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث رمز الإشعارات الفورية بنجاح'),
            new OA\Response(response: 422, description: 'فشل في التحقق من البيانات'),
        ]
    )]
    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()->user_id;
            $this->notificationService->updateFcmToken($request, $userId);

            return $this->successResponse('تم تسجيل رمز الإشعارات الفورية (FCM Token) للجهاز بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث رمز الإشعارات الفورية: '.$e->getMessage(), [], 422);
        }
    }
}
