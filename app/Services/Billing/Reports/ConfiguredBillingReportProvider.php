<?php

namespace App\Services\Billing\Reports;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConfiguredBillingReportProvider implements BillingReportProvider
{
    public function __construct(private readonly array $config) {}

    public function key(): string { return $this->config['key']; }

    public function definition(): array
    {
        return [
            'id' => $this->key(),
            'label' => $this->config['label'],
            'description' => $this->config['description'],
            'schema_version' => 1,
            'scope' => ['NETWORK_OWNER', 'PLATFORM'],
            'filters' => [
                ['id' => 'from_date', 'kind' => 'date', 'label' => 'من تاريخ'],
                ['id' => 'to_date', 'kind' => 'date', 'label' => 'إلى تاريخ'],
                [
                    'id' => 'status',
                    'kind' => ! empty($this->config['status_choices']) ? 'single_choice' : 'text',
                    'label' => 'الحالة',
                    'choices' => array_map(static fn ($value): array => ['value' => $value, 'label_key' => 'billing.status.'.$value], $this->config['status_choices'] ?? []),
                ],
                ['id' => 'search', 'kind' => 'text', 'label' => 'بحث'],
            ],
            'columns' => $this->config['columns'],
            'metrics' => $this->config['metrics'] ?? [],
            'chart_slots' => $this->config['chart_slots'] ?? [],
            'supported_locale_tags' => ['ar-YE', 'en'],
            'metric_slots' => array_map(static fn ($metric): array => [
                'id' => $metric,
                'label_key' => 'billing.report.metric.'.$metric,
                'data_type' => 'integer',
            ], $this->config['metrics'] ?? []),
            'templates' => [[
                'id' => 'netsales.billing-report.'.$this->key().'.executive',
                'version' => ['major' => 1, 'minor' => 0, 'patch' => 0],
            ]],
            'page_profile_ids' => ['a4-portrait'],
            'execution_policy' => 'server',
            'export_formats' => ['CSV'],
            'currency_aggregation' => 'SEPARATE_CURRENCY_BUCKETS',
            'pagination' => 'CURSOR',
        ];
    }

    private function query(?int $networkId, array $filters): Builder
    {
        $query = DB::table($this->config['table'].' as r');
        foreach ($this->config['joins'] ?? [] as $join) {
            $query->leftJoin($join[0], $join[1], $join[2], $join[3]);
        }

        $scope = $this->config['scope'];
        if ($networkId !== null) {
            if ($scope['kind'] === 'network') {
                $query->where($scope['column'], $networkId);
            } elseif ($scope['kind'] === 'account') {
                $query->whereIn($scope['column'], DB::table('billing_accounts')->select('billing_account_id')->where('network_id', $networkId));
            } else {
                $query->where('ba.network_id', $networkId);
            }
        }

        foreach ($this->config['where'] ?? [] as $condition) {
            $operator = strtolower((string) $condition[1]);
            if ($operator === 'in') {
                $query->whereIn($condition[0], $condition[2]);
            } elseif ($operator === 'not in') {
                $query->whereNotIn($condition[0], $condition[2]);
            } elseif ($operator === 'is null') {
                $query->whereNull($condition[0]);
            } elseif ($operator === 'is not null') {
                $query->whereNotNull($condition[0]);
            } else {
                $query->where($condition[0], $condition[1], $condition[2]);
            }
        }
        $dateColumn = $this->config['date'];
        if (! empty($filters['from_date'])) $query->whereDate($dateColumn, '>=', $filters['from_date']);
        if (! empty($filters['to_date'])) $query->whereDate($dateColumn, '<=', $filters['to_date']);
        if (! empty($filters['status']) && ! empty($this->config['status'])) {
            $query->where($this->config['status'], strtoupper((string) $filters['status']));
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '' && ! empty($this->config['search'])) {
            $query->where(function (Builder $nested) use ($search): void {
                foreach ($this->config['search'] as $index => $column) {
                    $index === 0
                        ? $nested->where($column, 'like', '%'.$search.'%')
                        : $nested->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        return $query;
    }

    public function build(?int $networkId, array $filters): CursorPaginator
    {
        $query = $this->query($networkId, $filters);
        $query->select($this->config['select']);
        return $query
            ->orderByDesc($this->config['date'])
            ->orderByDesc($this->config['key_column'])
            ->cursorPaginate(min(max((int) ($filters['per_page'] ?? 25), 1), 100), ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    public function exportRows(?int $networkId, array $filters): iterable
    {
        $query = $this->query($networkId, $filters);
        $query->select($this->config['select']);
        return $query->orderByDesc($this->config['date'])
            ->orderByDesc($this->config['key_column'])
            ->cursor();
    }

    public function summary(?int $networkId, array $filters): array
    {
        $query = $this->query($networkId, $filters);
        $count = (clone $query)->count();
        $result = ['record_count' => $count, 'amounts_by_currency' => []];
        if (! empty($this->config['amount']) && ! empty($this->config['currency'])) {
            $currencyQuery = (clone $query)->leftJoin(
                'currencies as billing_report_currency',
                'billing_report_currency.currency_id',
                '=',
                $this->config['currency']
            );
            $result['amounts_by_currency'] = $currencyQuery
                ->select($this->config['currency'].' as currency_id')
                ->addSelect('billing_report_currency.code as currency_code', 'billing_report_currency.name as currency_name', 'billing_report_currency.decimal_places')
                ->selectRaw('SUM('.$this->config['amount'].') as amount_minor')
                ->groupBy($this->config['currency'], 'billing_report_currency.code', 'billing_report_currency.name', 'billing_report_currency.decimal_places')
                ->orderBy($this->config['currency'])
                ->get()
                ->map(static fn ($row): array => [
                    'currency_id' => (int) $row->currency_id,
                    'currency_code' => $row->currency_code,
                    'currency_name' => $row->currency_name,
                    'decimal_places' => (int) $row->decimal_places,
                    'amount_minor' => (int) $row->amount_minor,
                ])->all();
        }
        return $result;
    }
}
