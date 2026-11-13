<?php

namespace App\\Services\\Reports\\Providers;

use App\\Enums\\OperationalReportType;
use App\\Models\\SaleInvoice;
use App\\Models\\User;
use App\\Services\\Reports\\AbstractOperationalReportProvider;
use Illuminate\\Contracts\\Pagination\\CursorPaginator;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Support\\Carbon;

final class DebtAgingReportProvider extends AbstractOperationalReportProvider
{
    public function type(): OperationalReportType
    {
        return OperationalReportType::DEBT_AGING;
    }

    protected function label(): string
    {
        return 'تقرير أعمار الديون';
    }

    protected function description(): string
    {
        return 'الفواتير ذات الأرصدة المتبقية مع تصنيف عمر الدين.';
    }

    protected function viewPermission(): string
    {
        return 'sales.report';
    }

    protected function exportPermission(): string
    {
        return 'sales.export';
    }

    protected function scopes(): array
    {
        return ['network', 'pos'];
    }

    protected function posSelection(): string
    {
        return 'optional';
    }

    protected function statusChoices(): array
    {
        return ['PARTIALLY_PAID', 'UNPAID'];
    }

    protected function columns(): array
    {
        return [
            ['id' => 'invoice_id', 'label' => 'رقم الفاتورة', 'data_type' => 'integer'],
            ['id' => 'invoice_uuid', 'label' => 'مرجع الفاتورة', 'data_type' => 'text'],
            ['id' => 'invoice_date', 'label' => 'التاريخ', 'data_type' => 'date'],
            ['id' => 'pos_name', 'label' => 'نقطة البيع', 'data_type' => 'text'],
            ['id' => 'invoice_total_amount', 'label' => 'الإجمالي', 'data_type' => 'decimal'],
            ['id' => 'paid_amount', 'label' => 'المدفوع', 'data_type' => 'decimal'],
            ['id' => 'remaining_amount', 'label' => 'المتبقي', 'data_type' => 'decimal'],
            ['id' => 'age_days', 'label' => 'عمر الدين', 'data_type' => 'integer'],
            ['id' => 'aging_bucket', 'label' => 'شريحة العمر', 'data_type' => 'text'],
        ];
    }

    protected function metricSlots(): array
    {
        return [
            ['id' => 'record_count', 'label' => 'عدد الفواتير', 'data_type' => 'integer'],
            ['id' => 'total_outstanding', 'label' => 'إجمالي المديونية', 'data_type' => 'decimal'],
            ['id' => 'bucket_0_30', 'label' => '0–30 يوماً', 'data_type' => 'decimal'],
            ['id' => 'bucket_31_60', 'label' => '31–60 يوماً', 'data_type' => 'decimal'],
            ['id' => 'bucket_61_90', 'label' => '61–90 يوماً', 'data_type' => 'decimal'],
            ['id' => 'bucket_over_90', 'label' => 'أكثر من 90 يوماً', 'data_type' => 'decimal'],
        ];
    }

    protected function summaryCharts(): array
    {
        return [[
            'id' => 'debt_aging',
            'label' => 'توزيع المديونية حسب العمر',
            'chart_type' => 'bar',
            'value_key' => 'amount',
            'label_key' => 'bucket',
        ]];
    }

    private function query(int $networkId, ?int $posId, array $filters): Builder
    {
        $pattern = $this->pattern($filters);

        $query = SaleInvoice::query()
            ->with('pos')
            ->where('network_id', $networkId)
            ->where('remaining_amount', '>', 0)
            ->when($posId !== null, fn (Builder $query): Builder => $query->where('pos_id', $posId))
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('payment_status', $status)
            )
            ->when(
                $pattern !== null,
                fn (Builder $query): Builder => $query->where('invoice_uuid', 'like', $pattern)
            );

