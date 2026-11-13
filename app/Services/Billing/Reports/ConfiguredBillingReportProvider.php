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
            'filters' => array_values(array_filter([
                ['id' => 'from_date', 'kind' => 'date', 'label' => 'من تاريخ'],
                ['id' => 'to_date', 'kind' => 'date', 'label' => 'إلى تاريخ'],
                [
                    'id' => 'status',
                    'kind' => ! empty($this->config['status_choices']) ? 'single_choice' : 'text',
                    'label' => 'الحالة',
                    'choices' => array_map(static fn ($value): array => ['value' => $value, 'label_key' => 'billing.status.'.$value], $this->config['status_choices'] ?? []),
                ],
                ['id' => 'search', 'kind' => 'text', 'label' => 'بحث'],
            ], fn (array $filter): bool => in_array($filter['id'], $this->config['filter_ids'] ?? ['from_date','to_date','status','search'], true))),
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
            'export_formats' => ['CSV', 'PDF'],
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

    public function charts(array $summary): array
    {
        $pie = static function (string $id, string $title, array $items, string $labelKey, string $valueKey): array {
            return [
                'id' => $id, 'type' => 'pie', 'title' => $title,
                'data' => array_map(static fn ($item): array => [
                    'label' => (string) ($item[$labelKey] ?? '—'),
                    'value' => (int) ($item[$valueKey] ?? 0),
                ], $items),
            ];
        };
        $bar = static function (string $id, string $title, string $currencyCode, array $items, array $series): array {
            return [
                'id' => $id, 'type' => 'bar', 'title' => $title.' — '.$currencyCode,
                'series' => $series,
                'data' => $items,
            ];
        };

        return match ($this->key()) {
            'subscriptions' => [$pie('subscription_status_distribution', 'توزيع حالات الاشتراك', $summary['status_distribution'] ?? [], 'status', 'count')],
            'trials' => [$pie('trial_status_distribution', 'توزيع حالات التجارب', $summary['status_distribution'] ?? [], 'status', 'count')],
            'trial_conversion' => array_merge([[
                'id' => 'trial_conversion_funnel', 'type' => 'bar', 'title' => 'قمع تحويل التجربة',
                'series' => [['key' => 'count', 'label' => 'عدد الحالات']],
                'data' => array_map(static fn ($key, $value): array => ['label' => str_replace('_', ' ', $key), 'count' => (int) $value], array_keys($summary['funnel'] ?? []), array_values($summary['funnel'] ?? [])),
            ]], [[
                'id' => 'conversion_by_plan', 'type' => 'bar', 'title' => 'أهلية التجربة حسب الخطة والسعر',
                'series' => [['key' => 'eligible_checks', 'label' => 'حسابات مؤهلة']],
                'data' => array_map(static fn ($item): array => [
                    'label' => 'Plan '.$item['plan_id'].' / Price '.$item['price_id'],
                    'eligible_checks' => (int) $item['eligible_checks'],
                ], $summary['eligible_by_policy_plan_price'] ?? []),
            ]]),
            'renewals' => [$pie('renewal_outcomes', 'نتائج أحداث التجديد', $summary['event_distribution'] ?? [], 'event_type', 'count')],
            'plan_performance' => collect($summary['mrr_arr_by_plan_and_currency'] ?? [])->groupBy('currency_code')->map(
                fn ($items, $currency): array => $bar('mrr_arr_'.$currency, 'الإيراد الشهري والسنوي حسب الخطة', (string) $currency, $items->map(static fn ($item): array => [
                    'label' => $item['plan_name'], 'mrr' => (int) $item['mrr_minor'], 'arr' => (int) $item['arr_minor'],
                ])->values()->all(), [['key' => 'mrr', 'label' => 'MRR'], ['key' => 'arr', 'label' => 'ARR']])
            )->values()->all(),
            'invoices' => array_merge(
                [$pie('invoice_status_distribution', 'توزيع حالات الفواتير', $summary['status_distribution'] ?? [], 'status', 'count')],
                collect($summary['amounts_by_currency'] ?? [])->map(
                    fn ($item): array => $bar('invoice_amounts_'.$item['currency_code'], 'الفواتير والرصيد المستحق', (string) $item['currency_code'], [[
                        'label'=>'الفواتير','invoiced'=>(int)$item['invoiced_minor'],
                        'credited'=>(int)$item['credited_minor'],'outstanding'=>(int)$item['outstanding_minor'],
                    ]], [['key'=>'invoiced','label'=>'إجمالي الفواتير'],['key'=>'credited','label'=>'الإشعارات الدائنة'],['key'=>'outstanding','label'=>'المستحق']])
                )->all()
            ),
            'collections' => array_merge(
                [$pie('collection_status_distribution', 'حالات التحصيل', $summary['collection_statuses'] ?? [], 'status', 'count')],
                collect($summary['collections_by_currency'] ?? [])->map(
                    fn ($item): array => $bar('collections_'.$item['currency_code'], 'إجمالي وصافي التحصيل', (string) $item['currency_code'], [[
                        'label'=>'التحصيل','gross'=>(int)$item['gross_collected_minor'],
                        'refunded'=>(int)$item['refunded_minor'],'net'=>(int)$item['net_collected_minor'],
                    ]], [['key'=>'gross','label'=>'إجمالي التحصيل'],['key'=>'refunded','label'=>'المسترد'],['key'=>'net','label'=>'الصافي']])
                )->all()
            ),
            'receivables' => collect($summary['aging_by_currency'] ?? [])->map(
                fn ($item): array => $bar('receivables_aging_'.$item['currency_code'], 'أعمار الذمم المدينة', (string) $item['currency_code'], [[
                    'label' => 'الذمم المستحقة',
                    'current' => (int) $item['current_minor'],
                    'days_1_30' => (int) $item['days_1_30_minor'],
                    'days_31_60' => (int) $item['days_31_60_minor'],
                    'days_61_90' => (int) $item['days_61_90_minor'],
                    'over_90' => (int) $item['over_90_minor'],
                ]], [['key'=>'current','label'=>'غير متأخر'],['key'=>'days_1_30','label'=>'1–30'],['key'=>'days_31_60','label'=>'31–60'],['key'=>'days_61_90','label'=>'61–90'],['key'=>'over_90','label'=>'+90']])
            )->values()->all(),
            'usage' => collect($summary['amounts_by_currency'] ?? [])->map(
                fn ($item): array => $bar('usage_amount_'.$item['currency_code'], 'قيمة الاستخدام', (string) $item['currency_code'], [[
                    'label'=>'الاستخدام','amount'=>(int)$item['amount_minor'],
                ]], [['key'=>'amount','label'=>'قيمة الاستخدام']])
            )->values()->all(),
            'dunning' => [$pie('dunning_outcomes', 'نتائج محاولات التحصيل', $summary['status_distribution'] ?? [], 'status', 'count')],
            'refunds' => collect($summary['amounts_by_currency'] ?? [])->map(
                fn ($item): array => $bar('refunds_'.$item['currency_code'], 'مبالغ الاسترداد', (string) $item['currency_code'], [[
                    'label'=>'الاستردادات','amount'=>(int)$item['amount_minor'],
                ]], [['key'=>'amount','label'=>'المبلغ المسترد']])
            )->values()->all(),
            'revenue_recognition' => collect($summary['revenue_by_currency'] ?? [])->map(
                fn ($item): array => $bar('revenue_'.$item['currency_code'], 'الإيراد المعترف به والمؤجل', (string) $item['currency_code'], [[
                    'label'=>'الإيراد','recognized'=>(int)$item['recognized_in_period_minor'],'deferred'=>(int)$item['deferred_revenue_minor'],
                ]], [['key'=>'recognized','label'=>'معترف به'],['key'=>'deferred','label'=>'مؤجل']])
            )->values()->all(),
            default => [],
        };
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
                ->addSelect(['billing_report_currency.code as currency_code', 'billing_report_currency.name as currency_name', 'billing_report_currency.decimal_places'])
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
