<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\BankAccount\StoreBankAccountRequest;
use App\Http\Requests\API\V1\BankAccount\UpdateBankAccountRequest;
use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Resources\API\V1\BankAccount\BankAccountResource;
use App\Services\BankAccountService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة الحسابات البنكية والمحافظ الإلكترونية للنظام (Bank Account Controller).
 *
 * متحكم نحيف (Strict Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. منع استدعاء $request->validated() نهائياً.
 * 2. تغليف كافة الدوال بكتل try-catch موحدة.
 * 3. عدم فتح أي معاملات DB::transaction داخل الكونترولر.
 */
#[OA\Tag(name: 'Bank Accounts', description: 'إدارة الحسابات البنكية والمحافظ المعتمدة للنظام')]
class BankAccountController extends BaseController
{
    public function __construct(
        protected BankAccountService $bankAccountService
    ) {}

    /**
     * عرض قائمة الحسابات البنكية مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/bank-accounts',
        summary: 'عرض قائمة الحسابات البنكية',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'account_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'account_currency', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الحسابات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'account_type', 'account_currency', 'search', 'per_page', 'all']);
            $accounts = $this->bankAccountService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة الحسابات البنكية بنجاح.', BankAccountResource::collection($accounts));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الحسابات البنكية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض الحسابات البنكية النشطة المتاحة للسداد والتحويل.
     */
    #[OA\Get(
        path: '/api/v1/bank-accounts/active',
        summary: 'عرض الحسابات البنكية النشطة',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الحسابات النشطة بنجاح'),
        ]
    )]
    public function active(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['account_currency', 'account_type']);
            $accounts = $this->bankAccountService->getActiveAccounts($filters);

            return $this->successResponse('تم استرجاع الحسابات البنكية النشطة بنجاح.', BankAccountResource::collection($accounts));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الحسابات النشطة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل حساب بنكي محدد.
     */
    #[OA\Get(
        path: '/api/v1/bank-accounts/{id}',
        summary: 'عرض تفاصيل حساب بنكي',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الحساب بنجاح'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $account = $this->bankAccountService->getById($id);

            return $this->successResponse('تم استرجاع تفاصيل الحساب البنكي بنجاح.', new BankAccountResource($account));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات الحساب البنكي: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء حساب بنكي جديد مع رفع الشعار.
     */
    #[OA\Post(
        path: '/api/v1/bank-accounts',
        summary: 'إنشاء حساب بنكي جديد',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الحساب بنجاح'),
        ]
    )]
    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        try {
            $account = $this->bankAccountService->create($request);

            return $this->successResponse('تم إنشاء الحساب البنكي بنجاح.', new BankAccountResource($account), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء الحساب البنكي: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات حساب بنكي قائم واستبدال الشعار.
     */
    #[OA\Put(
        path: '/api/v1/bank-accounts/{id}',
        summary: 'تحديث بيانات حساب بنكي',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث الحساب بنجاح'),
        ]
    )]
    public function update(UpdateBankAccountRequest $request, int $id): JsonResponse
    {
        try {
            $account = $this->bankAccountService->update($id, $request);

            return $this->successResponse('تم تحديث بيانات الحساب البنكي بنجاح.', new BankAccountResource($account));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث الحساب البنكي: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تبديل حالة تفعيل الحساب البنكي (نشط / معطل).
     */
    #[OA\Post(
        path: '/api/v1/bank-accounts/{id}/toggle-status',
        summary: 'تبديل حالة تفعيل الحساب البنكي',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تبديل حالة الحساب بنجاح'),
        ]
    )]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $newStatus = $this->bankAccountService->toggleStatus($id);

            return $this->successResponse('تم تبديل حالة الحساب البنكي بنجاح.', ['status' => $newStatus]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تبديل حالة الحساب البنكي: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف حساب بنكي ناعماً.
     */
    #[OA\Delete(
        path: '/api/v1/bank-accounts/{id}',
        summary: 'حذف حساب بنكي ناعماً',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الحساب بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->bankAccountService->delete($id);

            return $this->successResponse('تم حذف الحساب البنكي مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف الحساب البنكي: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة الحسابات البنكية المحذوفة ناعماً.
     */
    #[OA\Get(
        path: '/api/v1/bank-accounts/trashed',
        summary: 'عرض الحسابات البنكية المحذوفة ناعماً',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الحسابات المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'per_page', 'all']);
            $accounts = $this->bankAccountService->getTrashedFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة الحسابات البنكية المحذوفة بنجاح.', BankAccountResource::collection($accounts));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الحسابات المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة حساب بنكي محذوف ناعماً.
     */
    #[OA\Post(
        path: '/api/v1/bank-accounts/{id}/restore',
        summary: 'استعادة حساب بنكي محذوف',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة الحساب بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->bankAccountService->restore($id);

            return $this->successResponse('تم استعادة الحساب البنكي بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة الحساب البنكي: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي للحساب البنكي مع تنظيف الشعار.
     */
    #[OA\Delete(
        path: '/api/v1/bank-accounts/{id}/force',
        summary: 'حذف نهائي للحساب البنكي',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الحساب نهائياً بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->bankAccountService->forceDelete($id);

            return $this->successResponse('تم حذف الحساب البنكي نهائياً وتنظيف شعاره بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للحساب البنكي: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع ناعم للحسابات.
     */
    #[OA\Post(
        path: '/api/v1/bank-accounts/batch-delete',
        summary: 'حذف مجمع للحسابات',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف المجمع بنجاح'),
        ]
    )]
    public function batchDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->bankAccountService->batchDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) حساب بنكي بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع للحسابات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة للحسابات.
     */
    #[OA\Post(
        path: '/api/v1/bank-accounts/batch-restore',
        summary: 'استعادة مجمعة للحسابات',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        responses: [
            new OA\Response(response: 200, description: 'تمت الاستعادة المجمعة بنجاح'),
        ]
    )]
    public function batchRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->bankAccountService->batchRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) حساب بنكي بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة للحسابات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي مجمع للحسابات.
     */
    #[OA\Post(
        path: '/api/v1/bank-accounts/batch-force-delete',
        summary: 'حذف نهائي مجمع للحسابات',
        security: [['SessionAuth' => []]],
        tags: ['Bank Accounts'],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي المجمع بنجاح'),
        ]
    )]
    public function batchForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->bankAccountService->batchForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) حساب بنكي وتنظيف شعاراتهم بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع للحسابات: '.$e->getMessage(), [], 422);
        }
    }
}
