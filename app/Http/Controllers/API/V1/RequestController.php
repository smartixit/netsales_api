<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Common\RejectRequest;
use App\Http\Requests\API\V1\Request\ApproveRequestRequest;
use App\Http\Requests\API\V1\Request\StoreRequestRequest;
use App\Http\Requests\API\V1\Request\UpdateRequestRequest;
use App\Http\Resources\API\V1\Request\RequestResource;
use App\Http\Resources\API\V1\SaleInvoice\SaleInvoiceResource;
use App\Services\RequestService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة دورة حياة طلبات الكروت (Enterprise Voucher Request Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال كائن الـ FormRequest وتمريره مباشرة إلى طبقة الخدمة RequestService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد RequestResource.
 * 3. خلو كامل من الاستعلامات المباشرة، شروط الأعمال، أو معاملات DB::transaction.
 */
#[OA\Tag(name: 'Voucher Requests', description: 'إدارة طلبات شراء كروت الإنترنت، اعتماد ورفض الطلبات، التسوية التلقائية، والعمليات المجمعة')]
class RequestController extends BaseController
{
    public function __construct(
        protected RequestService $requestService,
    ) {}

    /**
     * عرض قائمة طلبات الكروت مع الفلترة والبحث والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/requests',
        summary: 'عرض قائمة طلبات كروت الإنترنت',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'created_by', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'reviewed_by', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الطلبات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'pos_id', 'status', 'created_by', 'reviewed_by', 'from_date', 'to_date', 'search', 'per_page', 'all']);
            $requests = $this->requestService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة الطلبات بنجاح.', RequestResource::collection($requests));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الطلبات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل طلب محدد.
     */
    #[OA\Get(
        path: '/api/v1/requests/{id}',
        summary: 'عرض تفاصيل طلب محدد',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الطلب بنجاح'),
            new OA\Response(response: 404, description: 'الطلب غير موجود'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $record = $this->requestService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل الطلب بنجاح.', new RequestResource($record));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات الطلب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء وتقديم طلب كروت جديد من نقطة البيع.
     */
    #[OA\Post(
        path: '/api/v1/requests',
        summary: 'تقديم طلب كروت جديد',
        tags: ['Voucher Requests'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id', 'pos_id'],
                    properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'pos_id', type: 'integer', example: 1),
                    new OA\Property(property: 'request_note', type: 'string'),
                    new OA\Property(property: 'status', type: 'string'),
                    new OA\Property(property: 'items[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الطلب بنجاح'),
        ]
    )]
    public function store(StoreRequestRequest $request): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $record = $this->requestService->create($request, $userId);

            return $this->successResponse('تم تقديم طلب الكروت بنجاح وهو قيد الانتظار.', new RequestResource($record), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تقديم طلب الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تعديل طلب الكروت (مسموح فقط في حالة قيد الانتظار).
     */
    #[OA\Put(
        path: '/api/v1/requests/{id}',
        summary: 'تعديل طلب الكروت قيد الانتظار',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تعديل الطلب بنجاح'),
        ]
    )]
    public function update(UpdateRequestRequest $request, int $id): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $record = $this->requestService->update($id, $request, $userId);

            return $this->successResponse('تم تعديل طلب الكروت بنجاح.', new RequestResource($record));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تعديل طلب الكروت: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * مراجعة واعتماد طلب الكروت وتوليد الفاتورة والتسوية المالية اللحظية.
     */
    #[OA\Post(
        path: '/api/v1/requests/{id}/approve',
        summary: 'اعتماد طلب الكروت وتوليد فاتورة المبيعات آلياً',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم اعتماد الطلب وتوليد الفاتورة بنجاح'),
        ]
    )]
    public function approve(ApproveRequestRequest $request, int $id): JsonResponse
    {
        try {
            $reviewedBy = $this->currentUserId();
            $result = $this->requestService->approve($id, $reviewedBy, $request);

            return $this->successResponse('تم اعتماد طلب الكروت وتوليد فاتورة المبيعات وتسوية الرصيد بنجاح.', [
                'request' => new RequestResource($result['request']),
                'invoice' => new SaleInvoiceResource($result['invoice']),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر اعتماد الطلب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * رفض طلب الكروت مع توثيق السبب.
     */
    #[OA\Post(
        path: '/api/v1/requests/{id}/reject',
        summary: 'رفض طلب الكروت',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['rejection_reason'],
                    properties: [
                    new OA\Property(property: 'rejection_reason', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم رفض الطلب بنجاح'),
        ]
    )]
    public function reject(RejectRequest $request, int $id): JsonResponse
    {
        try {
            $reviewedBy = $this->currentUserId();
            $record = $this->requestService->reject($id, $reviewedBy, $request->getReason());

            return $this->successResponse('تم رفض طلب الكروت وتوثيق السبب بنجاح.', new RequestResource($record));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر رفض الطلب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إلغاء طلب الكروت من قبل صاحبه.
     */
    #[OA\Post(
        path: '/api/v1/requests/{id}/cancel',
        summary: 'إلغاء طلب الكروت',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم إلغاء الطلب بنجاح'),
        ]
    )]
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $record = $this->requestService->cancel($id, $userId);

            return $this->successResponse('تم إلغاء طلب الكروت بنجاح.', new RequestResource($record));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إلغاء الطلب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف طلب مؤقتاً (Soft Delete).
     */
    #[OA\Delete(
        path: '/api/v1/requests/{id}',
        summary: 'حذف طلب كروت مؤقتاً',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الطلب مؤقتاً بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->requestService->deleteRequest($id);

            return $this->successResponse('تم حذف طلب الكروت مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف الطلب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة الطلبات المحذوفة مؤقتاً.
     */
    #[OA\Get(
        path: '/api/v1/requests/trashed',
        summary: 'عرض الطلبات المحذوفة مؤقتاً',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الطلبات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'pos_id', 'search', 'per_page', 'all']);
            $records = $this->requestService->getTrashed($filters);

            return $this->successResponse('تم استرجاع قائمة الطلبات المحذوفة بنجاح.', RequestResource::collection($records));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الطلبات المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة طلب محذوف مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/requests/{id}/restore',
        summary: 'استعادة طلب كروت محذوف مؤقتاً',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الطلب بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->requestService->restoreRequest($id);

            return $this->successResponse('تم استعادة طلب الكروت بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة الطلب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لطلب كروت.
     */
    #[OA\Delete(
        path: '/api/v1/requests/{id}/force',
        summary: 'الحذف النهائي لطلب كروت',
        tags: ['Voucher Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي للطلب بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->requestService->forceDeleteRequest($id);

            return $this->successResponse('تم حذف طلب الكروت نهائياً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للطلب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع لطلبات الكروت.
     */
    #[OA\Post(
        path: '/api/v1/requests/batch-delete',
        summary: 'حذف مجمع لطلبات الكروت',
        tags: ['Voucher Requests'],
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
            new OA\Response(response: 200, description: 'تم الحذف المجمع بنجاح'),
        ]
    )]
    public function batchDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->requestService->bulkDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) طلب بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة لطلبات الكروت.
     */
    #[OA\Post(
        path: '/api/v1/requests/batch-restore',
        summary: 'استعادة مجمعة لطلبات الكروت',
        tags: ['Voucher Requests'],
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
            new OA\Response(response: 200, description: 'تمت الاستعادة المجمعة بنجاح'),
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->requestService->bulkRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) طلب بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع لطلبات الكروت.
     */
    #[OA\Post(
        path: '/api/v1/requests/batch-force-delete',
        summary: 'حذف نهائي مجمع لطلبات الكروت',
        tags: ['Voucher Requests'],
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
            new OA\Response(response: 200, description: 'تم الحذف النهائي المجمع بنجاح'),
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->requestService->bulkForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) طلب بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع: '.$e->getMessage(), [], 422);
        }
    }
}
