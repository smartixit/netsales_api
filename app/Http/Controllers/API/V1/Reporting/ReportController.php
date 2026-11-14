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

class ReportController extends BaseController
{
    public function __construct(
        private ReportRunService $reports
    ) {}

    public function networkCatalog(int $networkId): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع كتالوج التقارير المتاحة للشبكة.',
            $this->reports->networkCatalog($this->requireCurrentUser(), $networkId)
        );
    }

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

    public function platformCatalog(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع كتالوج تقارير فوترة المنصة.',
            $this->reports->platformCatalog($this->requireCurrentUser())
        );
    }

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
