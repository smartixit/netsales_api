<?php

namespace App\Services\Reports;

use App\Contracts\Reports\OperationalReportProvider;
use App\Enums\OperationalReportType;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

abstract class AbstractOperationalReportProvider implements OperationalReportProvider
{
    abstract protected function label(): string;

    abstract protected function description(): string;

    abstract protected function viewPermission(): string;

    abstract protected function exportPermission(): string;

    /**
     * @return list<string>
     */
    abstract protected function supportedScopes(): array;

    /**
     * forbidden | optional | required
     */
    abstract protected function posSelection(): string;

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function columns(): array;

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function metricSlots(): array;

    /**
     * @return list<string>
     */
    protected function statusOptions(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function saleTypeOptions(): array
    {
        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function filters(): array
    {
        $filters = [
            [
                'id' => 'from_date',
                'label' => 'من تاريخ',
                'kind' => 'date',
                'required' => false,
            ],
            [
                'id' => 'to_date',
                'label' => 'إلى تاريخ',
                'kind' => 'date',
                'required' => false,
            ],
            [
                'id' => 'search',
                'label' => 'بحث',
                'kind' => 'text',
                'required' => false,
                'max_length' => 120,
            ],
        ];

        $statuses = $this->statusOptions();
        if ($statuses !== []) {
            $filters[] = [
                'id' => 'status',
                'label' => 'الحالة',
                'kind' => 'single_choice',
                'required' => false,
                'choices' => $statuses,
            ];
        }

        $saleTypes = $this->saleTypeOptions();
        if ($saleTypes !== []) {
            $filters[] = [
                'id' => 'sale_type',
                'label' => 'نوع البيع / الطلب',
                'kind' => 'single_choice',
                'required' => false,
                'choices' => $saleTypes,
            ];
        }

        return $filters;
    }

    final public function definition(): array
    {
        $type = $this->type();

        return [
            'id' => $type->value,
            'schema_version' => 1,
            'title_key' => 'operational_reports.'.$type->value.'.title',
            'label' => $this->label(),
            'description' => $this->description(),
            'permissions' => [
                'view' => $this->viewPermission(),
                'export' => $this->exportPermission(),
            ],
            'supported_scopes' => $this->supportedScopes(),
            'pos_selection' => $this->posSelection(),
            'filters' => $this->filters(),
            'columns' => $this->columns(),
            'metric_slots' => $this->metricSlots(),
            'templates' => [
                [
                    'id' => 'netsales.operational-report.executive',
                    'version' => '1.0.0',
                ],
            ],
            'execution_policy' => 'server',
            'client_row_limit' => null,
            'charts_available' => false,
            'export_formats' => ['CSV'],
        ];
    }

    protected function applyDates(Builder $query, string $column, array $filters): Builder
    {
        return $query
            ->when(
                $filters['from_date'] ?? null,
                fn (Builder $q, string $date): Builder => $q->where($column, '>=', Carbon::parse($date)->startOfDay())
            )
            ->when(
                $filters['to_date'] ?? null,
                fn (Builder $q, string $date): Builder => $q->where($column, '<=', Carbon::parse($date)->endOfDay())
            );
    }

    protected function paginate(Builder $query, array $filters, string $dateColumn, string $key): CursorPaginator
    {
        $query->orderBy($dateColumn, 'desc')->orderBy($key, 'desc');

        return $query->cursorPaginate(
            (int) ($filters['per_page'] ?? 25),
            ['*'],
            'cursor',
            $filters['cursor'] ?? null
        );
    }

    protected function searchPattern(array $filters): ?string
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $search === '' ? null : '%'.$search.'%';
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<string, string>
     */
    protected function columnLabels(array $labels): array
    {
        return $labels;
    }
}
