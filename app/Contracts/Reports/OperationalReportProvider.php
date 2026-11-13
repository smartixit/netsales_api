<?php

namespace App\Contracts\Reports;

use App\Enums\OperationalReportType;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface OperationalReportProvider
{
    public function type(): OperationalReportType;

    /**
     * Public schema describing permitted client configuration.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator<int, mixed>
     */
    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator;

    /**
     * Authoritative metrics and chart series for the complete filtered dataset.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(User $user, int $networkId, ?int $posId, array $filters): array;

    /**
     * Stable CSV field IDs mapped to localized header labels.
     *
     * @return array<string, string>
     */
    public function exportColumns(): array;

    /**
     * Serialize a report row to the provider's stable export field IDs.
     *
     * @return array<string, mixed>
     */
    public function serializeExportRow(mixed $row): array;
}
