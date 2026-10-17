<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Expense\StoreExpenseItemRequest;
use App\Http\Requests\API\V1\Expense\StoreExpenseRequest;
use App\Http\Requests\API\V1\Expense\UpdateExpenseRequest;
use App\Http\Resources\API\V1\Expense\ExpenseItemResource;
use App\Http\Resources\API\V1\Expense\ExpenseResource;
use App\Services\ExpenseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة المصروفات التشغيلية والبنود والمرفقات والتسوية المالية (Expense Controller).
 *
 * متحكم نحيف (Strict Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة.
 * 3. عدم فتح أي معاملات DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Expenses', description: 'إدارة المصروفات التشغيلية للشبكات، البنود والمرفقات، والخصم الإلزامي من المحفظة المالية')]
class ExpenseController extends BaseController
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    /**
     * عرض قائمة المصروفات مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/expenses',
        summary: 'عرض قائمة المصروفات التشغيلية',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم جلب المصروفات بنجاح'),
            new OA\Response(response: 401, description: 'غير مصرح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'created_by', 'from_date', 'to_date', 'search', 'per_page', 'all']);
            $expenses = $this->expenseService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة المصروفات التشغيلية بنجاح.', ExpenseResource::collection($expenses));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة المصروفات التشغيلية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل مصروف تشغيلي محدد مع بنوده ومرفقاته.
     */
    #[OA\Get(
        path: '/api/v1/expenses/{id}',
        summary: 'عرض تفاصيل مصروف محدد',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل المصروف بنجاح'),
            new OA\Response(response: 404, description: 'المصروف غير موجود'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $expense = $this->expenseService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل المصروف بنجاح.', new ExpenseResource($expense));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات المصروف: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تسجيل مصروف تشغيلي جديد مع البنود والمرفقات وخصم إلزامي من محفظة الشبكة.
     */
    #[OA\Post(
        path: '/api/v1/expenses',
        summary: 'تسجيل مصروف تشغيلي جديد',
        
        tags: ['Expenses'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id', 'items'],
                    properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'expense_date', type: 'string', format: 'date', example: '2026-01-01'),
                    new OA\Property(property: 'items[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    new OA\Property(property: 'attachments[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم تسجيل المصروف والخصم من المحفظة بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $expense = $this->expenseService->create($request, $userId);

            return $this->successResponse('تم تسجيل المصروف وخصم المبلغ من محفظة الشبكة بنجاح.', new ExpenseResource($expense), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تسجيل المصروف التشغيلي: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات المصروف وبنوده ومرفقاته.
     */
    #[OA\Put(
        path: '/api/v1/expenses/{id}',
        summary: 'تحديث بيانات مصروف قائم',
        
        tags: ['Expenses'],
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
                    new OA\Property(property: 'expense_date', type: 'string', format: 'date', example: '2026-01-01'),
                    new OA\Property(property: 'items[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    new OA\Property(property: 'attachments[]', type: 'array', items: new OA\Items(type: 'string', example: 1)),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث المصروف وتسوية المحفظة بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function update(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $expense = $this->expenseService->update($id, $request, $userId);

            return $this->successResponse('تم تحديث المصروف وتسوية فرق الرصيد بالمحفظة بنجاح.', new ExpenseResource($expense));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث بيانات المصروف: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مصروف تشغيلي ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/expenses/{id}',
        summary: 'حذف مصروف ناعماً',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المصروف بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->expenseService->delete($id);

            return $this->successResponse('تم حذف المصروف مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف المصروف: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة المصروفات المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/expenses/trashed',
        summary: 'عرض المصروفات المحذوفة ناعماً',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'created_by', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع المصروفات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'created_by', 'search', 'per_page', 'all']);
            $expenses = $this->expenseService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة المصروفات المحذوفة بنجاح.', ExpenseResource::collection($expenses));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة المصروفات المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مصروف محذوف ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/expenses/{id}/restore',
        summary: 'استعادة مصروف محذوف',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة المصروف بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->expenseService->restore($id);

            return $this->successResponse('تم استعادة المصروف بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة المصروف: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي للمصروف مع تنظيف المرفقات.
     */
    #[OA\Delete(
        path: '/api/v1/expenses/{id}/force',
        summary: 'حذف نهائي للمصروف',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المصروف نهائياً بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->expenseService->forceDelete($id);

            return $this->successResponse('تم حذف المصروف نهائياً وتنظيف كافة مرفقاته بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للمصروف: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع ناعم للمصروفات.
     */
    #[OA\Post(
        path: '/api/v1/expenses/batch-delete',
        summary: 'حذف مجمع للمصروفات',
        
        tags: ['Expenses'],
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
            $count = $this->expenseService->batchDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) مصروف بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة للمصروفات المحذوفة.
     */
    #[OA\Post(
        path: '/api/v1/expenses/batch-restore',
        summary: 'استعادة مجمعة للمصروفات',
        
        tags: ['Expenses'],
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
            $count = $this->expenseService->batchRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) مصروف بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع نهائي للمصروفات.
     */
    #[OA\Post(
        path: '/api/v1/expenses/batch-force-delete',
        summary: 'حذف نهائي مجمع للمصروفات',
        
        tags: ['Expenses'],
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
            $count = $this->expenseService->batchForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) مصروف وتنظيف مرفقاتهم بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إضافة بند منفرد لمصروف قائم مع خصم المبلغ من المحفظة.
     */
    #[OA\Post(
        path: '/api/v1/expenses/{id}/items',
        summary: 'إضافة بند منفرد للمصروف',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['item_statement', 'item_amount'],
                    properties: [
                    new OA\Property(property: 'item_statement', type: 'string'),
                    new OA\Property(property: 'item_amount', type: 'number', example: 100.5),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تمت إضافة البند وتسوية المحفظة بنجاح'),
        ]
    )]
    public function addItem(StoreExpenseItemRequest $request, int $id): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $item = $this->expenseService->addItem($id, $request, $userId);

            return $this->successResponse('تمت إضافة بند المصروف وخصم المبلغ من محفظة الشبكة بنجاح.', new ExpenseItemResource($item), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إضافة بند المصروف: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف بند منفرد من مصروف قائم مع استرداد المبلغ للمحفظة.
     */
    #[OA\Delete(
        path: '/api/v1/expenses/{id}/items/{itemId}',
        summary: 'حذف بند منفرد من المصروف',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف البند واسترداد مبلغه للمحفظة بنجاح'),
        ]
    )]
    public function deleteItem(int $id, int $itemId, Request $request): JsonResponse
    {
        try {
            $userId = $this->currentUserId();
            $this->expenseService->deleteItem($id, $itemId, $userId);

            return $this->successResponse('تم حذف بند المصروف وإعادة المبلغ لمحفظة الشبكة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف بند المصروف: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مرفق منفرد من المصروف.
     */
    #[OA\Delete(
        path: '/api/v1/expenses/{id}/attachments/{attachmentId}',
        summary: 'حذف مرفق منفرد من المصروف',
        
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'attachmentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المرفق بنجاح'),
        ]
    )]
    public function deleteAttachment(int $id, int $attachmentId): JsonResponse
    {
        try {
            $this->expenseService->deleteAttachment($id, $attachmentId);

            return $this->successResponse('تم حذف المرفق من المصروف بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف مرفق المصروف: '.$e->getMessage(), [], 422);
        }
    }
}
