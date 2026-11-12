<?php

namespace App\Services\Reports;

use App\Contracts\Reports\OperationalReportProvider;
use App\Enums\OperationalReportType;
use App\Models\User;
use App\Services\OperationalReportQueryService;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class OperationalReportProviderAdapter implements OperationalReportProvider
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        private readonly OperationalReportType $reportType,
        private readonly array $definition,
        private readonly OperationalReportQueryService $queries
    ) {}

    public function type(): OperationalReportType
    {
        return $this->reportType;
    }

    public function definition(): array
    {
        return $this->definition;
    }

    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator|array
    {
        return $this->queries->buildLegacy($user, $this->reportType, $networkId, $posId, $filters);
    }

    public function summary(User $user, int $networkId, ?int $posId, array $filters): array
    {
        return $this->queries->summaryLegacy($this->reportType, $networkId, $posId, $filters);
    }

    public function emptyExportColumns(): array
    {
        return $this->definition['empty_export_columns'] ?? [];
    }
}
