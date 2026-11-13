<?php

namespace App\Services\Reports\Providers;

use App\Enums\OperationalReportType;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Services\Reports\AbstractOperationalReportProvider;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class SalesReportProvider extends AbstractOperationalReportProvider
{
    public function type(): OperationalReportType { return OperationalReportType::SALES; }
    protected function label(): string { return 'تقرير المبيعات والفواتير'; }
    protected function description(): string { return 'فواتير البيع ومبالغها وحالة السداد ضمن نطاق الشبكة ونقطة البيع.'; }
    protected function viewPermission(): string { return 'sales.report'; }
    protected function exportPermission(): string { return 'sales.export'; }
    protected function scopes(): array { return ['network', 'pos']; }
    protected function posSelection(): string { return 'optional'; }
    protected function statusChoices(): array { return ['PAID', 'PARTIALLY_PAID', 'UNPAID']; }
    protected function saleTypeChoices(): array { return ['CASH', 'CREDIT']; }

    protected function columns(): array
    {
        return [
            ['id' => 'invoice_id', 'label' => 'رقم الفاتورة', 'data_type' => 'integer'],
            ['id' => 'invoice_uuid', 'label' => 'مرجع الفاتورة', 'data_type' => 'text'],
            ['id' => 'invoice_date', 'label' => 'تاريخ الفاتورة', 'data_type' => 'date'],
            ['id' => 'pos_name', 'label' => 'نقطة البيع', 'data_type' => 'text'],
            ['id' => 'sale_type', 'label' => 'نوع البيع', 'data_type' => 'text'],
            ['id' => 'payment_status', 'label' => 'حالة السداد', 'data_type' => 'text'],
            ['id' => 'invoice_total_amount', 'label' => 'الإجمالي', 'data_type' => 'decimal'],
            ['id' => 'paid_amount', 'label' => 'المدفوع', 'data_type' => 'decimal'],
            ['id' => 'remaining_amount', 'label' => 'المتبقي', 'data_type' => 'decimal'],
            ['id' => 'created_by_name', 'label' => 'أُصدرت بواسطة', 'data_type' => 'text'],
        ];
    }

    protected function metricSlots(): array
    {
        return [
            ['id' => 'record_count', 'label' => 'عدد الفواتير', 'data_type' => 'integer'],
            ['id' => 'total_amount', 'label' => 'إجمالي المبيعات', 'data_type' => 'decimal'],
            ['id' => 'paid_amount', 'label' => 'المبالغ المسددة', 'data_type' => 'decimal'],
            ['id' => 'remaining_amount', 'label' => 'المبالغ المتبقية', 'data_type' => 'decimal'],
        ];
    }

    protected function summaryCharts(): array
    {
        return [
            ['id' => 'sales_by_day', 'label' => 'المبيعات حسب اليوم', 'chart_type' => 'line', 'value_key' => 'amount', 'label_key' => 'date'],
            ['id' => 'sales_by_payment_status', 'label' => 'توزيع الفواتير حسب حالة السداد', 'chart_type' => 'bar', 'value_key' => 'record_count', 'label_key' => 'status'],
        ];
    }

    private function query(int $networkId, ?int $posId, array $filters): Builder
    {
        $pattern = $this->pattern($filters);
        $query = SaleInvoice::query()->with(['pos', 'createdBy'])
            ->where('network_id', $networkId)
            ->when($posId !== null, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['sale_type'] ?? null, fn (Builder $q, string $v): Builder => $q->where('sale_type', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('payment_status', $v))
            ->when($pattern !== null, fn (Builder $q): Builder => $q->where(
                fn (Builder $nested): Builder => $nested
                   ->where('invoice_uuid', 'like', $pattern)
                    ->orWhereHas('pos', fn (Builder $pos): Builder => $pos->where('pos_name', 'like', $pattern))
            ));

        return $this->applyDates($query, 'invoice_date', $filters);
    }

    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        return $this->paginate($this->query($networkId, $posId, $filters), $filters, 'invoice_date', 'invoice_id');
    }

    public function summary(User $user, int $networkId, ?int $posId, array $filters): array
    {
        $query = $this->query($networkId, $posId, $filters);
        $daily = (clone $query)->reorder()
            ->selectRaw('DATE(invoice_date) as date, SUM(invoice_total_amount) as amount')
            ->groupByRaw('DATE(invoice_date)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => ['date' => (string) $row->date, 'amount' => $this->amountValue($row->amount)])
            ->all();

        $statuses = (clone $query)->reorder()
            ->selectRaw('payment_status as status, COUNT(*) as record_count')
            ->groupBy('payment_status')
            ->orderBy('payment_status')
            ->get()
            ->map(fn ($row): array => ['status' => (string) $row->status, 'record_count' => (int) $row->record_count])
            ->all();

        return [
            'record_count' => (clone $query)->count(),
            'total_amount' => $this->amountValue((clone $query)->sum('invoice_total_amount')),
            'paid_amount' => $this->amountValue((clone $query)->sum('paid_amount')),
            'remaining_amount' => $this->amountValue((clone $query)->sum('remaining_amount')),
            'currency_code' => $this->currencyCode($networkId),
            'charts' => [
                'sales_by_day' => $daily,
                'sales_by_payment_status' => $statuses,
            ],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'invoice_id' => 'رقم الفاتورة',
            'invoice_uuid' => 'مرجع الفاتورة',
            'invoice_date' => 'تاريخ الفاتورة',
            'pos_name' => 'نقطة البيع',
            'sale_type' => 'نوع البيع',
            'payment_status' => 'حالة السداد',
            'invoice_total_amount' => 'الإجمالي',
            'paid_amount' => 'المدفوع',
            'remaining_amount' => 'المتبقي',
            'created_by_name' => 'أُصدرت بواسطة',
        ];
    }

    public function serializeExportRow(mixed $row): array
    {
        return [
            'invoice_id' => $row->invoice_id,
            'invoice_uuid' => $row->invoice_uuid,
            'invoice_date' => $this->dateValue($row->invoice_date),
            'pos_name' => $row->pos?->pos_name,
            'sale_type' => $this->enumValue($row->sale_type),
            'payment_status' => $this->enumValue($row->payment_status),
            'invoice_total_amount' => $this->amountValue($row->invoice_total_amount),
            'paid_amount' => $this->amountValue($row->paid_amount),
            'remaining_amount' => $this->amountValue($row->remaining_amount),
            'created_by_name' => $row->createdBy?->user_full_name,
        ];
    }
}
