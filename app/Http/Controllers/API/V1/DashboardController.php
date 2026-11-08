<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Dashboard\DashboardOverviewRequest;
use App\Http\Resources\API\V1\Dashboard\DashboardOverviewResource;
use App\Services\DashboardOverviewService;
use Exception;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Dashboard', description: 'مؤشرات لوحة تحكم إدارة المنصة')]
class DashboardController extends BaseController
{
    public function __construct(
        protected DashboardOverviewService $overviewService
    ) {}

    #[OA\Get(
        path: '/api/v1/dashboard/overview',
        summary: 'بيانات Overview الحقيقية للوحة تحكم المنصة',
        tags: ['Dashboard'],
        parameters: [
            new OA\Parameter(
                name: 'period',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['7d', '30d', '90d', '12m'], default: '30d')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'بيانات المؤشرات والرسوم والمهام التي يحق للدور الحالي رؤيتها'),
            new OA\Response(response: 401, description: 'جلسة غير صالحة'),
            new OA\Response(response: 403, description: 'الدور غير مخول للوصول إلى لوحة المنصة'),
            new OA\Response(response: 422, description: 'قيمة الفترة غير صالحة'),
        ]
    )]
    public function overview(DashboardOverviewRequest $request): JsonResponse
    {
        try {
            $overview = $this->overviewService->getOverview(
                $request->user(),
                $request->period()
            );

            return $this->successResponse(
                'تم جلب بيانات لوحة تحكم المنصة بنجاح.',
                (new DashboardOverviewResource($overview))->resolve($request)
            );
        } catch (Exception $exception) {
            report($exception);

            return $this->errorResponse(
                'تعذر جلب بيانات لوحة تحكم المنصة حالياً.',
                [],
                500
            );
        }
    }
}
