<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\SaleInvoice\StoreSaleInvoiceRequest;
use App\Http\Requests\API\V1\SaleInvoice\UpdateSaleInvoiceRequest;
use App\Http\Resources\API\V1\SaleInvoice\SaleInvoiceResource;
use App\Services\SaleInvoiceService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم فواتير مبيعات الكروت (Enterprise Sale Invoice Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال الـ FormRequest وتمريره مباشرة إلى طبقة الخدمة SaleInvoiceService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد SaleInvoiceResource.
 * 3. خلو كامل من الاستعلامات المباشرة، شروط الأعمال، أو معاملات DB::transaction.
 */
#[OA\Tag(name: 'Sale Invoices', description: 'إدارة فواتير مبيعات كروت الإنترنت، السداد والتحصيل المالي، والعمليات الإدارية والمجمعة')]
class SaleInvoiceController extends BaseController
{
    public function __construct(
        protected SaleInvoiceService $saleInvoiceService,
    ) {}

    /**
     * عرض قائمة فواتير المبيعات مع الفلترة والبحث والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/sale-invoices',
        summary: 'عرض قائمة فواتير مبيعات الكروت',
        tags: ['Sale Invoices'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'invoice_sale_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'created_by', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الفواتير بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'pos_id', 'invoice_sale_type', 'created_by', 'from_date', 'to_date', 'search', 'per_page', 'all']);
            $invoices = $this->saleInvoiceService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة فواتير المبيعات بنجاح.', SaleInvoiceResource::collection($invoices));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة فواتير المبيعات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل فاتورة مبيعات محددة.
     */
    #[OA\Get(
        path: '/api/v1/sale-invoices/{id}',
        summary: 'عرض تفاصيل فاتورة مبيعات محددة',
        tags: ['Sale Invoices'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الفاتورة بنجاح'),
            new OA\Response(response: 404, description: 'الفاتورة غير موجودة'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $record = $this->saleInvoiceService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل الفاتورة بنجاح.', new SaleInvoiceResource($record));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات الفاتورة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء فاتورة مبيعات مباشرة.
     */
    #[OA\Post(
        path: '/api/v1/sale-invoices',
        summary: 'إنشاء فاتورة مبيعات مباشرة',
        tags: ['Sale Invoices'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id', 'pos_id', 'items'],
                    properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'pos_id', type: 'integer', example: 1),
                    new OA\Property(property: 'items[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    new OA\Property(property: 'paid_amount', type: 'number', example: 100.5),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الفاتورة بنجاح'),
        ]
    )]
    public function store(StoreSaleInvoiceRequest $request): JsonResponse
    {
        try {
            $userId = (int) ($request->user()?->user_id ?? 1);
            $record = $this->saleInvoiceService->createDirectInvoice($request, $userId);

            return $this->successResponse('تم إنشاء فاتورة المبيعات بنجاح.', new SaleInvoiceResource($record), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء فاتورة المبيعات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث نوع الفاتورة (كاش أو آجل).
     */
    #[OA\Put(
        path: '/api/v1/sale-invoices/{id}',
        summary: 'تحديث نوع الفاتورة',
        tags: ['Sale Invoices'],
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
                    new OA\Property(property: 'invoice_sale_type', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث نوع الفاتورة بنجاح'),
        ]
    )]
    public function update(UpdateSaleInvoiceRequest $request, int $id): JsonResponse
    {
        try {
            $record = $this->saleInvoiceService->updateSaleType($id, $request);

            return $this->successResponse('تم تحديث نوع الفاتورة بنجاح.', new SaleInvoiceResource($record));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث الفاتورة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف فاتورة مبيعات مؤقتاً (Soft Delete).
     */
    #[OA\Delete(
        path: '/api/v1/sale-invoices/{id}',
        summary: 'حذف فاتورة مبيعات مؤقتاً',
        tags: ['Sale Invoices'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الفاتورة مؤقتاً بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->saleInvoiceService->deleteInvoice($id);

            return $this->successResponse('تم حذف فاتورة المبيعات مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف الفاتورة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة فواتير المبيعات المحذوفة مؤقتاً.
     */
    #[OA\Get(
        path: '/api/v1/sale-invoices/trashed',
        summary: 'عرض فواتير المبيعات المحذوفة مؤقتاً',
        tags: ['Sale Invoices'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الفواتير المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'pos_id', 'search', 'per_page', 'all']);
            $records = $this->saleInvoiceService->getTrashed($filters);

            return $this->successResponse('تم استرجاع قائمة فواتير المبيعات المحذوفة بنجاح.', SaleInvoiceResource::collection($records));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الفواتير المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة فاتورة مبيعات محذوفة مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/sale-invoices/{id}/restore',
        summary: 'استعادة فاتورة مبيعات محذوفة مؤقتاً',
        tags: ['Sale Invoices'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الفاتورة بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->saleInvoiceService->restoreInvoice($id);

            return $this->successResponse('تم استعادة فاتورة المبيعات بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة الفاتورة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لفاتورة مبيعات.
     */
    #[OA\Delete(
        path: '/api/v1/sale-invoices/{id}/force',
        summary: 'الحذف النهائي لفاتورة مبيعات',
        tags: ['Sale Invoices'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي للفاتورة بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->saleInvoiceService->forceDeleteInvoice($id);

            return $this->successResponse('تم حذف فاتورة المبيعات نهائياً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للفاتورة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع لفواتير المبيعات.
     */
    #[OA\Post(
        path: '/api/v1/sale-invoices/batch-delete',
        summary: 'حذف مجمع لفواتير المبيعات',
        tags: ['Sale Invoices'],
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
            $count = $this->saleInvoiceService->bulkDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) فاتورة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة لفواتير المبيعات.
     */
    #[OA\Post(
        path: '/api/v1/sale-invoices/batch-restore',
        summary: 'استعادة مجمعة لفواتير المبيعات',
        tags: ['Sale Invoices'],
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
            $count = $this->saleInvoiceService->bulkRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) فاتورة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع لفواتير المبيعات.
     */
    #[OA\Post(
        path: '/api/v1/sale-invoices/batch-force-delete',
        summary: 'حذف نهائي مجمع لفواتير المبيعات',
        tags: ['Sale Invoices'],
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
            $count = $this->saleInvoiceService->bulkForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) فاتورة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع: '.$e->getMessage(), [], 422);
        }
    }
}
