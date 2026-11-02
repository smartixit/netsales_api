<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Debt\CollectRepaymentRequest;
use App\Http\Requests\API\V1\Debt\ReverseRepaymentRequest;
use App\Http\Resources\API\V1\Pos\PosRepaymentResource;
use App\Http\Resources\API\V1\SaleInvoice\SaleInvoiceResource;
use App\Policies\PosRepaymentPolicy;
use App\Services\DebtRepaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'POS Debts & Repayments', description: 'إدارة وتتبع الديون والذمم المدينة، السداد وتحصيل الفواتير الآجلة، تحليل أعمار الديون، وعكس السداد')]
class DebtRepaymentController extends BaseController
{
    public function __construct(
        protected DebtRepaymentService $debtRepaymentService,
        protected PosRepaymentPolicy $policy
    ) {}

    /**
     * استرجاع ملخص الديون والذمم المدينة وتحليل الشرائح العمرية (Aging Summary).
     */
    #[OA\Get(
        path: '/api/v1/pos-debts/summary',
        summary: 'ملخص الديون والشرائح العمرية للشبكة ونقاط البيع',
        tags: ['POS Debts & Repayments'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع ملخص الذمم المدينة بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح'),
        ]
    )]
    public function summary(Request $request): JsonResponse
    {
        try {
            $currentUser = $request->user();
            [$networkId, $posId] = $this->resolveNetworkAndPosScope($request);

            if ($currentUser && ! $this->policy->viewAny($currentUser, $networkId, $posId)) {
                return $this->errorResponse('غير مصرح لك بعرض ملخص مديونية هذه الشبكة أو نقطة البيع.', [], 403);
            }

            $summary = $this->debtRepaymentService->getReceivablesSummary($networkId, $posId);

            return $this->successResponse('تم استرجاع ملخص الذمم المدينة بنجاح.', $summary);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استرجاع ملخص الديون: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استرجاع قائمة الفواتير غير المسددة مع العمر الزمني والشريحة العمرية.
     */
    #[OA\Get(
        path: '/api/v1/pos-debts/invoices',
        summary: 'قائمة فواتير البيع الآجل غير المسددة',
        tags: ['POS Debts & Repayments'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'bucket', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['0_30', '31_60', '61_90', 'over_90'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع فواتير الذمم بنجاح'),
        ]
    )]
    public function outstandingInvoices(Request $request): JsonResponse
    {
        try {
            $currentUser = $request->user();
            [$networkId, $posId] = $this->resolveNetworkAndPosScope($request);

            if ($currentUser && ! $this->policy->viewAny($currentUser, $networkId, $posId)) {
                return $this->errorResponse('غير مصرح لك باستعراض فواتير هذه الشبكة أو نقطة البيع.', [], 403);
            }

            $invoices = $this->debtRepaymentService->getOutstandingInvoices($networkId, $posId, $request->all());

            return $this->successResponse('تم استرجاع فواتير المبيعات الآجلة المستحقة بنجاح.', SaleInvoiceResource::collection($invoices));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استرجاع الفواتير المستحقة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استرجاع سجلات سندات السداد.
     */
    #[OA\Get(
        path: '/api/v1/pos-debts/repayments',
        summary: 'سجل سندات سداد الديون',
        tags: ['POS Debts & Repayments'],
        parameters: [
            new OA\Parameter(name: 'network_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'pos_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['COMPLETED', 'REVERSED'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع سجلات السداد بنجاح'),
        ]
    )]
    public function repayments(Request $request): JsonResponse
    {
        try {
            $currentUser = $request->user();
            [$networkId, $posId] = $this->resolveNetworkAndPosScope($request);

            if ($currentUser && ! $this->policy->viewAny($currentUser, $networkId, $posId)) {
                return $this->errorResponse('غير مصرح لك باستعراض سجلات السداد لهذه الشبكة أو نقطة البيع.', [], 403);
            }

            $repayments = $this->debtRepaymentService->getRepayments($networkId, $posId, $request->all());

            return $this->successResponse('تم استرجاع سجلات سداد الديون بنجاح.', PosRepaymentResource::collection($repayments));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استرجاع سجلات السداد: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * استرجاع تفاصيل سند سداد محدد وتوزيعاته على الفواتير.
     */
    #[OA\Get(
        path: '/api/v1/pos-debts/repayments/{id}',
        summary: 'تفاصيل سند سداد وتوزيعاته على الفواتير',
        tags: ['POS Debts & Repayments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع تفاصيل سند السداد بنجاح'),
            new OA\Response(response: 404, description: 'سند السداد غير موجود'),
        ]
    )]
    public function showRepayment(Request $request, int $id): JsonResponse
    {
        try {
            $repayment = $this->debtRepaymentService->getRepaymentById($id);
            $currentUser = $request->user();

            if ($currentUser && ! $this->policy->view($currentUser, $repayment)) {
                return $this->errorResponse('غير مصرح لك بعرض تفاصيل سند السداد هذا.', [], 403);
            }

            return $this->successResponse('تم استرجاع تفاصيل سند السداد بنجاح.', new PosRepaymentResource($repayment));
        } catch (Exception $e) {
            return $this->errorResponse('تعذر استرجاع تفاصيل سند السداد: '.$e->getMessage(), [], 404);
        }
    }

    /**
     * تحصيل سداد مديونية نقطة البيع وتوزيع المبلغ وفق FIFO.
     */
    #[OA\Post(
        path: '/api/v1/pos-debts/repayments',
        summary: 'تحصيل سداد مديونية نقطة البيع وتوزيعها على الفواتير',
        tags: ['POS Debts & Repayments'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'بيانات سند السداد',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['network_id', 'pos_id', 'amount'],
                    properties: [
                        new OA\Property(property: 'network_id', type: 'integer', example: 1),
                        new OA\Property(property: 'pos_id', type: 'integer', example: 5),
                        new OA\Property(property: 'amount', type: 'number', example: 50000.0),
                        new OA\Property(property: 'payment_method', type: 'string', example: 'CASH'),
                        new OA\Property(property: 'reference_number', type: 'string', nullable: true, example: 'REF-9988'),
                        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'سداد نقدي مباشر'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'تم تسجيل سند السداد وتوزيعه بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح'),
            new OA\Response(response: 422, description: 'خطأ في التحقق من البيانات'),
        ]
    )]
    public function collect(CollectRepaymentRequest $request): JsonResponse
    {
        try {
            $currentUser = $request->user();
            $networkId = $request->getNetworkId();

            if ($currentUser && ! $this->policy->collect($currentUser, $networkId)) {
                return $this->errorResponse('غير مصرح لك بتحصيل سداد الديون لهذه الشبكة.', [], 403);
            }

            $userId = (int) ($currentUser?->user_id ?? 0);
            $repayment = $this->debtRepaymentService->collectRepayment($request->validated(), $userId);

            return $this->successResponse('تم تحصيل سداد المديونية وتوزيعها على الفواتير بنجاح.', new PosRepaymentResource($repayment), 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر تسجيل سداد المديونية: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * عكس سند سداد مديونية مع استرجاع مبالغ الفواتير والأرصدة بدقة.
     */
    #[OA\Post(
        path: '/api/v1/pos-debts/repayments/{id}/reverse',
        summary: 'عكس سند سداد مديونية',
        tags: ['POS Debts & Repayments'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'سبب العكس',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['reversal_reason'],
                    properties: [
                        new OA\Property(property: 'reversal_reason', type: 'string', example: 'سند محرر بالخطأ'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'تم عكس سند السداد بنجاح'),
            new OA\Response(response: 403, description: 'غير مصرح'),
            new OA\Response(response: 422, description: 'تعذر العكس لعدم كفاية الرصيد أو تجاوز السقف الائتماني'),
        ]
    )]
    public function reverse(ReverseRepaymentRequest $request, int $id): JsonResponse
    {
        try {
            $repayment = $this->debtRepaymentService->getRepaymentById($id);
            $currentUser = $request->user();

            if ($currentUser && ! $this->policy->reverse($currentUser, $repayment)) {
                return $this->errorResponse('غير مصرح لك بعكس سند السداد هذا.', [], 403);
            }

            $userId = (int) ($currentUser?->user_id ?? 0);
            $reversed = $this->debtRepaymentService->reverseRepayment($id, $request->getReversalReason(), $userId);

            return $this->successResponse('تم عكس سند السداد وإعادة قيد المديونية بنجاح.', new PosRepaymentResource($reversed));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر عكس سند السداد: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تحديد نطاق الشبكة ونقطة البيع بناءً على الطلب وصلاحيات المستخدم.
     *
     * @return array{0: int, 1: ?int}
     */
    protected function resolveNetworkAndPosScope(Request $request): array
    {
        $currentUser = $request->user();
        $networkId = (int) $request->input('network_id');
        $posId = $request->filled('pos_id') ? (int) $request->input('pos_id') : null;

        if (! $networkId && $currentUser) {
            if ($currentUser->pos) {
                $posId = (int) $currentUser->pos->pos_id;
                $link = $currentUser->pos->networks()->first();
                $networkId = (int) ($link?->network_id ?? 0);
            } elseif ($firstNetwork = $currentUser->networks()->first()) {
                $networkId = (int) $firstNetwork->network_id;
            }
        }

        if (! $networkId) {
            throw ValidationException::withMessages([
                'network_id' => ['معرف الشبكة (network_id) مطلوب لإتمام الاستعلام.'],
            ]);
        }

        return [$networkId, $posId];
    }
}
