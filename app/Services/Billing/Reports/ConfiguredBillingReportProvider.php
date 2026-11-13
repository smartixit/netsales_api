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
            $query->where($condition[0], $condition[1], $condition[2]);
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
            $result['amounts_by_currency'] = (clone $query)
                ->select($this->config['currency'].' as currency_id')
                ->selectRaw('SUM('.$this->config['amount'].') as amount_minor')
                ->groupBy($this->config['currency'])
                ->orderBy($this->config['currency'])
                ->get()
                ->map(static fn ($row): array => [
                    'currency_id' => (int) $row->currency_id,
                    'amount_minor' => (int) $row->amount_minor,
                ])->all();
        }
        return $result;
    }
}