        return $this->applyDates($query, 'invoice_date', $filters);
    }

    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        return $this->paginate(
            $this->query($networkId, $posId, $filters),
            $filters,
            'invoice_date',
            'invoice_id'
        );
    }

    /**
     * @return array{age_days: int, aging_bucket: string}
     */
    private function aging(SaleInvoice $invoice): array
    {
        $ageDays = max(
            0,
            (int) Carbon::parse($invoice->invoice_date)
                ->startOfDay()
                ->diffInDays(now('Asia/Aden')->startOfDay(), false)
        );

        $bucket = match (true) {
            $ageDays <= 30 => '0_30',
            $ageDays <= 60 => '31_60',
            $ageDays <= 90 => '61_90',
            default => 'over_90',
        };

        return [
            'age_days' => $ageDays,
            'aging_bucket' => $bucket,
        ];
    }

    public function summary(User $user, int $networkId, ?int $posId, array $filters): array
    {
        $query = $this->query($networkId, $posId, $filters);
        $today = now('Asia/Aden')->startOfDay();
        $day30 = $today->copy()->subDays(30);
        $day60 = $today->copy()->subDays(60);
        $day90 = $today->copy()->subDays(90);

        $bucketMinorUnits = [
            '0_30' => $this->toMinorUnits(
                (clone $query)
                    ->where('invoice_date', '>=', $day30)
                    ->where('invoice_date', '<=', $today->copy()->endOfDay())
                    ->sum('remaining_amount')
            ),
            '31_60' => $this->toMinorUnits(
                (clone $query)
                    ->where('invoice_date', '<', $day30)
                    ->where('invoice_date', '>=', $day60)
                    ->sum('remaining_amount')
            ),
            '61_90' => $this->toMinorUnits(
                (clone $query)
                    ->where('invoice_date', '<', $day60)
                    ->where('invoice_date', '>=', $day90)
                    ->sum('remaining_amount')
            ),
            'over_90' => $this->toMinorUnits(
                (clone $query)
                    ->where('invoice_date', '<', $day90)
                    ->sum('remaining_amount')
            ),
        ];

        $totalMinorUnits = 0;
        foreach ($bucketMinorUnits as $bucketAmount) {
            $totalMinorUnits = $this->addMinorUnits($totalMinorUnits, $bucketAmount);
        }

        $formattedBuckets = array_map(
            fn (int $amount): string => $this->fromMinorUnits($amount),
            $bucketMinorUnits
        );

        return [
            'record_count' => (clone $query)->count(),
            'total_outstanding' => $this->fromMinorUnits($totalMinorUnits),
            'bucket_0_30' => $formattedBuckets['0_30'],
            'bucket_31_60' => $formattedBuckets['31_60'],
            'bucket_61_90' => $formattedBuckets['61_90'],
            'bucket_over_90' => $formattedBuckets['over_90'],
            'currency_code' => $this->currencyCode($networkId),
            'charts' => [
                'debt_aging' => [
                    ['bucket' => '0–30', 'amount' => $formattedBuckets['0_30']],
                    ['bucket' => '31–60', 'amount' => $formattedBuckets['31_60']],
                    ['bucket' => '61–90', 'amount' => $formattedBuckets['61_90']],
                    ['bucket' => '>90', 'amount' => $formattedBuckets['over_90']],
                ],
            ],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'invoice_id' => 'رقم الفاتورة',
            'invoice_uuid' => 'مرجع الفاتورة',
            'invoice_date' => 'التاريخ',
            'pos_name' => 'نقطة البيع',
            'invoice_total_amount' => 'الإجمالي',
            'paid_amount' => 'المدفوع',
            'remaining_amount' => 'المتبقي',
            'age_days' => 'عمر الدين',
            'aging_bucket' => 'شريحة العمر',
        ];
    }

    public function serializeExportRow(mixed $row): array
    {
        return [
            'invoice_id' => $row->invoice_id,
            'invoice_uuid' => $row->invoice_uuid,
            'invoice_date' => $this->dateValue($row->invoice_date),
            'pos_name' => $row->pos?->pos_name,
            'invoice_total_amount' => $this->amountValue($row->invoice_total_amount),
            'paid_amount' => $this->amountValue($row->paid_amount),
            'remaining_amount' => $this->amountValue($row->remaining_amount),
            ...$this->aging($row),
        ];
    }
}
