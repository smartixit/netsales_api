<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Common\BatchIdsRequest;
use App\Http\Requests\API\V1\Pos\DepositPosWalletRequest;
use App\Http\Requests\API\V1\Pos\StorePosWalletRequest;
use App\Http\Requests\API\V1\Pos\TransferNetworkToPosWalletRequest;
use App\Http\Requests\API\V1\Pos\TransferPosToNetworkWalletRequest;
use App\Http\Requests\API\V1\Pos\UpdatePosWalletRequest;
use App\Http\Requests\API\V1\Pos\WithdrawPosWalletRequest;
use App\Http\Resources\API\V1\Network\NetworkWalletLogResource;
use App\Http\Resources\API\V1\Network\NetworkWalletResource;
use App\Http\Resources\API\V1\Pos\PosWalletLogResource;
use App\Http\Resources\API\V1\Pos\PosWalletResource;
use App\Services\PosWalletService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم محافظ نقاط البيع والتحويلات المالية البينية (Enterprise POS Wallet Controller).
 *
 * متحكم نحيف (Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال الـ FormRequest وتمريره مباشرة إلى PosWalletService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد PosWalletResource.
 * 3. خالٍ تماماً من الاستعلامات المباشرة، شروط الأعمال، أو DB::transaction.
 */
#[OA\Tag(name: 'POS Wallets', description: 'إدارة محافظ نقاط البيع، التغذية والتحويل المالي بين الشبكات ونقاط البيع، كشف الحساب ودفتر الأستاذ، والعمليات المجمعة')]
class PosWalletController extends BaseController
{
    public function __construct(
        protected PosWalletService $posWalletService,
    ) {}

    /**
     * عرض قائمة محافظ نقاط البيع مع الفلترة والبحث والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/pos-wallets',
        summary: 'عرض قائمة محافظ نقاط البيع',
        tags: ['POS Wallets'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
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
            $filters = $request->only(['network_id', 'pos_id', 'status', 'min_balance', 'max_balance', 'search', 'per_page', 'all']);
            $wallets = $this->posWalletService->getWallets($filters);

            return $this->successResponse('تم استرجاع قائمة محافظ نقاط البيع بنجاح.', PosWalletResource::collection($wallets));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة محافظ نقاط البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض تفاصيل محفظة نقطة بيع محددة.
     */
    #[OA\Get(
        path: '/api/v1/pos-wallets/{id}',
        summary: 'عرض تفاصيل محفظة نقطة بيع عبر المعرف',
        tags: ['POS Wallets'],
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
            $wallet = $this->posWalletService->getWalletById($id);

            return $this->successResponse('تم استرجاع بيانات محفظة نقطة البيع بنجاح.', new PosWalletResource($wallet));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عرض بيانات المحفظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض محفظة نقطة بيع محددة لشبكة معينة.
     */
    #[OA\Get(
        path: '/api/v1/pos-wallets/network/{networkId}/pos/{posId}',
        summary: 'عرض محفظة نقطة بيع عبر معرف الشبكة ومعرف نقطة البيع',
        tags: ['POS Wallets'],
        parameters: [
            new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'posId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع المحفظة بنجاح'),
        ]
    )]
    public function showByNetworkAndPos(int $networkId, int $posId): JsonResponse
    {
        try {
            $wallet = $this->posWalletService->getWalletByNetworkAndPos($networkId, $posId);

            return $this->successResponse('تم استرجاع محفظة نقطة البيع بنجاح.', new PosWalletResource($wallet));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب محفظة نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إنشاء محفظة جديدة لنقطة بيع.
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets',
        summary: 'إنشاء محفظة جديدة لنقطة بيع',
        tags: ['POS Wallets'],
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
                    new OA\Property(property: 'wallet_balance', type: 'number', example: 100.5),
                    new OA\Property(property: 'status', type: 'boolean', example: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم إنشاء وتدشين محفظة نقطة البيع بنجاح'),
        ]
    )]
    public function store(StorePosWalletRequest $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()?->user_id;
            $wallet = $this->posWalletService->createWallet($request, $userId > 0 ? $userId : null);

            return $this->successResponse('تم إنشاء وتدشين محفظة نقطة البيع بنجاح.', new PosWalletResource($wallet), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إنشاء محفظة نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديث حالة محفظة نقطة بيع (تفعيل / تجميد).
     */
    #[OA\Put(
        path: '/api/v1/pos-wallets/{id}',
        summary: 'تحديث حالة محفظة نقطة بيع',
        tags: ['POS Wallets'],
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
    public function update(UpdatePosWalletRequest $request, int $id): JsonResponse
    {
        try {
            $wallet = $this->posWalletService->updateStatus($id, $request);

            return $this->successResponse('تم تحديث حالة محفظة نقطة البيع بنجاح.', new PosWalletResource($wallet));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تحديث محفظة نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إيداع رصيد مالي مباشر في محفظة نقطة البيع.
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets/{id}/deposit',
        summary: 'إيداع رصيد مباشر في محفظة نقطة البيع',
        tags: ['POS Wallets'],
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
    public function deposit(DepositPosWalletRequest $request, int $id): JsonResponse
    {
        try {
            $userId = (int) $request->user()?->user_id;
            $result = $this->posWalletService->deposit($id, $request, $userId);

            return $this->successResponse('تم إيداع الرصيد في محفظة نقطة البيع بنجاح.', [
                'wallet' => new PosWalletResource($result['wallet']),
                'log' => new PosWalletLogResource($result['log']),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إتمام الإيداع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * سحب رصيد مالي مباشر من محفظة نقطة البيع.
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets/{id}/withdraw',
        summary: 'سحب رصيد مباشر من محفظة نقطة البيع',
        tags: ['POS Wallets'],
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
    public function withdraw(WithdrawPosWalletRequest $request, int $id): JsonResponse
    {
        try {
            $userId = (int) $request->user()?->user_id;
            $result = $this->posWalletService->withdraw($id, $request, $userId);

            return $this->successResponse('تم سحب الرصيد من محفظة نقطة البيع بنجاح.', [
                'wallet' => new PosWalletResource($result['wallet']),
                'log' => new PosWalletLogResource($result['log']),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إتمام السحب: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحويل وتغذية رصيد من محفظة الشبكة إلى محفظة نقطة البيع (عملية ذرية مشتركة).
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets/transfer-from-network',
        summary: 'تحويل وتغذية رصيد من محفظة الشبكة إلى محفظة نقطة البيع',
        tags: ['POS Wallets'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id', 'pos_id', 'amount'],
                    properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'pos_id', type: 'integer', example: 1),
                    new OA\Property(property: 'amount', type: 'number', example: 100.5),
                    new OA\Property(property: 'statement', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم تحويل وتغذية الرصيد بنجاح'),
        ]
    )]
    public function transferFromNetwork(TransferNetworkToPosWalletRequest $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()?->user_id;
            $result = $this->posWalletService->transferFromNetwork($request, $userId);

            return $this->successResponse('تم تحويل وتغذية الرصيد إلى نقطة البيع بنجاح وتسجيل القيود المالية المزدوجة.', [
                'network_wallet' => new NetworkWalletResource($result['network_wallet']),
                'pos_wallet' => new PosWalletResource($result['pos_wallet']),
                'network_log' => new NetworkWalletLogResource($result['network_log']),
                'pos_log' => new PosWalletLogResource($result['pos_log']),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إتمام التحويل: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استرجاع أو تحويل رصيد من محفظة نقطة البيع إلى محفظة الشبكة (عملية ذرية مشتركة).
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets/transfer-to-network',
        summary: 'استرجاع أو تحويل رصيد من محفظة نقطة البيع إلى محفظة الشبكة',
        tags: ['POS Wallets'],
                requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات الطلب',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['network_id', 'pos_id', 'amount'],
                    properties: [
                    new OA\Property(property: 'network_id', type: 'integer', example: 1),
                    new OA\Property(property: 'pos_id', type: 'integer', example: 1),
                    new OA\Property(property: 'amount', type: 'number', example: 100.5),
                    new OA\Property(property: 'statement', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع الرصيد بنجاح'),
        ]
    )]
    public function transferToNetwork(TransferPosToNetworkWalletRequest $request): JsonResponse
    {
        try {
            $userId = (int) $request->user()?->user_id;
            $result = $this->posWalletService->transferToNetwork($request, $userId);

            return $this->successResponse('تم استرجاع الرصيد إلى محفظة الشبكة بنجاح وتسجيل القيود المالية المزدوجة.', [
                'pos_wallet' => new PosWalletResource($result['pos_wallet']),
                'network_wallet' => new NetworkWalletResource($result['network_wallet']),
                'pos_log' => new PosWalletLogResource($result['pos_log']),
                'network_log' => new NetworkWalletLogResource($result['network_log']),
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر إتمام الاسترجاع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعراض قيود ودفتر أستاذ محفظة نقطة البيع.
     */
    #[OA\Get(
        path: '/api/v1/pos-wallets/{id}/logs',
        summary: 'عرض كشف حساب وسجلات محفظة نقطة البيع',
        tags: ['POS Wallets'],
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
            $filters = $request->only(['log_operation_type', 'created_by', 'from_date', 'to_date', 'search', 'per_page', 'all']);
            $logs = $this->posWalletService->getLogs($id, $filters);

            return $this->successResponse('تم استرجاع كشف حساب وسجلات محفظة نقطة البيع بنجاح.', PosWalletLogResource::collection($logs));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب كشف حساب المحفظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * حذف محفظة نقطة بيع (حذف مؤقت).
     */
    #[OA\Delete(
        path: '/api/v1/pos-wallets/{id}',
        summary: 'حذف محفظة نقطة بيع مؤقتاً',
        tags: ['POS Wallets'],
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
            $this->posWalletService->deleteWallet($id);

            return $this->successResponse('تم حذف محفظة نقطة البيع مؤقتاً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر حذف محفظة نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عرض قائمة المحافظ المحذوفة مؤقتاً لنقاط البيع.
     */
    #[OA\Get(
        path: '/api/v1/pos-wallets/trashed',
        summary: 'عرض محافظ نقاط البيع المحذوفة مؤقتاً',
        tags: ['POS Wallets'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
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
            $filters = $request->only(['network_id', 'pos_id', 'search', 'per_page', 'all']);
            $wallets = $this->posWalletService->getTrashedWallets($filters);

            return $this->successResponse('تم استرجاع قائمة المحافظ المحذوفة لنقاط البيع بنجاح.', PosWalletResource::collection($wallets));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب قائمة المحافظ المحذوفة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استعادة محفظة نقطة بيع محذوفة مؤقتاً.
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets/{id}/restore',
        summary: 'استعادة محفظة نقطة بيع محذوفة مؤقتاً',
        tags: ['POS Wallets'],
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
            $this->posWalletService->restoreWallet($id);

            return $this->successResponse('تم استعادة محفظة نقطة البيع بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استعادة محفظة نقطة البيع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي لمحفظة نقطة بيع.
     */
    #[OA\Delete(
        path: '/api/v1/pos-wallets/{id}/force',
        summary: 'الحذف النهائي لمحفظة نقطة بيع',
        tags: ['POS Wallets'],
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
            $this->posWalletService->forceDeleteWallet($id);

            return $this->successResponse('تم حذف محفظة نقطة البيع نهائياً بنجاح.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر الحذف النهائي للمحفظة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف المجمع لمحافظ نقاط البيع.
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets/batch-delete',
        summary: 'حذف مجمع لمحافظ نقاط البيع',
        tags: ['POS Wallets'],
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
            $count = $this->posWalletService->bulkDelete($request->getIds());

            return $this->successResponse("تم حذف ({$count}) محفظة لنقاط البيع بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف المجمع: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الاستعادة المجمعة لمحافظ نقاط البيع.
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets/batch-restore',
        summary: 'استعادة مجمعة لمحافظ نقاط البيع',
        tags: ['POS Wallets'],
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
            $count = $this->posWalletService->bulkRestore($request->getIds());

            return $this->successResponse("تم استعادة ({$count}) محفظة لنقاط البيع بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الاستعادة المجمعة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * الحذف النهائي المجمع لمحافظ نقاط البيع.
     */
    #[OA\Post(
        path: '/api/v1/pos-wallets/batch-force-delete',
        summary: 'حذف نهائي مجمع لمحافظ نقاط البيع',
        tags: ['POS Wallets'],
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
            $count = $this->posWalletService->bulkForceDelete($request->getIds());

            return $this->successResponse("تم الحذف النهائي لـ ({$count}) محفظة لنقاط البيع بنجاح.");
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تنفيذ الحذف النهائي المجمع: '.$e->getMessage(), [], 422);
        }
    }
}
