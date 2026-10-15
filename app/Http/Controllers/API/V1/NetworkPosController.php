<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Common\BatchStatusRequest;
use App\Http\Requests\API\V1\Network\StoreNetworkPosRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkPosRequest;
use App\Http\Resources\API\V1\Network\NetworkPosResource;
use App\Services\NetworkPosService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة ربط نقاط البيع بالشبكات (Network POS Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال الـ FormRequest وتمريره مباشرة إلى NetworkPosService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد NetworkPosResource.
 * 3. خالٍ تماماً من أي استعلامات أو DB::transaction أو استدعاء لـ $request->validated().
 */
#[OA\Tag(name: 'Network POS', description: 'إدارة وتتبع ارتباط نقاط البيع بمزودي الشبكات، تفعيلها والعمليات المجمعة')]
class NetworkPosController extends BaseController
{
    public function __construct(
        protected NetworkPosService $networkPosService,
    ) {}

    /**
     * عرض قائمة ارتباطات نقاط البيع بالشبكات مع الفلترة والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/network-pos',
        summary: 'عرض ارتباطات نقاط البيع بالشبكات',
        description: 'استرجاع قائمة ارتباطات نقاط البيع بمزودي الشبكات مع دعم الفلترة حسب الشبكة، نقطة البيع، الحالة، والبحث.',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الارتباطات بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'pos_id', 'status', 'search', 'per_page', 'all']);
            $links = $this->networkPosService->getFiltered($filters);

            return $this->successResponse('تم استرجاع قائمة ارتباطات نقاط البيع بالشبكات بنجاح.', NetworkPosResource::collection($links));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة الارتباطات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل ارتباط محدد.
     */
    #[OA\Get(
        path: '/api/v1/network-pos/{id}',
        summary: 'عرض تفاصيل ارتباط نقطة بيع بشبكة',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل الارتباط بنجاح'),
            new OA\Response(response: 404, description: 'سجل الارتباط غير موجود'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $link = $this->networkPosService->findById($id);
            if (! $link) {
                return $this->errorResponse('سجل ارتباط نقطة البيع بالشبكة غير موجود.', [], 404);
            }

            return $this->successResponse('تم جلب تفاصيل الارتباط بنجاح.', new NetworkPosResource($link));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب تفاصيل الارتباط: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء ارتباط جديد بين نقطة بيع وشبكة.
     */
    #[OA\Post(
        path: '/api/v1/network-pos',
        summary: 'ربط نقطة بيع بمزود شبكة',
        tags: ['Network POS'],
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
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء الارتباط بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة أو الارتباط موجود مسبقاً'),
        ]
    )]
    public function store(StoreNetworkPosRequest $request): JsonResponse
    {
        try {
            $link = $this->networkPosService->create($request);

            return $this->successResponse('تم ربط نقطة البيع بالشبكة بنجاح.', new NetworkPosResource($link), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل ربط نقطة البيع بالشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث حالة ارتباط نقطة بيع بشبكة.
     */
    #[OA\Put(
        path: '/api/v1/network-pos/{id}',
        summary: 'تعديل حالة ارتباط نقطة بيع بشبكة',
        tags: ['Network POS'],
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
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث حالة الارتباط بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function update(UpdateNetworkPosRequest $request, int $id): JsonResponse
    {
        try {
            $link = $this->networkPosService->update($id, $request);

            return $this->successResponse('تم تحديث حالة الارتباط بنجاح.', new NetworkPosResource($link));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث حالة الارتباط: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تبديل حالة الارتباط (تفعيل / إلغاء تفعيل).
     */
    #[OA\Post(
        path: '/api/v1/network-pos/{id}/toggle-status',
        summary: 'تبديل حالة ارتباط نقطة بيع بشبكة',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم تبديل حالة الارتباط بنجاح'),
            new OA\Response(response: 422, description: 'سجل الارتباط غير موجود'),
        ]
    )]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $link = $this->networkPosService->toggleStatus($id);

            return $this->successResponse('تم تبديل حالة الارتباط بنجاح.', new NetworkPosResource($link));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تبديل حالة الارتباط: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف أو فك ارتباط نقطة بيع بشبكة.
     */
    #[OA\Delete(
        path: '/api/v1/network-pos/{id}',
        summary: 'فك ارتباط نقطة بيع بشبكة',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم فك الارتباط بنجاح'),
            new OA\Response(response: 404, description: 'سجل الارتباط غير موجود'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->networkPosService->delete($id);
            if (! $deleted) {
                return $this->errorResponse('سجل ارتباط نقطة البيع بالشبكة غير موجود أو تعذر حذفه.', [], 404);
            }

            return $this->successResponse('تم فك ارتباط نقطة البيع بالشبكة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل فك الارتباط: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف مجمع لسجلات الارتباط بين الشبكات ونقاط البيع.
     */
    #[OA\Post(
        path: '/api/v1/network-pos/batch-delete',
        summary: 'فك ارتباط مجمع لنقاط البيع بالشبكات',
        tags: ['Network POS'],
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
            $ids = $request->getIds();
            $count = $this->networkPosService->bulkDelete($ids);

            return $this->successResponse("تم فك ارتباط {$count} من نقاط البيع بالشبكات بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل الحذف المجمع للارتباطات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تغيير حالة مجموعة ارتباطات مجمعة (تفعيل أو إلغاء تفعيل مجمع).
     */
    #[OA\Post(
        path: '/api/v1/network-pos/batch-status',
        summary: 'تغيير حالة مجمعة لارتباطات نقاط البيع بالشبكات',
        tags: ['Network POS'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['ids', 'status'],
                    properties: [
                    new OA\Property(property: 'ids[]', type: 'array', items: new OA\Items(type: 'integer', example: 1)),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحديث الحالة بنجاح'),
        ]
    )]
    public function batchStatus(BatchStatusRequest $request): JsonResponse
    {
        try {
            $ids = $request->getIds();
            $status = $request->getStatus();
            $count = $this->networkPosService->bulkToggleStatus($ids, $status);

            $statusText = $status ? 'تفعيل' : 'تعطيل';

            return $this->successResponse("تم {$statusText} عدد {$count} من ارتباطات نقاط البيع بالشبكات بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تحديث الحالة المجمعة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض نقاط البيع المرتبطة بشبكة محددة.
     */
    #[OA\Get(
        path: '/api/v1/networks/{id}/pos',
        summary: 'عرض نقاط البيع المرتبطة بشبكة محددة',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة نقاط البيع المرتبطة بالشبكة بنجاح'),
        ]
    )]
    public function networkPosList(int $id, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'per_page', 'all']);
            $list = $this->networkPosService->getPosForNetwork($id, $filters);

            return $this->successResponse('تم استرجاع قائمة نقاط البيع المرتبطة بالشبكة بنجاح.', NetworkPosResource::collection($list));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب نقاط البيع المرتبطة بالشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض الشبكات المرتبطة بنقطة بيع محددة.
     */
    #[OA\Get(
        path: '/api/v1/pos/{id}/networks',
        summary: 'عرض الشبكات المرتبطة بنقطة بيع محددة',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الشبكات المرتبطة بنقطة البيع بنجاح'),
        ]
    )]
    public function posNetworksList(int $id, Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'per_page', 'all']);
            $list = $this->networkPosService->getNetworksForPos($id, $filters);

            return $this->successResponse('تم استرجاع قائمة الشبكات المرتبطة بنقطة البيع بنجاح.', NetworkPosResource::collection($list));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الشبكات المرتبطة بنقطة البيع: '.$e->getMessage(), [], 422);
        }
    }
}
