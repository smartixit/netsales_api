<?php

namespace App\Http\Controllers\API\V1\Reporting;

use App\Http\Controllers\API\V1\BaseController;
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

    public function storeNetworkRun(Request $request, int $networkId): JsonResponse
    {
        $input = $request->validate([
            'report_key' => ['required', 'string', 'max:120'],
            'filters' => ['sometimes', 'array'],
        ]);

        $run = $this->reports->queueNetworkReport(
            $this->requireCurrentUser(),
            $networkId,
            (string) $input['report_key'],
            (array) ($input['filters'] ?? [])
        );

        return $this->successResponse(
            'تم تسجيل التقرير في قائمة التنفيذ. يمكنك متابعة حالته وتنزيله عند اكتماله.',
            $this->reports->present($run),
            202
        );
    }

    public function showNetworkRun(int $networkId, int $reportRunId): JsonResponse
    {
        $run = $this->reports->networkRun(
            $this->requireCurrentUser(),
            $networkId,
            $reportRunId
        );

        return $this->successResponse(
            'تم استرجاع حالة التقرير وبيانات الأثر الناتج.',
            $this->reports->present($run)
        );
    }

    public function platformCatalog(): JsonResponse
    {
        return $this->successResponse(
            'تم استرجاع كتالوج تقارير فوترة المنصة.',
            $this->reports->platformCatalog($this->requireCurrentUser())
        );
    }

    public function storePlatformRun(Request $request): JsonResponse
    {
        $input = $request->validate([
            'report_key' => ['required', 'string', 'max:120'],
            'filters' => ['sometimes', 'array'],
        ]);

        $run = $this->reports->queuePlatformReport(
            $this->requireCurrentUser(),
            (string) $input['report_key'],
            (array) ($input['filters'] ?? [])
        );

        return $this->successResponse(
            'تم تسجيل تقرير المنصة في قائمة التنفيذ. يمكنك متابعة حالته وتنزيله عند اكتماله.',
            $this->reports->present($run),
            202
        );
    }

    public function showPlatformRun(int $reportRunId): JsonResponse
    {
        $run = $this->reports->platformRun(
            $this->requireCurrentUser(),
            $reportRunId
        );

        return $this->successResponse(
            'تم استرجاع حالة تقرير المنصة وبيانات الأثر الناتج.',
            $this->reports->present($run)
        );
    }
}
