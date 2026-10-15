<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Resources\API\V1\ScheduledTaskRun\ScheduledTaskRunResource;
use App\Services\ScheduledTaskRunService;
use App\Services\SmartSchedulerCatchupService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

/**
 * متحكم إدارة وسجلات المهام المجدولة (Scheduled Task Run Controller).
 */
#[OA\Tag(name: 'Scheduled Tasks', description: 'إدارة وسجلات المهام المجدولة ومحرك الاستدراك الذكي')]
class ScheduledTaskRunController extends BaseController
{
    public function __construct(
        protected ScheduledTaskRunService $taskRunService,
        protected SmartSchedulerCatchupService $catchupService
    ) {}

    /**
     * عرض سجلات تشغيل المهام المجدولة مع الفلترة والترقيم.
     */
    #[OA\Get(
        path: '/api/v1/scheduled-tasks',
        summary: 'قائمة سجلات تشغيل المهام المجدولة',
        security: [['sessionAuth' => [], 'deviceAuth' => []]],
        tags: ['Scheduled Tasks'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
            new OA\Parameter(name: 'all', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'تم جلب سجلات المهام المجدولة بنجاح'),
            new OA\Response(response: 422, description: 'خطأ أثناء جلب سجلات المهام المجدولة'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'task_type' => $request->query('task_type'),
                'network_id' => $request->query('network_id'),
                'target_date' => $request->query('target_date'),
                'status' => $request->query('status'),
                'per_page' => $request->query('per_page', 15),
            ];

            $runs = $this->taskRunService->getFiltered($filters);

            return $this->successResponse(
                'تم جلب سجلات المهام المجدولة بنجاح.',
                ScheduledTaskRunResource::collection($runs)->response()->getData(true)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('خطأ أثناء جلب سجلات المهام المجدولة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * إحصائيات سريعة لحالات المهام المجدولة لليوم والتاريخ الإجمالي.
     */
    #[OA\Get(
        path: '/api/v1/scheduled-tasks/stats',
        summary: 'إحصائيات المهام المجدولة',
        security: [['sessionAuth' => [], 'deviceAuth' => []]],
        tags: ['Scheduled Tasks'],
        responses: [
            new OA\Response(response: 200, description: 'تم جلب إحصائيات المهام المجدولة بنجاح'),
            new OA\Response(response: 422, description: 'خطأ أثناء جلب الإحصائيات'),
        ]
    )]
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->taskRunService->getSummaryStats();

            return $this->successResponse('تم جلب إحصائيات المهام المجدولة بنجاح.', $stats);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('خطأ أثناء جلب إحصائيات المهام المجدولة: '.$e->getMessage(), [], 422);
        }
    }

    /**
     * تشغيل يدوي فوري لمحرك الاستدراك والتعويض الذكي للمهام الفائتة.
     */
    #[OA\Post(
        path: '/api/v1/scheduled-tasks/catchup',
        summary: 'تشغيل محرك الاستدراك الذكي للمهام الفائتة',
        security: [['sessionAuth' => [], 'deviceAuth' => []]],
        tags: ['Scheduled Tasks'],
        responses: [
            new OA\Response(response: 200, description: 'تم تشغيل محرك التعويض الذكي بنجاح'),
            new OA\Response(response: 422, description: 'خطأ أثناء تشغيل محرك التعويض الذكي'),
        ]
    )]
    public function catchup(Request $request): JsonResponse
    {
        try {
            $force = filter_var($request->input('force', false), FILTER_VALIDATE_BOOLEAN);
            $task = $request->input('task');

            $result = $this->catchupService->runAllCatchupTasks($force, $task);

            return $this->successResponse('تم تشغيل محرك التعويض والاستدراك الذكي بنجاح.', $result);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            return $this->errorResponse('خطأ أثناء تشغيل محرك التعويض الذكي: '.$e->getMessage(), [], 422);
        }
    }
}
