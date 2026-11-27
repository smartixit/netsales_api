<?php

namespace App\Services\Billing\Reports;

use Illuminate\Contracts\Pagination\CursorPaginator;

interface BillingReportProvider
{
    public function key(): string;

    /** @return array<string, mixed> */
    public function definition(): array;

    /** @param array<string, mixed> $filters @return CursorPaginator<int, object> */
    public function build(?int $networkId, array $filters): CursorPaginator;

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function summary(?int $networkId, array $filters): array;

    /** @param array<string, mixed> $filters @return iterable<object> */
    public function exportRows(?int $networkId, array $filters): iterable;

    /** @param array<string, mixed> $summary @return list<array<string, mixed>> */
    public function charts(array $summary): array;
}
