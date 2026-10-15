<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Network\DepositNetworkWalletRequest;
use App\Http\Requests\API\V1\Network\StoreNetworkWalletRequest;
use App\Http\Requests\API\V1\Network\UpdateNetworkWalletRequest;
use App\Http\Requests\API\V1\Network\WithdrawNetworkWalletRequest;
use App\Http\Resources\API\V1\Network\NetworkWalletLogResource;
use App\Http\Resources\API\V1\Network\NetworkWalletResource;
use App\Services\NetworkWalletService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم محافظ مزودي الشبكات ودفتر الأستاذ المالي (Enterprise Network Wallet Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال الـ FormRequest وتمريره مباشرة إلى NetworkWalletService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد NetworkWalletResource.
 * 3. خالٍ تماماً من الاستعلامات المباشرة، شروط الأعمال، أو DB::transaction.
 */
#[OA\Tag(name: 'Network Wallets', description: 'إدارة محافظ مزودي الشبكات، الإيداع والسحب المالي، كشف الحساب ودفتر الأستاذ، والعمليات الإدارية والمجمعة')]
class NetworkWalletController extends BaseController
{
    public function __construct(
        protected NetworkWalletService $networkWalletService,
    ) {}

    /**
     * عرض قائمة محافظ الشبكات مع الفلترة والبحث والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/network-wallets',
        summary: 'عرض قائمة محافظ مزودي الشبكات',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'min_balance', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'max_balance', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قائمة المحافظ بنجاح'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'status', 'min_balance', 'max_balance', 'search', 'per_page', 'all']);
            $wallets = $this->networkWalletService->getWallets($filters);

            return $this->successResponse('تم استرجاع قائمة محافظ الشبكات بنجاح.', NetworkWalletResource::collection($wallets));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة محافظ الشبكات: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل محفظة شبكة محددة.
     */
    #[OA\Get(
        path: '/api/v1/network-wallets/{id}',
        summary: 'عرض تفاصيل محفظة شبكة عبر المعرف',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع بيانات المحفظة بنجاح'),
            new OA\Response(response: 404, description: 'المحفظة غير موجودة'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $wallet = $this->networkWalletService->getWalletById($id);

            return $this->successResponse('تم استرجاع بيانات المحفظة بنجاح.', new NetworkWalletResource($wallet));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات المحفظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض محفظة شبكة محددة عبر معرف الشبكة.
     */
    #[OA\Get(
        path: '/api/v1/network-wallets/network/{networkId}',
        summary: 'عرض محفظة شبكة عبر معرف الشبكة',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع محفظة الشبكة بنجاح'),
        ]
    )]
    public function showByNetwork(int $networkId): JsonResponse
    {
        try {
            $wallet = $this->networkWalletService->getWalletByNetworkId($networkId);

            return $this->successResponse('تم استرجاع محفظة مزود الشبكة بنجاح.', new NetworkWalletResource($wallet));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب محفظة الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء وتدشين محفظة جديدة لمزود الشبكة.
     */
    #[OA\Post(
        path: '/api/v1/network-wallets',
        summary: 'إنشاء محفظة جديدة لمزود شبكة',
        tags: ['Network Wallets'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id'],
                    properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'wallet_balance', type: 'number', example: 100.5),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء وتدشين المحفظة بنجاح'),
        ]
    )]
    public function store(StoreNetworkWalletRequest $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()?->user_id;
            $wallet = $this->networkWalletService->createWallet($request, $userId > 0 ? $userId : null);

            return $this->successResponse('تم تدشين محفظة مزود الشبكة بنجاح.', new NetworkWalletResource($wallet), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء محفظة الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث حالة محفظة شبكة (تفعيل / تجميد).
     */
    #[OA\Put(
        path: '/api/v1/network-wallets/{id}',
        summary: 'تحديث حالة محفظة شبكة',
        tags: ['Network Wallets'],
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
            new OA\Response(response: 200, description: 'تم تحديث حالة المحفظة بنجاح'),
        ]
    )]
    public function update(UpdateNetworkWalletRequest $request, int $id): JsonResponse
    {
        try {
            $wallet = $this->networkWalletService->updateStatus($id, $request);

            return $this->successResponse('تم تحديث حالة محفظة الشبكة بنجاح.', new NetworkWalletResource($wallet));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث محفظة الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إيداع رصيد مالي في محفظة الشبكة مع تسجيل القيد بدفتر الأستاذ.
     */
    #[OA\Post(
        path: '/api/v1/network-wallets/{id}/deposit',
        summary: 'إيداع رصيد في محفظة الشبكة',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['amount', 'statement'],
                    properties: [
                    new OA\Property(property: 'amount', type: 'number', example: 100.5),
                    new OA\Property(property: 'statement', type: 'string'),
                    new OA\Property(property: 'log_date', type: 'string', format: 'date', example: '2026-01-01'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم إيداع الرصيد بنجاح'),
        ]
    )]
    public function deposit(DepositNetworkWalletRequest $request, int $id): JsonResponse
    {
        try {
            $userId = (int) $request->user()?->user_id;
            $result = $this->networkWalletService->deposit($id, $request, $userId);

            return $this->successResponse('تم إيداع الرصيد بنجاح وتسجيل القيد في دفتر الأستاذ.', [
                'wallet' => new NetworkWalletResource($result['wallet']),
                'log' => new NetworkWalletLogResource($result['log']),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إتمام عملية الإيداع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * سحب رصيد مالي من محفظة الشبكة مع تسجيل القيد بدفتر الأستاذ.
     */
    #[OA\Post(
        path: '/api/v1/network-wallets/{id}/withdraw',
        summary: 'سحب رصيد من محفظة الشبكة',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['amount', 'statement'],
                    properties: [
                    new OA\Property(property: 'amount', type: 'number', example: 100.5),
                    new OA\Property(property: 'statement', type: 'string'),
                    new OA\Property(property: 'log_date', type: 'string', format: 'date', example: '2026-01-01'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم سحب الرصيد بنجاح'),
        ]
    )]
    public function withdraw(WithdrawNetworkWalletRequest $request, int $id): JsonResponse
    {
        try {
            $userId = (int) $request->user()?->user_id;
            $result = $this->networkWalletService->withdraw($id, $request, $userId);

            return $this->successResponse('تم سحب الرصيد بنجاح وتسجيل القيد في دفتر الأستاذ.', [
                'wallet' => new NetworkWalletResource($result['wallet']),
                'log' => new NetworkWalletLogResource($result['log']),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إتمام عملية السحب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعراض قيود ودفتر أستاذ محفظة الشبكة.
     */
    #[OA\Get(
        path: '/api/v1/network-wallets/{id}/logs',
        summary: 'عرض كشف حساب وسجلات محفظة الشبكة',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع قيود دفتر الأستاذ بنجاح'),
        ]
    )]
    public function logs(Request $request, int $id): JsonResponse
    {
        try {
            $filters = $request->only(['log_operation', 'created_by', 'from_date', 'to_date', 'search', 'per_page', 'all']);
            $logs = $this->networkWalletService->getLogs($id, $filters);

            return $this->successResponse('تم استرجاع كشف حساب وسجلات المحفظة بنجاح.', NetworkWalletLogResource::collection($logs));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب كشف حساب المحفظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف محفظة شبكة (حذف مؤقت).
     */
    #[OA\Delete(
        path: '/api/v1/network-wallets/{id}',
        summary: 'حذف محفظة شبكة مؤقتاً',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم حذف المحفظة مؤقتاً بنجاح'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->networkWalletService->deleteWallet($id);

            return $this->successResponse('تم حذف محفظة الشبكة مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف محفظة الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة المحافظ المحذوفة مؤقتاً.
     */
    #[OA\Get(
        path: '/api/v1/network-wallets/trashed',
        summary: 'عرض المحافظ المحذوفة مؤقتاً',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع المحافظ المحذوفة بنجاح'),
        ]
    )]
    public function trashed(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['network_id', 'search', 'per_page', 'all']);
            $wallets = $this->networkWalletService->getTrashedWallets($filters);

            return $this->successResponse('تم استرجاع قائمة المحافظ المحذوفة بنجاح.', NetworkWalletResource::collection($wallets));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة المحافظ المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة محفظة محذوفة مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/network-wallets/{id}/restore',
        summary: 'استعادة محفظة محذوفة مؤقتاً',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استعادة المحفظة بنجاح'),
        ]
    )]
    public function restore(int $id): JsonResponse
    {
        try {
            $this->networkWalletService->restoreWallet($id);

            return $this->successResponse('تم استعادة محفظة الشبكة بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة محفظة الشبكة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لمحفظة شبكة من قاعدة البيانات.
     */
    #[OA\Delete(
        path: '/api/v1/network-wallets/{id}/force',
        summary: 'الحذف النهائي لمحفظة شبكة',
        tags: ['Network Wallets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم الحذف النهائي للمحفظة بنجاح'),
        ]
    )]
    public function forceDelete(int $id): JsonResponse
    {
        try {
            $this->networkWalletService->forceDeleteWallet($id);

            return $this->successResponse('تم حذف محفظة الشبكة نهائياً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للمحفظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع لمحافظ الشبكات.
     */
    #[OA\Post(
        path: '/api/v1/network-wallets/batch-delete',
        summary: 'حذف مجمع لمحافظ الشبكات',
        tags: ['Network Wallets'],
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
            $count = $this->networkWalletService->bulkDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) محفظة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة لمحافظ الشبكات.
     */
    #[OA\Post(
        path: '/api/v1/network-wallets/batch-restore',
        summary: 'استعادة مجمعة لمحافظ الشبكات',
        tags: ['Network Wallets'],
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
            $count = $this->networkWalletService->bulkRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) محفظة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع لمحافظ الشبكات.
     */
    #[OA\Post(
        path: '/api/v1/network-wallets/batch-force-delete',
        summary: 'حذف نهائي مجمع لمحافظ الشبكات',
        tags: ['Network Wallets'],
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
            $count = $this->networkWalletService->bulkForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) محفظة بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع: '.$e->getMessage(), [], 422);
        }
    }
}
