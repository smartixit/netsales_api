<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Services\Billing\BillingAuthorizationService;
use App\Services\Billing\Reports\BillingReportProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillingReportController extends BaseController
{
    public function __construct(
        private BillingAuthorizationService $authorization,
        private BillingReportProviderRegistry $providers
    ) {}

    private function filters(Request $request): array
    {
        return $request->validate([
            'from_date' => ['nullable','date_format:Y-m-d'],
            'to_date' => ['nullable','date_format:Y-m-d','after_or_equal:from_date'],
            'status' => ['nullable','string','max:40'],
            'search' => ['nullable','string','max:120'],
            'per_page' => ['sometimes','integer','min:1','max:100'],
            'cursor' => ['nullable','string','max:2048'],
        ]);
    }

    public function networkCatalog(int $networkId): JsonResponse
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);
        return $this->successResponse('كتالوج تقارير فوترة الشبكة.', [
            'schema_version'=>1,
            'reports'=>array_map(static fn ($p) => $p->definition(), $this->providers->all()),
        ]);
    }

    public function networkShow(Request $request, int $networkId, string $reportType): JsonResponse
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);
        return $this->successResponse('بيانات تقرير فوترة الشبكة.', $this->providers->get($reportType)->build($networkId, $this->filters($request)));
    }

    public function networkSummary(Request $request, int $networkId, string $reportType): JsonResponse
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);
        return $this->successResponse('ملخص تقرير فوترة الشبكة.', $this->providers->get($reportType)->summary($networkId, $this->filters($request)));
    }

    public function platformCatalog(): JsonResponse
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());
        return $this->successResponse('كتالوج تقارير فوترة المنصة.', [
            'schema_version'=>1,
            'reports'=>array_map(static fn ($p) => $p->definition(), $this->providers->all()),
        ]);
    }

    public function platformShow(Request $request, string $reportType): JsonResponse
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());
        return $this->successResponse('بيانات تقرير فوترة المنصة.', $this->providers->get($reportType)->build(null, $this->filters($request)));
    }

    public function platformSummary(Request $request, string $reportType): JsonResponse
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());
        return $this->successResponse('ملخص تقرير فوترة المنصة.', $this->providers->get($reportType)->summary(null, $this->filters($request)));
    }
}
