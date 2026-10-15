<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Notification\SendAdminNotificationRequest;
use App\Services\NotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إشعارات ورسائل الإدارة المركزية (Admin Notification Controller).
 */
#[OA\Tag(name: 'Admin Notifications', description: 'إرسال وتوزيع إشعارات الإدارة المتعددة القنوات (داخل التطبيق، SMS، البريد)')]
class AdminNotificationController extends BaseController
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * إرسال إشعار لجمهور متعدد عبر قنوات مختارة (داخل التطبيق / SMS / بريد).
     */
    #[OA\Post(
        path: '/api/v1/admin/notifications/send',
        summary: 'إرسال إشعار إداري للجمهور عبر القنوات المحددة',
        security: [['sessionAuth' => [], 'deviceAuth' => []]],
        tags: ['Admin Notifications'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['audience', 'audience.type', 'channels', 'title', 'body'],
                    properties: [
                    new OA\Property(property: 'audience[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    new OA\Property(property: 'audience.type', type: 'string'),
                    new OA\Property(property: 'audience.role_id', type: 'integer', example: 1),
                    new OA\Property(property: 'audience.network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'audience.user_ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    new OA\Property(property: 'channels[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    new OA\Property(property: 'category', type: 'string'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'body', type: 'string'),
                    new OA\Property(property: 'image', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم إرسال وتوزيع الإشعار بنجاح'),
            new OA\Response(response: 422, description: 'فشل في إرسال الإشعار'),
        ]
    )]
    public function send(SendAdminNotificationRequest $request): JsonResponse
    {
        try {
            $result = $this->notificationService->sendToAudience($request);

            return $this->successResponse('تم إرسال وتوزيع الإشعار بنجاح.', [
                'recipients_count' => $result['recipients_count'],
                'channels' => $result['channels'],
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('خطأ أثناء محاولة إرسال الإشعار: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إرسال رسالة نصية SMS فورية لمستخدم محدد عبر بوابة SMSGate.
     */
    #[OA\Post(
        path: '/api/v1/admin/notifications/user',
        summary: 'إرسال رسالة SMS فورية لمستخدم معين',
        security: [['sessionAuth' => [], 'deviceAuth' => []]],
        tags: ['Admin Notifications'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات إرسال رسالة SMS للمستخدم',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['user_id', 'title', 'body'],
                    properties: [
                        new OA\Property(property: 'user_id', type: 'integer', example: 1),
                        new OA\Property(property: 'title', type: 'string', example: 'تنبيه أمني'),
                        new OA\Property(property: 'body', type: 'string', example: 'تم تسجيل دخول جديد إلى حسابك'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم إرسال الرسالة النصية بنجاح'),
            new OA\Response(response: 422, description: 'فشل في إرسال الرسالة النصية'),
        ]
    )]
    public function sendToUser(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,user_id'],
                'title' => ['required', 'string', 'max:150'],
                'body' => ['required', 'string', 'max:1000'],
            ]);

            $sent = $this->notificationService->sendSmsToUser(
                userId: (int) $request->input('user_id'),
                title: (string) $request->input('title'),
                body: (string) $request->input('body')
            );

            if ($sent) {
                return $this->successResponse('تم إرسال الرسالة النصية للمستخدم بنجاح.');
            }

            return $this->errorResponse('تعذر إرسال الرسالة النصية أو تم حظرها بسياسة النظام.', [], 422);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('خطأ أثناء إرسال الرسالة النصية للمستخدم: '.$e->getMessage(), [], 422);
        }
    }
}
