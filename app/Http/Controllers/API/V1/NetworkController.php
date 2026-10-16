<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Common\RejectRequest;
use App\Http\Requests\API\V1\Network\StoreNetworkRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkRequest;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Services\NetworkService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة مزودي الشبكات والاعتمادات (Enterprise Network Controller).
 *
 * متحكم نحيف (Lean Controller) يقتصر دوره حصراً على:
 * 1. استقبال الـ FormRequest المحقق وتمريره إلى NetworkService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد NetworkResource.
 * 3. خالٍ تماماً من الاستعلامات المباشرة، شروط الأعمال، أو DB::transaction.
 */
#[OA\Tag(name: 'Networks', description: 'إدارة مزودي الشبكات، مراجعة واعتماد الطلبات، والعمليات الإدارية')]
class NetworkController extends BaseController
{
    public function __construct(
        protected NetworkService $networkService,
    ) {}

    /**
     * عرض قائمة مزودي الشبكات مع الفلترة والترقيم بالمؤشر.
     */
    #[OA\Get(
        path: '/api/v1/networks',
        summary: 'عرض قائمة مزودي الشبكات',
        description: 'استرجاع قائمة الشبكات مع دعم الفلترة حسب الحالة، المحافظة، البحث، والترقيم.',
        tags: ['Networks'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ACTIVE', 'PENDING', 'SUSPENDED', 'REJECTED'])),
            new OA\Parameter(name: 'governorate_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الشبكات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'status', 'governorate_id', 'per_page']);
            $networks = $this->networkService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة الشبكات بنجاح.', NetworkResource::collection($networks));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الشبكات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل شبكة محددة.
     */
    #[OA\Get(
        path: '/api/v1/networks/{id}',
        summary: 'عرض تفاصيل شبكة',
        description: 'جلب بيانات مزود الشبكة الكاملة مع المحافظة والمديرية والمحفظة.',
        tags: ['Networks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع بيانات الشبكة بنجاح'),
            new OA\Response(response: 404, description: 'الشبكة غير موجودة'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $network = $this->networkService->findById($id);
            if (! $network) {
                return $this->errorResponse('الشبكة غير موجودة في النظام.', [], 404);
            }

            return $this->successResponse('تم جلب بيانات الشبكة بنجاح.', new NetworkResource($network));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب بيانات الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء شبكة جديدة إدارياً.
     */
    #[OA\Post(
        path: '/api/v1/networks',
        summary: 'إضافة شبكة جديدة إدارياً',
        tags: ['Networks'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_name', 'governorate_id', 'directorate_id'],
                    properties: [
                    new OA\Property(property: 'network_name', type: 'string'),
                    new OA\Property(property: 'governorate_id', type: 'integer', example: 1),
                    new OA\Property(property: 'directorate_id', type: 'integer', example: 1),
                    new OA\Property(property: 'network_address', type: 'string'),
                    new OA\Property(property: 'network_logo', type: 'string', format: 'binary'),
                    new OA\Property(property: 'status', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الشبكة بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function store(StoreNetworkRequest $request): JsonResponse
    {
        try {
            $network = $this->networkService->create($request);

            return $this->successResponse('تم إنشاء الشبكة بنجاح.', new NetworkResource($network), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل إنشاء الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث بيانات شبكة قائمة.
     */
    #[OA\Put(
        path: '/api/v1/networks/{id}',
        summary: 'تعديل بيانات شبكة',
        tags: ['Networks'],
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
                    new OA\Property(property: 'network_name', type: 'string'),
                    new OA\Property(property: 'governorate_id', type: 'integer', example: 1),
                    new OA\Property(property: 'directorate_id', type: 'integer', example: 1),
                    new OA\Property(property: 'network_address', type: 'string'),
                    new OA\Property(property: 'network_logo', type: 'string', format: 'binary'),
                    new OA\Property(property: 'status', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث الشبكة بنجاح'),
            new OA\Response(response: 404, description: 'الشبكة غير موجودة'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function update(UpdateNetworkRequest $request, int $id): JsonResponse
    {
        try {
            $network = $this->networkService->update($id, $request);

            return $this->successResponse('تم تحديث بيانات الشبكة بنجاح.', new NetworkResource($network));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المؤقت لشبكة.
     */
    #[OA\Delete(
        path: '/api/v1/networks/{id}',
        summary: 'حذف شبكة (Soft Delete)',
        tags: ['Networks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف الشبكة بنجاح'),
            new OA\Response(response: 404, description: 'الشبكة غير موجودة'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->networkService->delete($id);
            if (! $deleted) {
                return $this->errorResponse('الشبكة غير موجودة أو تعذر حذفها.', [], 404);
            }

            return $this->successResponse('تم حذف الشبكة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل حذف الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض سلة محذوفات الشبكات.
     */
    #[OA\Get(
        path: '/api/v1/networks/trashed',
        summary: 'سلة محذوفات الشبكات',
        tags: ['Networks'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع المحذوفات بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $trashed = $this->networkService->getTrashed($perPage);

            return $this->successResponse('تم استرجاع المحذوفات بنجاح.', NetworkResource::collection($trashed));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب سلة المحذوفات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة شبكة محذوفة مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/networks/{id}/restore',
        summary: 'استعادة شبكة محذوفة',
        tags: ['Networks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تمت الاستعادة بنجاح'),
            new OA\Response(response: 404, description: 'الشبكة غير موجودة في المحذوفات'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $restored = $this->networkService->restore($id);
            if (! $restored) {
                return $this->errorResponse('تعذر استعادة الشبكة، قد لا تكون في سلة المحذوفات.', [], 404);
            }

            return $this->successResponse('تمت استعادة الشبكة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل استعادة الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لشبكة من قاعدة البيانات.
     */
    #[OA\Delete(
        path: '/api/v1/networks/{id}/force',
        summary: 'حذف نهائي لشبكة',
        tags: ['Networks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي بنجاح'),
            new OA\Response(response: 404, description: 'الشبكة غير موجودة'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $deleted = $this->networkService->forceDelete($id);
            if (! $deleted) {
                return $this->errorResponse('تعذر الحذف النهائي، الشبكة غير موجودة.', [], 404);
            }

            return $this->successResponse('تم حذف الشبكة نهائياً من النظام.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف النهائي للشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع لعدة شبكات.
     */
    #[OA\Post(
        path: '/api/v1/networks/batch-delete',
        summary: 'حذف مجمع للشبكات (Batch Delete)',
        tags: ['Networks'],
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
            new OA\Response(response: 200, description: 'تم الحذف بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function bulkDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->networkService->bulkDelete($request->getIds());

            return $this->successResponse("تم حذف {$count} من مزودي الشبكات بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف المجمع للشبكات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة مجمعة لعدة شبكات من سلة المحذوفات.
     */
    #[OA\Post(
        path: '/api/v1/networks/batch-restore',
        summary: 'استعادة مجمعة للشبكات (Batch Restore)',
        tags: ['Networks'],
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
            new OA\Response(response: 200, description: 'تمت الاستعادة بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function bulkRestore(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->networkService->bulkRestore($request->getIds());

            return $this->successResponse("تمت استعادة {$count} من مزودي الشبكات بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الاستعادة المجمعة للشبكات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف نهائي مجمع لعدة شبكات.
     */
    #[OA\Post(
        path: '/api/v1/networks/batch-force-delete',
        summary: 'حذف نهائي مجمع للشبكات (Batch Force Delete)',
        tags: ['Networks'],
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
            new OA\Response(response: 200, description: 'تم الحذف النهائي بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function bulkForceDelete(BatchIdsRequest $request): JsonResponse
    {
        try {
            $count = $this->networkService->bulkForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لعدد {$count} من مزودي الشبكات بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف النهائي المجمع للشبكات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * اعتماد والموافقة على طلب مزود شبكة.
     */
    #[OA\Post(
        path: '/api/v1/networks/{id}/approve',
        summary: 'الموافقة على طلب شبكة وتفعيل حسابها',
        tags: ['Networks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم اعتماد الشبكة وتفعيل حسابها بنجاح'),
            new OA\Response(response: 404, description: 'الشبكة غير موجودة'),
            new OA\Response(response: 422, description: 'تعذر الاعتماد'),
        ]
    )]
    public function approve(int $id): JsonResponse
    {
        try {
            $network = $this->networkService->approve($id, (int) $this->currentUserId());

            return $this->successResponse('تمت الموافقة على طلب مزود الشبكة وتفعيل حسابه بنجاح.', new NetworkResource($network));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل اعتماد الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * رفض طلب مزود شبكة مع توثيق السبب وإشعار صاحبه.
     */
    #[OA\Post(
        path: '/api/v1/networks/{id}/reject',
        summary: 'رفض طلب مزود شبكة مع السبب',
        tags: ['Networks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['reason'],
                    properties: [
                    new OA\Property(property: 'reason', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم رفض طلب الشبكة بنجاح'),
            new OA\Response(response: 404, description: 'الشبكة غير موجودة'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function reject(RejectRequest $request, int $id): JsonResponse
    {
        try {
            $network = $this->networkService->reject($id, (int) $this->currentUserId(), $request->getReason());

            return $this->successResponse('تم رفض طلب مزود الشبكة وتوثيق السبب بنجاح.', new NetworkResource($network));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل رفض الشبكة: '.$e->getMessage(), [], 422);
        }
    }
}
