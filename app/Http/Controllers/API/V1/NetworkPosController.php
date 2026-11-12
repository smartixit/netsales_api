<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Common\BatchStatusRequest;
use App\Http\Requests\API\V1\Network\FilterNetworkPosRequest;
use App\Http\Requests\API\V1\Network\ConfirmNetworkPosLinkingChallengeRequest;
use App\Http\Requests\API\V1\Network\CreateNetworkPosLinkingChallengeRequest;
use App\Http\Requests\API\V1\Network\PreviewNetworkPosLinkingChallengeRequest;
use App\Http\Requests\API\V1\Network\StoreNetworkPosRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkPosRequest;
use App\Http\Resources\API\V1\Network\NetworkPosLinkingChallengeResource;
use App\Http\Resources\API\V1\Network\NetworkPosLinkingPreviewResource;
use App\Http\Resources\API\V1\Network\NetworkPosResource;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\Pos;
use App\Services\NetworkPosLinkingChallengeService;
use App\Services\NetworkPosService;
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
            if ($user && ! $user->isSuperAdmin()) {
                $networkIds = $user->networks()->pluck('networks.network_id')
                    ->merge($user->networkUsers()->pluck('network_id'))
                    ->unique()->values()->toArray();
                $posIds = $user->pos()->pluck('pos.pos_id')
                    ->merge($user->posUsers()->pluck('pos_id'))
                    ->unique()->values()->toArray();

                $filters['scoped_network_ids'] = $networkIds;
                $filters['scoped_pos_ids'] = $posIds;
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
            if ($currentUser && ! Gate::forUser($currentUser)->allows('update', $link)) {
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
            if ($currentUser && ! $currentUser->isSuperAdmin()) {
                $unauthorized = NetworkPos::whereIn('network_pos_id', $ids)
                    ->whereDoesntHave('network', function ($q) use ($currentUser) {
                        $q->where('owner_id', $currentUser->user_id)
                            ->orWhereHas('memberships', fn ($m) => $m->where('user_id', $currentUser->user_id));
                    })->exists();

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
            if ($currentUser && ! $currentUser->isSuperAdmin()) {
                $unauthorized = NetworkPos::whereIn('network_pos_id', $ids)
                    ->whereDoesntHave('network', function ($q) use ($currentUser) {
                        $q->where('owner_id', $currentUser->user_id)
                            ->orWhereHas('memberships', fn ($m) => $m->where('user_id', $currentUser->user_id));
                    })->exists();

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
    #[OA\Get(
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
    #[OA\Post(
        path: '/api/v1/network-pos/challenges',
        summary: 'إنشاء دعوة وتحدي ربط نقطة بيع بشبكة عبر رمز QR',
        tags: ['Network POS'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات إنشاء التحدي المشفر',
            content: new OA\JsonContent(
                required: ['network_id'],
                properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'pos_id', type: 'integer', nullable: true, example: 5),
                    new OA\Property(property: 'pos_uuid', type: 'string', nullable: true, example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
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
                $request->getPosId(),
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
            description: 'حمولة رمز QR ومعرف نقطة البيع المستهدفة',
            content: new OA\JsonContent(
                required: ['payload'],
                properties: [
                    new OA\Property(property: 'payload', type: 'string', example: 'NTS_LINK_V1_...'),
                    new OA\Property(property: 'pos_id', type: 'integer', nullable: true, example: 5),
                    new OA\Property(property: 'pos_uuid', type: 'string', nullable: true, example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
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
                $request->getPosId(),
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
            description: 'المعرف الفريد للتحدي ونقطة البيع المؤكدة',
            content: new OA\JsonContent(
                required: ['challenge_uuid'],
                properties: [
                    new OA\Property(property: 'challenge_uuid', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'pos_id', type: 'integer', nullable: true, example: 5),
                    new OA\Property(property: 'pos_uuid', type: 'string', nullable: true, example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
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
                $request->getPosId(),
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
}
