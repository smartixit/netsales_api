<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Services\Billing\BillingAuthorizationService;
use App\Services\Billing\Reports\BillingReportProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    private function csvResponse(string $reportType, ?int $networkId, array $filters): mixed
    {
        $provider = $this->providers->get($reportType);
        $definition = $provider->definition();
        $columns = $definition['columns'];
        return response()->streamDownload(function () use ($provider, $networkId, $filters, $columns): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_map(static fn ($column) => $column['label'] ?? $column['key'], $columns));
            foreach ($provider->exportRows($networkId, $filters) as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $key = $column['key'];
                    $value = data_get($row, $key);
                    if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (is_string($value) && ! is_numeric($value) && preg_match('/^[=+@\\-]/', $value)) $value = "'".$value;
                    $values[] = $value;
                }
                fputcsv($stream, $values);
            }
            fclose($stream);
        }, 'billing-'.$reportType.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function networkExportCsv(Request $request, int $networkId, string $reportType): mixed
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);
        return $this->csvResponse($reportType, $networkId, $this->filters($request));
    }

    public function platformExportCsv(Request $request, string $reportType): mixed
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());
        return $this->csvResponse($reportType, null, $this->filters($request));
    }

    public function platformSummary(Request $request, string $reportType): JsonResponse
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());
        return $this->successResponse('ملخص تقرير فوترة المنصة.', $this->providers->get($reportType)->summary(null, $this->filters($request)));
    }
}
