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
use OpenApi\Attributes as OA;

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

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/billing/reports/catalog',
        summary: 'Network Catalog',
        tags: ['Billing'],
        operationId: 'billingreport_networkCatalog',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function networkCatalog(int $networkId): JsonResponse
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);

        return $this->successResponse('كتالوج تقارير فوترة الشبكة.', [
            'schema_version' => 1,
            'reports' => array_map(static fn ($p) => $p->definition(), $this->providers->all()),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/billing/reports/{reportType}',
        summary: 'Network Show',
        tags: ['Billing'],
        operationId: 'billingreport_networkShow',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'reportType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function networkShow(Request $request, int $networkId, string $reportType): JsonResponse
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);

        $provider = $this->providers->get($reportType);

        return $this->successResponse(
            'بيانات تقرير فوترة الشبكة.',
            $provider->build($networkId, $this->filters($request, $provider))
        );
    }

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/billing/reports/{reportType}/summary',
        summary: 'Network Summary',
        tags: ['Billing'],
        operationId: 'billingreport_networkSummary',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'reportType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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

    #[OA\Get(
        path: '/api/v1/platform/billing/reports/catalog',
        summary: 'Platform Catalog',
        tags: ['Billing'],
        operationId: 'billingreport_platformCatalog',
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function platformCatalog(): JsonResponse
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());

        return $this->successResponse('كتالوج تقارير فوترة المنصة.', [
            'schema_version' => 1,
            'reports' => array_map(static fn ($p) => $p->definition(), $this->providers->all()),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/reports/{reportType}',
        summary: 'Platform Show',
        tags: ['Billing'],
        operationId: 'billingreport_platformShow',
        parameters: [new OA\Parameter(name: 'reportType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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

    #[OA\Get(
        path: '/api/v1/networks/{networkId}/billing/reports/{reportType}/export.csv',
        summary: 'Network Export Csv',
        tags: ['Billing'],
        operationId: 'billingreport_networkExportCsv',
        parameters: [new OA\Parameter(name: 'networkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'reportType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function networkExportCsv(Request $request, int $networkId, string $reportType): mixed
    {
        $this->authorization->assertNetworkOwner($this->requireCurrentUser(), $networkId);

        return $this->csvResponse($reportType, $networkId, $this->filters($request, $this->providers->get($reportType)));
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/reports/{reportType}/export.csv',
        summary: 'Platform Export Csv',
        tags: ['Billing'],
        operationId: 'billingreport_platformExportCsv',
        parameters: [new OA\Parameter(name: 'reportType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
    public function platformExportCsv(Request $request, string $reportType): mixed
    {
        $this->authorization->assertPlatform($this->requireCurrentUser());

        return $this->csvResponse($reportType, null, $this->filters($request, $this->providers->get($reportType)));
    }

    #[OA\Get(
        path: '/api/v1/platform/billing/reports/{reportType}/summary',
        summary: 'Platform Summary',
        tags: ['Billing'],
        operationId: 'billingreport_platformSummary',
        parameters: [new OA\Parameter(name: 'reportType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'تم تنفيذ العملية بنجاح'), new OA\Response(response: 401, description: 'غير مصادق عليه'), new OA\Response(response: 403, description: 'غير مصرح'), new OA\Response(response: 404, description: 'المورد غير موجود'), new OA\Response(response: 422, description: 'بيانات غير صالحة')]
    )]
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
