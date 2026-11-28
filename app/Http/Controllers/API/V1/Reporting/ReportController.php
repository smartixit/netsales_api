<?php

namespace App\Http\Controllers\API\V1\Reporting;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\API\V1\Reporting\ReportRunIndexRequest;
use App\Http\Requests\API\V1\Reporting\StoreReportRunRequest;
use App\Http\Resources\API\V1\Reporting\ReportRunResource;
use App\Models\ReportRun;
use App\Services\Reporting\ReportRunData;
use App\Services\Reporting\ReportRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ReportController extends BaseController
{
    public function __construct(
        private ReportRunService $reports
    ) {}

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/reports/catalog', summary: 'networkCatalog', tags: ['Report Runs'], operationId: 'report_networkCatalog',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function networkCatalog(int $networkId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع كتالوج التقارير المتاحة للشبكة.',
            $this->reports->networkCatalog($this->requireCurrentUser(), $networkId)
        );
    }

    #[OA\Post(
        path: '/api/v1/networks/{networkId}/reports/runs', summary: 'storeNetworkRun', tags: ['Report Runs'], operationId: 'report_storeNetworkRun',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'object', required: ['report_key'], properties: [new OA\Property(property: 'report_key', type: 'string'), new OA\Property(property: 'filters', type: 'object')]))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object'))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storeNetworkRun(StoreReportRunRequest $request, int $networkId): JsonResponse
    {
        $input = $request->validated();

        $run = $this->reports->queueNetworkReport(
            $this->requireCurrentUser(),
            $networkId,
            (string) $input['report_key'],
            (array) ($input['filters'] ?? [])
        );

        return $this->successResponse(
            'تم تسجيل التقرير في قائمة التنفيذ. يمكنك متابعة حالته وتنزيله عند اكتماله.',
            ReportRunResource::make($this->reports->toData($run))->resolve($request),
            202
        );
    }

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/reports/runs', summary: 'networkRuns', tags: ['Report Runs'], operationId: 'report_networkRuns',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function networkRuns(ReportRunIndexRequest $request, int $networkId): JsonResponse
    {
        $filters = $request->validated();
        $runs = $this->reports->networkRuns(
            $this->requireCurrentUser(),
            $networkId,
            (int) ($filters['per_page'] ?? 20)
        );

        $runs->through(
            fn (ReportRun $run): ReportRunData => $this->reports->toData($run)
        );

        return $this->successResponse(
            'تم استرجاع سجل التقارير التي أنشأها المستخدم في هذه الشبكة.',
            ReportRunResource::collection($runs)
        );
    }

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/reports/runs/{reportRunId}', summary: 'showNetworkRun', tags: ['Report Runs'], operationId: 'report_showNetworkRun',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'reportRunId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function showNetworkRun(Request $request, int $networkId, int $reportRunId): JsonResponse
    {
        $run = $this->reports->networkRun(
            $this->requireCurrentUser(),
            $networkId,
            $reportRunId
        );

        return $this->successResponse(
            'تم استرجاع حالة التقرير وبيانات الأثر الناتج.',
            ReportRunResource::make($this->reports->toData($run))->resolve($request)
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/reports/catalog', summary: 'platformCatalog', tags: ['Report Runs'], operationId: 'report_platformCatalog',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function platformCatalog(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع كتالوج تقارير فوترة المنصة.',
            $this->reports->platformCatalog($this->requireCurrentUser())
        );
    }

    #[OA\Post(
        path: '/api/v1/platform/reports/runs', summary: 'storePlatformRun', tags: ['Report Runs'], operationId: 'report_storePlatformRun',
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(type: 'object', required: ['report_key'], properties: [new OA\Property(property: 'report_key', type: 'string'), new OA\Property(property: 'filters', type: 'object')]))),
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function storePlatformRun(StoreReportRunRequest $request): JsonResponse
    {
        $input = $request->validated();

        $run = $this->reports->queuePlatformReport(
            $this->requireCurrentUser(),
            (string) $input['report_key'],
            (array) ($input['filters'] ?? [])
        );

        return $this->successResponse(
            'تم تسجيل تقرير المنصة في قائمة التنفيذ. يمكنك متابعة حالته وتنزيله عند اكتماله.',
            ReportRunResource::make($this->reports->toData($run))->resolve($request),
            202
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/reports/runs', summary: 'platformRuns', tags: ['Report Runs'], operationId: 'report_platformRuns',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function platformRuns(ReportRunIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $runs = $this->reports->platformRuns(
            $this->requireCurrentUser(),
            (int) ($filters['per_page'] ?? 20)
        );

        $runs->through(
            fn (ReportRun $run): ReportRunData => $this->reports->toData($run)
        );

        return $this->successResponse(
            'تم استرجاع سجل تقارير فوترة المنصة التي أنشأها المستخدم.',
            ReportRunResource::collection($runs)
        );
    }

    #[OA\Get(
        path: '/api/v1/platform/reports/runs/{reportRunId}', summary: 'showPlatformRun', tags: ['Report Runs'], operationId: 'report_showPlatformRun',
        parameters: [new OA\Parameter(name: 'reportRunId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 201, description: 'تم إنشاء المورد'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function showPlatformRun(Request $request, int $reportRunId): JsonResponse
    {
        $run = $this->reports->platformRun(
            $this->requireCurrentUser(),
            $reportRunId
        );

        return $this->successResponse(
            'تم استرجاع حالة تقرير المنصة وبيانات الأثر الناتج.',
            ReportRunResource::make($this->reports->toData($run))->resolve($request)
        );
    }
}
