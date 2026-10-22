<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\API\V1\Dashboard\DashboardOverviewRequest;
use App\Http\Resources\API\V1\Dashboard\DashboardOverviewResource;
use App\Services\DashboardService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم لوحة القيادة والتوزيع المركزية (Enterprise Dashboard Controller).
 *
 * متحكم نحيف (Strict Lean Controller) يلتزم بالمعايير الصارمة:
 * 1. استقبال الـ FormRequest وتمريره مباشرة إلى DashboardService.
 * 2. تغليف النتيجة عبر ApiResponseTrait ومورد DashboardOverviewResource.
 * 3. خالٍ تماماً من الاستعلامات المباشرة، شروط الأعمال، أو المعاملات المالية.
 */
#[OA\Tag(name: 'Dashboard', description: 'لوحة القيادة الموحدة، المؤشرات المالية والتشغيلية، رادار التنبيهات، والعمليات الأخيرة الموحدة لكافة الأدوار')]
class DashboardController extends BaseController
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    /**
     * استعلام النظرة العامة والمؤشرات المجمعة للوحة القيادة.
     */
    #[OA\Get(
        path: '/api/v1/dashboard/overview',
        summary: 'عرض النظرة العامة والمؤشرات التجميعية للوحة القيادة',
        security: [['sessionAuth' => []]],
        tags: ['Dashboard'],
        parameters: [
            new OA\Parameter(
                name: 'period',
                in: 'query',
                required: false,
                description: 'النطاق الزمني للمؤشرات (today, yesterday, this_week, this_month, this_year)',
                schema: new OA\Schema(type: 'string', enum: ['today', 'yesterday', 'this_week', 'this_month', 'this_year'], default: 'today')
            ),
            new OA\Parameter(
                name: 'network_id',
                in: 'query',
                required: false,
                description: 'معرف الشبكة المستهدفة (خاص بمزودي الشبكات المتعددة أو الإدارة)',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pos_id',
                in: 'query',
                required: false,
                description: 'معرف نقطة البيع المستهدفة',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'refresh',
                in: 'query',
                required: false,
                description: 'إجبار الخادم على تجاوز الكاش وإعادة الحساب فوراً',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم استرجاع بيانات لوحة القيادة بنجاح'),
            new OA\Response(response: 401, description: 'غير مصادق عليه / جلسة غير صالحة'),
            new OA\Response(response: 422, description: 'خطأ في معالجة الإحصائيات'),
        ]
    )]
    public function overview(DashboardOverviewRequest $request): JsonResponse
    {
        try {
            $user = $this->currentUser();
            if (! $user) {
                return $this->errorResponse('غير مصادق عليه.', [], 401);
            }

            $overview = $this->dashboardService->getOverview($user, $request);

            return $this->successResponse('تم استرجاع بيانات لوحة القيادة بنجاح.', new DashboardOverviewResource($overview));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('تعذر جلب بيانات لوحة القيادة: '.$e->getMessage(), [], 422);
        }
    }
}
