<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Ticket\StoreSupportTicketMessageRequest;
use App\Http\Requests\API\V1\Ticket\StoreSupportTicketRequest;
use App\Http\Requests\API\V1\Ticket\UpdateSupportTicketRequest;
use App\Http\Resources\API\V1\Ticket\SupportTicketMessageResource;
use App\Http\Resources\API\V1\Ticket\SupportTicketResource;
use App\Services\SupportTicketService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة تذاكر الدعم الفني والمراسلات (Support Ticket Controller).
 *
 * متحكم نحيف صارم (Strict Lean Controller):
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة.
 * 3. حظر فتح أي معاملات DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Support Tickets', description: 'إدارة تذاكر الدعم الفني والمراسلات التفاعلية')]
class SupportTicketController extends BaseController
{
    public function __construct(
        protected SupportTicketService $ticketService
    ) {}

    /**
     * عرض قائمة تذاكر الدعم الفني مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/support-tickets',
        summary: 'عرض قائمة التذاكر',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة التذاكر بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'pos_id', 'search', 'per_page', 'all']);
            $tickets = $this->ticketService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة تذاكر الدعم الفني بنجاح.', SupportTicketResource::collection($tickets));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة التذاكر: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة التذاكر المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/support-tickets/trashed',
        summary: 'عرض التذاكر المحذوفة',
        
        tags: ['Support Tickets'],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع التذاكر المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'pos_id', 'per_page', 'all']);
            $tickets = $this->ticketService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع التذاكر المحذوفة بنجاح.', SupportTicketResource::collection($tickets));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب التذاكر المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء تذكرة دعم فني جديدة.
     */
    #[OA\Post(
        path: '/api/v1/support-tickets',
        summary: 'إنشاء تذكرة دعم فني جديدة',
        
        tags: ['Support Tickets'],
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء التذكرة بنجاح'),
        ]
    )]
    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()->user_id;
            $ticket = $this->ticketService->create($request, $userId);

            return $this->successResponse('تم إنشاء تذكرة الدعم الفني بنجاح.', new SupportTicketResource($ticket), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء تذكرة الدعم الفني: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل تذكرة محددة عبر معرفها الرقمي.
     */
    #[OA\Get(
        path: '/api/v1/support-tickets/{id}',
        summary: 'عرض تفاصيل تذكرة',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل التذكرة بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $ticket = $this->ticketService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل تذكرة الدعم الفني بنجاح.', new SupportTicketResource($ticket));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب تفاصيل التذكرة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات تذكرة قائمة.
     */
    #[OA\Put(
        path: '/api/v1/support-tickets/{id}',
        summary: 'تحديث بيانات تذكرة',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث التذكرة بنجاح'),
        ]
    )]
    public function update(UpdateSupportTicketRequest $request, int $id): JsonResponse
    {
        try {
            $ticket = $this->ticketService->update($request, $id);

            return $this->successResponse('تم تحديث بيانات تذكرة الدعم الفني بنجاح.', new SupportTicketResource($ticket));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث بيانات التذكرة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف تذكرة ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/support-tickets/{id}',
        summary: 'حذف تذكرة ناعماً',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف التذكرة بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->ticketService->delete($id);

            return $this->successResponse('تم حذف تذكرة الدعم الفني بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف التذكرة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة تذكرة محذوفة ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/support-tickets/{id}/restore',
        summary: 'استعادة تذكرة محذوفة',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة التذكرة بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $ticket = $this->ticketService->restore($id);

            return $this->successResponse('تم استعادة تذكرة الدعم الفني بنجاح.', new SupportTicketResource($ticket));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة التذكرة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لتذكرة من قاعدة البيانات.
     */
    #[OA\Delete(
        path: '/api/v1/support-tickets/{id}/force',
        summary: 'الحذف النهائي لتذكرة',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي للتذكرة بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->ticketService->forceDelete($id);

            return $this->successResponse('تم الحذف النهائي لتذكرة الدعم الفني بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للتذكرة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع للتذاكر ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/support-tickets/batch-delete',
        summary: 'الحذف المجمع للتذاكر',
        
        tags: ['Support Tickets'],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف المجمع للتذاكر بنجاح'),
        ]
    )]
    public function batchDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->ticketService->bulkDelete($request);

            return $this->successResponse("تم حذف [{$count}] تذكرة بنجاح.", ['deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة للتذاكر المحذوفة ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/support-tickets/batch-restore',
        summary: 'الاستعادة المجمعة للتذاكر',
        
        tags: ['Support Tickets'],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة التذاكر المحددة بنجاح'),
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->ticketService->bulkRestore($request);

            return $this->successResponse("تم استعادة [{$count}] تذكرة بنجاح.", ['restored_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع للتذاكر.
     */
    #[OA\Post(
        path: '/api/v1/support-tickets/batch-force-delete',
        summary: 'الحذف النهائي المجمع للتذاكر',
        
        tags: ['Support Tickets'],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي للتذاكر بنجاح'),
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->ticketService->bulkForceDelete($request);

            return $this->successResponse("تم الحذف النهائي لـ [{$count}] تذكرة بنجاح.", ['force_deleted_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة رسائل تذكرة معينة مرتبة زمنياً.
     */
    #[OA\Get(
        path: '/api/v1/support-tickets/{id}/messages',
        summary: 'عرض رسائل التذكرة',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع رسائل التذكرة بنجاح'),
        ]
    )]
    public function messages(int $id): JsonResponse
    {
        try {
            $messages = $this->ticketService->getMessages($id);

            return $this->successResponse('تم استرجاع رسائل التذكرة بنجاح.', SupportTicketMessageResource::collection($messages));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استرجاع رسائل التذكرة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إضافة رد / رسالة جديدة إلى تذكرة قائمة.
     */
    #[OA\Post(
        path: '/api/v1/support-tickets/{id}/messages',
        summary: 'إرسال رسالة في التذكرة',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'تم إرسال الرسالة بنجاح'),
        ]
    )]
    public function addMessage(StoreSupportTicketMessageRequest $request, int $id): JsonResponse
    {
        try {
            $userId = (int) $request->user()->user_id;
            $message = $this->ticketService->addMessage($request, $id, $userId);

            return $this->successResponse('تم إرسال الرسالة بنجاح.', new SupportTicketMessageResource($message), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إرسال الرسالة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تمييز رسائل التذكرة كمقروءة للمستخدم الحالي.
     */
    #[OA\Post(
        path: '/api/v1/support-tickets/{id}/read',
        summary: 'تمييز رسائل التذكرة كمقروءة',
        
        tags: ['Support Tickets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تمييز الرسائل كمقروءة بنجاح'),
        ]
    )]
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $userId = (int) $request->user()->user_id;
            $count = $this->ticketService->markAsRead($id, $userId);

            return $this->successResponse("تم تمييز [{$count}] رسالة كمقروءة.", ['marked_read_count' => $count]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تمييز الرسائل كمقروءة: '.$e->getMessage(), [], 422);
        }
    }
}
