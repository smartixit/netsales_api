<?php

namespace App\Http\Controllers\API\V1\Billing;

use App\Http\Controllers\API\V1\BaseController;
use App\Services\Billing\BillingAuthorizationService;
use App\Services\Billing\Reports\BillingReportProvider;
use App\Services\Billing\Reports\BillingReportProviderRegistry;
use App\Support\CsvValueSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BillingReportController extends BaseController
{
    public function __construct(
        private BillingAuthorizationService $authorization,
        private BillingReportProviderRegistry $providers
    ) {}

    /** @return array<string, mixed> */
    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, BillingReportProvider $provider): array
    {
        $filters = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'status' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);

        $supported = array_column($provider->definition()['filters'] ?? [], 'id');
        $unsupported = array_diff(
            array_intersect(array_keys($filters), ['from_date', 'to_date', 'status', 'search']),
            $supported
        );
        $errors = [];
        foreach ($unsupported as $filter) {
            $errors[$filter] = ['هذا المرشح غير مدعوم لنوع التقرير المحدد.'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $filters;
    }

    public function networkCatalog(int $networkId): JsonResponse
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);

        return $this->successResponse('كتالوج تقارير فوترة الشبكة.', [
            'schema_version' => 1,
            'reports' => array_map(static fn ($p) => $p->definition(), $this->providers->all()),
        ]);
    }

    public function networkShow(Request $request, int $networkId, string $reportType): JsonResponse
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);

        $provider = $this->providers->get($reportType);

        return $this->successResponse(
            'بيانات تقرير فوترة الشبكة.',
            $provider->build($networkId, $this->filters($request, $provider))
        );
    }

    public function networkSummary(Request $request, int $networkId, string $reportType): JsonResponse
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);
        $provider = $this->providers->get($reportType);
        $filters = $this->filters($request, $provider);
        $summary = $provider->summary($networkId, $filters);

        return $this->successResponse('ملخص تقرير فوترة الشبكة.', [
            'summary' => $summary,
            'charts' => $provider->charts($summary),
        ]);
    }

    public function platformCatalog(): JsonResponse
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());

        return $this->successResponse('كتالوج تقارير فوترة المنصة.', [
            'schema_version' => 1,
            'reports' => array_map(static fn ($p) => $p->definition(), $this->providers->all()),
        ]);
    }

    public function platformShow(Request $request, string $reportType): JsonResponse
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());

        $provider = $this->providers->get($reportType);

        return $this->successResponse(
            'بيانات تقرير فوترة المنصة.',
            $provider->build(null, $this->filters($request, $provider))
        );
    }

    /** @param array<string, mixed> $filters */
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
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                    $value = CsvValueSanitizer::sanitize($value);
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

        return $this->csvResponse($reportType, $networkId, $this->filters($request, $this->providers->get($reportType)));
    }

    public function platformExportCsv(Request $request, string $reportType): mixed
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());

        return $this->csvResponse($reportType, null, $this->filters($request, $this->providers->get($reportType)));
    }

    public function platformSummary(Request $request, string $reportType): JsonResponse
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());
        $provider = $this->providers->get($reportType);
        $filters = $this->filters($request, $provider);
        $summary = $provider->summary(null, $filters);

        return $this->successResponse('ملخص تقرير فوترة المنصة.', [
            'summary' => $summary,
            'charts' => $provider->charts($summary),
        ]);
    }
}
