<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Common\BatchStatusRequest;
use App\Http\Requests\API\V1\Network\ConfirmNetworkPosLinkingChallengeRequest;
use App\Http\Requests\API\V1\Network\CreateNetworkPosLinkingChallengeRequest;
use App\Http\Requests\API\V1\Network\FilterNetworkPosRequest;
use App\Http\Requests\API\V1\Network\PreviewNetworkPosLinkingChallengeRequest;
use App\Http\Requests\API\V1\Network\ResolveNetworkLinkablePosRequest;
use App\Http\Requests\API\V1\Network\SearchNetworkLinkablePosRequest;
use App\Http\Requests\API\V1\Network\StoreNetworkPosRequest;
use App\Http\Requests\API\V1\Network\StoreNetworkPosAgreementRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkPosAgreementRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkPosRequest;
use App\Http\Resources\API\V1\Network\NetworkPosLinkingChallengeResource;
use App\Http\Resources\API\V1\Network\NetworkPosLinkingChallengeStatusResource;
use App\Http\Resources\API\V1\Network\NetworkLinkablePosResource;
use App\Http\Resources\API\V1\Network\NetworkPosLinkingPreviewResource;
use App\Http\Resources\API\V1\Network\NetworkPosResource;
use App\Http\Resources\API\V1\Network\NetworkPosAgreementResource;
use App\Http\Resources\API\V1\Network\NetworkPosEventResource;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\Pos;
use App\Services\NetworkPosLinkingChallengeService;
use App\Services\NetworkPosService;
use App\Services\NetworkPosAgreementService;
use App\Services\TenantPermissionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        protected NetworkPosLinkingChallengeService $challengeService,
        protected TenantPermissionService $tenantPermissions,
        protected NetworkPosAgreementService $agreementService,
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
            new OA\Parameter(name: 'cursor', in: 'query', required: false, description: 'Cursor token returned by the previous page.', schema: new OA\Schema(type: 'string', maxLength: 2048)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الارتباطات بنجاح'),
        ]
    )]
    public function index(FilterNetworkPosRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $user = $this->currentUser();
            if ($user && ! $this->tenantPermissions->allowsPlatform($user, 'networks.view')) {
                $filters['scoped_network_ids'] = $this->tenantPermissions->authorizedNetworkIds($user, 'networks.view');
                $filters['scoped_pos_ids'] = $this->tenantPermissions->authorizedPosIds($user, 'networks.view');
            }
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
            new OA\Response(response: 403, description: 'غير مصرح بالوصول إلى سجل الارتباط'),
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

            $currentUser = $this->currentUser();
            if ($currentUser && ! Gate::forUser($currentUser)->allows('view', $link)) {
                return $this->errorResponse('غير مصرح لك بالوصول إلى سجل الارتباط هذا.', [], 403);
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
        summary: 'إنشاء ارتباط نقطة بيع بمزود شبكة',
        tags: ['Network POS'],
        operationId: 'network_pos_store_link',
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
            new OA\Response(response: 403, description: 'غير مصرح بربط نقاط بيع بهذه الشبكة'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة أو الارتباط موجود مسبقاً'),
        ]
    )]
    public function store(StoreNetworkPosRequest $request): JsonResponse
    {
        try {
            $currentUser = $this->currentUser();
            $networkId = $request->getNetworkId();
            if ($currentUser && ! Gate::forUser($currentUser)->allows('create', [NetworkPos::class, $networkId])) {
                return $this->errorResponse('غير مصرح لك بربط نقاط بيع بهذه الشبكة.', [], 403);
            }

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
            $link = $this->networkPosService->findById($id);
            if (! $link) {
                return $this->errorResponse('سجل ارتباط نقطة البيع بالشبكة غير موجود.', [], 404);
            }

            $currentUser = $this->currentUser();
            if ($currentUser && ! Gate::forUser($currentUser)->allows('update', $link)) {
                return $this->errorResponse('غير مصرح لك بتعديل هذا الارتباط.', [], 403);
            }

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
            new OA\Response(response: 403, description: 'غير مصرح بتعديل حالة هذا الارتباط'),
            new OA\Response(response: 422, description: 'سجل الارتباط غير موجود'),
        ]
    )]
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $link = $this->networkPosService->findById($id);
            if (! $link) {
                return $this->errorResponse('سجل الارتباط غير موجود.', [], 404);
            }

            $currentUser = $this->currentUser();
            if ($currentUser && ! Gate::forUser($currentUser)->allows('toggle', $link)) {
                return $this->errorResponse('غير مصرح لك بتعديل حالة هذا الارتباط.', [], 403);
            }

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
            new OA\Response(response: 403, description: 'غير مصرح بفك هذا الارتباط'),
            new OA\Response(response: 404, description: 'سجل الارتباط غير موجود'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $link = $this->networkPosService->findById($id);
            if (! $link) {
                return $this->errorResponse('سجل ارتباط نقطة البيع بالشبكة غير موجود أو تعذر حذفه.', [], 404);
            }

            $currentUser = $this->currentUser();
            if ($currentUser && ! Gate::forUser($currentUser)->allows('delete', $link)) {
                return $this->errorResponse('غير مصرح لك بفك هذا الارتباط.', [], 403);
            }

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

            $currentUser = $this->currentUser();
            if ($currentUser) {
                $permissionService = app(\App\Services\TenantPermissionService::class);
                $links = NetworkPos::whereIn('network_pos_id', $ids)->get(['network_pos_id', 'network_id']);
                $unauthorized = $ids === [] || $links->count() !== count($ids)
                    || $links->contains(fn (NetworkPos $link): bool =>
                        ! $permissionService->allowsNetwork($currentUser, (int) $link->network_id, 'networks.delete_pos_link'));

                if ($unauthorized) {
                    return $this->errorResponse('غير مصرح لك بفك ارتباطات لا تتبع لشبكاتك.', [], 403);
                }
            }

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

            $currentUser = $this->currentUser();
            if ($currentUser) {
                $permissionService = app(\App\Services\TenantPermissionService::class);
                $links = NetworkPos::whereIn('network_pos_id', $ids)->get(['network_pos_id', 'network_id']);
                $unauthorized = $ids === [] || $links->count() !== count($ids)
                    || $links->contains(fn (NetworkPos $link): bool =>
                        ! $permissionService->allowsNetwork($currentUser, (int) $link->network_id, 'networks.toggle_pos_link'));

                if ($unauthorized) {
                    return $this->errorResponse('غير مصرح لك بتعديل ارتباطات لا تتبع لشبكاتك.', [], 403);
                }
            }

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
    #[OA\Post(
        path: '/api/v1/networks/{id}/pos',
        summary: 'عرض نقاط البيع المرتبطة بشبكة محددة',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 120)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 50)),
            new OA\Parameter(name: 'cursor', in: 'query', required: false, description: 'Cursor token returned by the previous page.', schema: new OA\Schema(type: 'string', maxLength: 2048)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة نقاط البيع المرتبطة بالشبكة بنجاح'),
        ]
    )]
    public function networkPosList(int $id, FilterNetworkPosRequest $request): JsonResponse
    {
        try {
            $network = Network::find($id);
            if (! $network) {
                return $this->errorResponse('الشبكة غير موجودة.', [], 404);
            }

            $currentUser = $this->currentUser();
            if ($currentUser && ! Gate::forUser($currentUser)->allows('view', $network)) {
                return $this->errorResponse('غير مصرح لك بعرض نقاط البيع لهذه الشبكة.', [], 403);
            }

            $filters = $request->validated();
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
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 120)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 50)),
            new OA\Parameter(name: 'cursor', in: 'query', required: false, description: 'Cursor token returned by the previous page.', schema: new OA\Schema(type: 'string', maxLength: 2048)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة الشبكات المرتبطة بنقطة البيع بنجاح'),
        ]
    )]
    public function posNetworksList(int $id, FilterNetworkPosRequest $request): JsonResponse
    {
        try {
            $pos = Pos::find($id);
            if (! $pos) {
                return $this->errorResponse('نقطة البيع غير موجودة.', [], 404);
            }

            $currentUser = $this->currentUser();
            if ($currentUser && ! Gate::forUser($currentUser)->allows('view', $pos)) {
                return $this->errorResponse('غير مصرح لك بعرض الشبكات المرتبطة بنقطة البيع هذه.', [], 403);
            }

            $filters = $request->validated();
            $list = $this->networkPosService->getNetworksForPos($id, $filters);

            return $this->successResponse('تم استرجاع قائمة الشبكات المرتبطة بنقطة البيع بنجاح.', NetworkPosResource::collection($list));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب الشبكات المرتبطة بنقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء دعوة وتحدي ربط نقطة بيع بالشبكة عبر رمز QR.
     */
    #[OA\Get(
        path: '/api/v1/network-pos/challenges/linkable-targets',
        summary: 'البحث عن نقاط البيع المؤهلة لدعوة ربط جديدة',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 120)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 30, default: 15)),
            new OA\Parameter(name: 'cursor', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 2048)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'قائمة نقاط البيع المؤهلة المصفحة بمؤشر'),
            new OA\Response(response: 403, description: 'غير مصرح بالوصول إلى الشبكة'),
            new OA\Response(response: 422, description: 'بيانات البحث غير صالحة'),
        ]
    )]
    /**
     * Search the eligible POS targets for one authorized Network.
     */
    public function searchLinkablePos(SearchNetworkLinkablePosRequest $request): JsonResponse
    {
        try {
            $results = $this->challengeService->searchLinkablePos(
                $this->currentUser(),
                $request->getNetworkId(),
                $request->getSearch(),
                $request->getPerPage(),
                $request->getCursor()
            );

            return $this->successResponse(
                'تم استرجاع نقاط البيع المؤهلة للدعوة.',
                NetworkLinkablePosResource::collection($results)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception) {
            return $this->errorResponse(
                'تعذر البحث عن نقاط البيع المؤهلة. حاول مرة أخرى.',
                [],
                422
            );
        }
    }

    /**
     * Resolve one exact POS business code without exposing a POS directory.
     */
    #[OA\Post(
        path: '/api/v1/network-pos/challenges/resolve-target',
        summary: 'Resolve exact POS link target',
        tags: ['Network POS'],
        operationId: 'network_pos_resolve_target',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['network_id', 'pos_uuid'],
                    properties: [
                        new OA\Property(property: 'network_id', type: 'integer', minimum: 1),
                        new OA\Property(property: 'pos_uuid', type: 'string', maxLength: 64, pattern: '^POS-[0-9]{4}-[0-9]{5}-[A-F0-9]{4}$')
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم التحقق من نقطة البيع وهي مؤهلة لإرسال دعوة الربط.'),
            new OA\Response(response: 401, description: 'غير مصادق عليه'),
            new OA\Response(response: 403, description: 'غير مصرح'),
            new OA\Response(response: 404, description: 'نقطة البيع أو الشبكة غير موجودة'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة')
        ]
    )]
    public function resolveLinkTarget(ResolveNetworkLinkablePosRequest $request): JsonResponse
    {
        try {
            $pos = $this->challengeService->resolveLinkTarget(
                $this->currentUser(),
                $request->getNetworkId(),
                $request->getPosBusinessCode()
            );

            return $this->successResponse(
                'تم التحقق من نقطة البيع وهي مؤهلة لإرسال دعوة الربط.',
                new NetworkLinkablePosResource($pos)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception) {
            return $this->errorResponse(
                'تعذر التحقق من نقطة البيع. راجع الرمز وحاول مرة أخرى.',
                [],
                422
            );
        }
    }

    #[OA\Post(
        path: '/api/v1/network-pos/challenges',
        summary: 'إنشاء دعوة وتحدي ربط نقطة بيع بشبكة عبر رمز QR',
        tags: ['Network POS'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات إنشاء التحدي المشفر. يجب إرسال الرمز التجاري لنقطة البيع في pos_uuid.',
            content: new OA\JsonContent(
                required: ['network_id', 'pos_uuid'],
                properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'pos_uuid', type: 'string', example: 'POS-2611-00001-8F3A', description: 'الرمز التجاري لنقطة البيع المستهدفة.'),
                    new OA\Property(property: 'duration_minutes', type: 'integer', default: 15, example: 15),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء دعوة الربط المشفرة بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة'),
        ]
    )]
    public function createChallenge(CreateNetworkPosLinkingChallengeRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $challenge = $this->challengeService->createChallenge(
                $user,
                $request->getNetworkId(),
                $request->getPosUuid(),
                $request->getDurationMinutes()
            );

            return $this->successResponse(
                'تم إنشاء دعوة ربط نقطة البيع عبر رمز QR بنجاح.',
                new NetworkPosLinkingChallengeResource($challenge),
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل إنشاء دعوة الربط: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * معاينة وفحص دعوة الربط المشفرة من جانب نقطة البيع قبل الاعتماد.
     */
    #[OA\Post(
        path: '/api/v1/network-pos/challenges/preview',
        summary: 'معاينة وفحص دعوة الربط المشفرة من جانب نقطة البيع قبل الاعتماد',
        tags: ['Network POS'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'حمولة رمز QR والرمز التجاري لنقطة البيع المستهدفة',
            content: new OA\JsonContent(
                required: ['payload', 'pos_uuid'],
                properties: [
                    new OA\Property(property: 'payload', type: 'string', example: 'NTS_LINK_V1_...'),
                    new OA\Property(property: 'pos_uuid', type: 'string', example: 'POS-2611-00001-8F3A'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم فحص ومعاينة دعوة الربط بنجاح'),
            new OA\Response(response: 422, description: 'بيانات غير صالحة أو دعوة منتهية/تالفة'),
        ]
    )]
    public function previewChallenge(PreviewNetworkPosLinkingChallengeRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $preview = $this->challengeService->previewChallenge(
                $user,
                $request->getChallengePayload(),
                $request->getPosUuid()
            );

            return $this->successResponse(
                'تم فحص ومعاينة دعوة الربط بنجاح.',
                new NetworkPosLinkingPreviewResource($preview)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشلت معاينة دعوة الربط: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تأكيد ربط نقطة البيع بالشبكة عبر التحدي المشفر (Consumer Confirmation).
     */
    #[OA\Post(
        path: '/api/v1/network-pos/challenges/confirm',
        summary: 'تأكيد اعتماد ربط نقطة البيع بالشبكة عبر التحدي المشفر',
        tags: ['Network POS'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'المعرف الفريد للتحدي والرمز التجاري لنقطة البيع المؤكدة',
            content: new OA\JsonContent(
                required: ['challenge_uuid', 'pos_uuid'],
                properties: [
                    new OA\Property(property: 'challenge_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'pos_uuid', type: 'string', example: 'POS-2611-00001-8F3A'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم تأكيد وربط نقطة البيع بالشبكة بنجاح'),
            new OA\Response(response: 422, description: 'فشل التأكيد أو انتهاء الصلاحية'),
        ]
    )]
    public function confirmChallenge(ConfirmNetworkPosLinkingChallengeRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $networkPos = $this->challengeService->confirmChallenge(
                $user,
                $request->getChallengeUuid(),
                $request->getPosUuid()
            );

            return $this->successResponse(
                'تم تأكيد وربط نقطة البيع بالشبكة بنجاح.',
                new NetworkPosResource($networkPos),
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل تأكيد الربط: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * Read challenge lifecycle state without returning its encrypted payload or identifiers.
     */
    #[OA\Get(
        path: '/api/v1/network-pos/challenges/{uuid}', summary: 'challengeStatus', tags: ['Network POS'], operationId: 'network_pos_challengeStatus',
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function challengeStatus(string $uuid): JsonResponse
    {
        try {
            $challenge = $this->challengeService->getChallengeStatus(
                $this->currentUser(),
                $uuid
            );

            if (! $challenge) {
                return $this->errorResponse('دعوة الربط غير موجودة.', [], 404);
            }

            return $this->successResponse(
                'تم استرجاع حالة دعوة الربط بنجاح.',
                new NetworkPosLinkingChallengeStatusResource($challenge)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استرجاع حالة دعوة الربط.', [], 422);
        }
    }

    /**
     * إلغاء/إبطال دعوة الربط عبر المعرف الفريد.
     */
    #[OA\Delete(
        path: '/api/v1/network-pos/challenges/{uuid}',
        summary: 'إلغاء وإبطال دعوة الربط المشفرة',
        tags: ['Network POS'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم إلغاء دعوة الربط بنجاح'),
            new OA\Response(response: 422, description: 'تعذر إلغاء دعوة الربط'),
        ]
    )]
    public function revokeChallenge(string $uuid): JsonResponse
    {
        try {
            $user = $this->currentUser();
            $revoked = $this->challengeService->revokeChallenge($user, $uuid);

            if (! $revoked) {
                return $this->errorResponse('دعوة الربط غير موجودة أو تعذر إلغاؤها.', [], 404);
            }

            return $this->successResponse('تم إلغاء دعوة الربط بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('فشل إلغاء دعوة الربط: '.$e->getMessage(), [], 422);
        }
    }

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/network-pos/trashed', summary: 'trashedForNetwork', tags: ['Network POS'], operationId: 'network_pos_trashedForNetwork',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function trashedForNetwork(\Illuminate\Http\Request $request, int $networkId): JsonResponse
    {
        $user = $this->currentUser();
        if (! $user || ! $this->tenantPermissions->allowsNetwork($user, $networkId, 'networks.view')) {
            return $this->errorResponse('غير مصرح بعرض العلاقات المؤرشفة لهذه الشبكة.', [], 403);
        }

        $links = $this->networkPosService->getTrashedForNetwork($networkId, $request->only(['per_page', 'cursor']));
        return $this->successResponse('تم استرجاع العلاقات المؤرشفة.', NetworkPosResource::collection($links));
    }

    #[OA\Get(
        path: '/api/v1/pos/{posId}/network-pos/trashed', summary: 'trashedForPos', tags: ['Network POS'], operationId: 'network_pos_trashedForPos',
        parameters: [new OA\Parameter(name: 'posId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function trashedForPos(Request $request, int $posId): JsonResponse
    {
        $user = $this->currentUser();
        if (! $user || ! $this->tenantPermissions->allowsPos($user, $posId, 'pos.view')) {
            return $this->errorResponse('غير مصرح بعرض العلاقات المؤرشفة لنقطة البيع.', [], 403);
        }

        $links = $this->networkPosService->getTrashedForPos($posId, $request->only(['per_page', 'cursor']));
        return $this->successResponse('تم استرجاع العلاقات المؤرشفة.', NetworkPosResource::collection($links));
    }

    #[OA\Post(
        path: '/api/v1/network-pos/{id}/restore', summary: 'restoreLink', tags: ['Network POS'], operationId: 'network_pos_restoreLink',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function restoreLink(int $id): JsonResponse
    {
        $link = $this->networkPosService->findByIdWithTrashed($id);
        if (! $link || ! $link->trashed()) {
            return $this->errorResponse('العلاقة المؤرشفة غير موجودة.', [], 404);
        }
        $user = $this->currentUser();
        if ($user && ! Gate::forUser($user)->allows('restore', $link)) {
            return $this->errorResponse('غير مصرح باستعادة هذه العلاقة.', [], 403);
        }
        try {
            $restored = $this->networkPosService->restore($id);
            return $restored
                ? $this->successResponse('تمت استعادة العلاقة بنجاح.', new NetworkPosResource($restored))
                : $this->errorResponse('تعذرت استعادة العلاقة.', [], 409);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذرت استعادة العلاقة.', [], 422);
        }
    }

    #[OA\Get(
        path: '/api/v1/network-pos/{id}/agreements', summary: 'agreements', tags: ['Network POS'], operationId: 'network_pos_agreements',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function agreements(\Illuminate\Http\Request $request, int $id): JsonResponse
    {
        $link = $this->networkPosService->findByIdWithTrashed($id);
        if (! $link) return $this->errorResponse('العلاقة غير موجودة.', [], 404);
        $user = $this->currentUser();
        if ($user && ! Gate::forUser($user)->allows('viewAgreements', $link)) {
            return $this->errorResponse('غير مصرح بعرض اتفاقيات العلاقة.', [], 403);
        }
        return $this->successResponse('تم استرجاع اتفاقيات العلاقة.', NetworkPosAgreementResource::collection(
            $this->agreementService->agreements($id, $request->only(['per_page', 'cursor']))
        ));
    }

    #[OA\Post(
        path: '/api/v1/network-pos/{id}/agreements', summary: 'storeAgreement', tags: ['Network POS'], operationId: 'network_pos_storeAgreement',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
                requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['title', 'terms'], properties: [new OA\Property(property: 'title', type: 'string', maxLength: 180), new OA\Property(property: 'reference', type: 'string', maxLength: 100), new OA\Property(property: 'terms', type: 'string', maxLength: 50000), new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'), new OA\Property(property: 'ends_at', type: 'string', format: 'date-time')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storeAgreement(StoreNetworkPosAgreementRequest $request, int $id): JsonResponse
    {
        $link = $this->networkPosService->findById($id);
        if (! $link) return $this->errorResponse('العلاقة غير موجودة.', [], 404);
        $user = $this->currentUser();
        if ($user && ! Gate::forUser($user)->allows('manageAgreements', $link)) {
            return $this->errorResponse('غير مصرح بإدارة اتفاقيات العلاقة.', [], 403);
        }
        try {
            $agreement = $this->agreementService->createDraft($id, $request->validated(), $user?->user_id);
            return $this->successResponse('تم إنشاء مسودة الاتفاقية.', new NetworkPosAgreementResource($agreement), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء مسودة الاتفاقية.', [], 422);
        }
    }

    #[OA\Put(
        path: '/api/v1/network-pos/{id}/agreements/{agreementId}', summary: 'updateAgreement', tags: ['Network POS'], operationId: 'network_pos_updateAgreement',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'agreementId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'reference', type: 'string'), new OA\Property(property: 'terms', type: 'string'), new OA\Property(property: 'starts_at', type: 'string'), new OA\Property(property: 'ends_at', type: 'string')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function updateAgreement(UpdateNetworkPosAgreementRequest $request, int $id, int $agreementId): JsonResponse
    {
        $link = $this->networkPosService->findById($id);
        if (! $link) return $this->errorResponse('العلاقة غير موجودة.', [], 404);
        $user = $this->currentUser();
        if ($user && ! Gate::forUser($user)->allows('manageAgreements', $link)) {
            return $this->errorResponse('غير مصرح بتعديل الاتفاقية.', [], 403);
        }
        try {
            $agreement = $this->agreementService->updateDraft($id, $agreementId, $request->validated(), $user?->user_id);
            return $this->successResponse('تم تحديث مسودة الاتفاقية.', new NetworkPosAgreementResource($agreement));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث مسودة الاتفاقية.', [], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/network-pos/{id}/agreements/{agreementId}/activate', summary: 'activateAgreement', tags: ['Network POS'], operationId: 'network_pos_activateAgreement',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'agreementId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function activateAgreement(int $id, int $agreementId): JsonResponse
    {
        $link = $this->networkPosService->findById($id);
        if (! $link) return $this->errorResponse('العلاقة غير موجودة.', [], 404);
        $user = $this->currentUser();
        if ($user && ! Gate::forUser($user)->allows('activateAgreement', $link)) {
            return $this->errorResponse('غير مصرح باعتماد الاتفاقية.', [], 403);
        }
        try {
            $agreement = $this->agreementService->activate($id, $agreementId, $user?->user_id);
            return $this->successResponse('تم اعتماد الاتفاقية.', new NetworkPosAgreementResource($agreement));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر اعتماد الاتفاقية.', [], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/network-pos/{id}/agreements/{agreementId}/terminate', summary: 'terminateAgreement', tags: ['Network POS'], operationId: 'network_pos_terminateAgreement',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'agreementId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: false, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function terminateAgreement(int $id, int $agreementId): JsonResponse
    {
        $link = $this->networkPosService->findById($id);
        if (! $link) return $this->errorResponse('العلاقة غير موجودة.', [], 404);
        $user = $this->currentUser();
        if ($user && ! Gate::forUser($user)->allows('terminateAgreement', $link)) {
            return $this->errorResponse('غير مصرح بإنهاء الاتفاقية.', [], 403);
        }
        try {
            $agreement = $this->agreementService->terminate($id, $agreementId, $user?->user_id);
            return $this->successResponse('تم إنهاء الاتفاقية.', new NetworkPosAgreementResource($agreement));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنهاء الاتفاقية.', [], 422);
        }
    }

    #[OA\Get(
        path: '/api/v1/network-pos/{id}/events', summary: 'relationshipEvents', tags: ['Network POS'], operationId: 'network_pos_relationshipEvents',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function relationshipEvents(\Illuminate\Http\Request $request, int $id): JsonResponse
    {
        $link = $this->networkPosService->findByIdWithTrashed($id);
        if (! $link) return $this->errorResponse('العلاقة غير موجودة.', [], 404);
        $user = $this->currentUser();
        if ($user && ! Gate::forUser($user)->allows('viewEvents', $link)) {
            return $this->errorResponse('غير مصرح بعرض سجل أحداث العلاقة.', [], 403);
        }
        return $this->successResponse('تم استرجاع سجل أحداث العلاقة.', NetworkPosEventResource::collection(
            $this->agreementService->events($id, $request->only(['per_page', 'cursor']))
        ));
    }
}
