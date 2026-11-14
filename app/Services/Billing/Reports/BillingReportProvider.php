<?php

namespace App\Services\Billing\Reports;

use Illuminate\Contracts\Pagination\CursorPaginator;

interface BillingReportProvider
{
    public function key(): string;
    public function definition(): array;
    public function build(?int $networkId, array $filters): CursorPaginator;
    public function summary(?int $networkId, array $filters): array;
    public function exportRows(?int $networkId, array $filters): iterable;
    public function charts(array $summary): array;
}
