<?php

namespace App\Contracts\Reports;

use App\Enums\OperationalReportType;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface OperationalReportProvider
{
    public function type(): OperationalReportType;

    /**
     * Public configuration schema. It describes permitted UI choices only;
     * authorization and execution remain enforced by Laravel.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator<int, mixed>|array<int, mixed>
     */
    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator|array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float|string>
     */
    public function summary(User $user, int $networkId, ?int $posId, array $filters): array;

    /**
     * @return array<string, string>
     */
    public function emptyExportColumns(): array;
}
