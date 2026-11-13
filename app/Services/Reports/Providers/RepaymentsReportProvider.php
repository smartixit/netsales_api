<?php

namespace App\Services\Reports\Providers;

use App\Enums\OperationalReportType;
use App\Models\PosRepayment;
use App\Models\User;
use App\Services\Reports\AbstractOperationalReportProvider;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class RepaymentsReportProvider extends AbstractOperationalReportProvider
{
    public function type(): OperationalReportType
    {
        return OperationalReportType::REPAYMENTS;
    }

    protected function label(): string
    {
        return 'تقرير سندات السداد';
    }

    protected function description(): string
    {
        return 'سندات السداد مع مراجعها وبيانات العكس.';
    }

    protected function viewPermission(): string
    {
        return 'pos_wallets.report';
    }

    protected function exportPermission(): string
    {
        return 'pos_wallets.export';
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
        return ['COMPLETED', 'REVERSED'];
    }

    protected function statusFilterLabel(): string
    {
        return 'حالة السند';
    }

    protected function columns(): array
    {
        return [
            ['id' => 'repayment_id', 'label' => 'رقم السند', 'data_type' => 'integer'],
            ['id' => 'repayment_uuid', 'label' => 'مرجع السند', 'data_type' => 'text'],
            ['id' => 'repayment_date', 'label' => 'التاريخ', 'data_type' => 'date'],
            ['id' => 'pos_name', 'label' => 'نقطة البيع', 'data_type' => 'text'],
            ['id' => 'amount', 'label' => 'المبلغ', 'data_type' => 'decimal'],
            ['id' => 'status', 'label' => 'الحالة', 'data_type' => 'text'],
            ['id' => 'payment_method', 'label' => 'وسيلة الدفع', 'data_type' => 'text'],
            ['id' => 'reference_number', 'label' => 'المرجع الخارجي', 'data_type' => 'text'],
            ['id' => 'reversal_reason', 'label' => 'سبب العكس', 'data_type' => 'text'],
        ];
    }

    protected function metricSlots(): array
    {
        return [
            ['id' => 'record_count', 'label' => 'عدد السندات', 'data_type' => 'integer'],
            ['id' => 'completed_amount', 'label' => 'السداد المكتمل', 'data_type' => 'decimal'],
            ['id' => 'reversed_amount', 'label' => 'المبالغ المعكوسة', 'data_type' => 'decimal'],
        ];
    }

    protected function summaryCharts(): array
    {
        return [[
            'id' => 'repayments_by_status',
            'label' => 'السندات حسب الحالة',
            'chart_type' => 'bar',
            'value_key' => 'amount',
            'label_key' => 'status',
        ]];
    }

    private function query(int $networkId, ?int $posId, array $filters): Builder
    {
        $pattern = $this->pattern($filters);

        $query = PosRepayment::query()
            ->with(['pos', 'createdBy'])
            ->where('network_id', $networkId)
            ->when($posId !== null, fn (Builder $query): Builder => $query->where('pos_id', $posId))
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('status', $status)
            )
            ->when(
                $pattern !== null,
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $nested): Builder => $nested
                        ->where('repayment_uuid', 'like', $pattern)
                        ->orWhere('reference_number', 'like', $pattern)
                        ->orWhere('notes', 'like', $pattern)
                )
            );

        return $this->applyDates($query, 'repayment_date', $filters);
    }

    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        return $this->paginate(
            $this->query($networkId, $posId, $filters),
            $filters,
            'repayment_date',
            'repayment_id'
        );
    }

    public function summary(User $user, int $networkId, ?int $posId, array $filters): array
    {
        $query = $this->query($networkId, $posId, $filters);
        $groups = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as record_count, SUM(amount) as amount')
            ->groupBy('status')
            ->get();

        $completedAmount = (clone $query)
            ->where('status', 'COMPLETED')
            ->sum('amount');
        $reversedAmount = (clone $query)
            ->where('status', 'REVERSED')
            ->sum('amount');

        return [
            'record_count' => (clone $query)->count(),
            'total_amount' => $this->amountValue($completedAmount),
            'completed_count' => (clone $query)->where('status', 'COMPLETED')->count(),
            'completed_amount' => $this->amountValue($completedAmount),
            'reversed_count' => (clone $query)->where('status', 'REVERSED')->count(),
            'reversed_amount' => $this->amountValue($reversedAmount),
            'currency_code' => $this->currencyCode($networkId),
            'charts' => [
                'repayments_by_status' => $groups->map(fn ($row): array => [
                    'status' => $this->enumValue($row->status),
                    'record_count' => (int) $row->record_count,
                    'amount' => $this->amountValue($row->amount),
                ])->all(),
            ],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'repayment_id' => 'رقم السند',
            'repayment_uuid' => 'مرجع السند',
            'repayment_date' => 'التاريخ',
            'pos_name' => 'نقطة البيع',
            'amount' => 'المبلغ',
            'status' => 'الحالة',
            'payment_method' => 'وسيلة الدفع',
            'reference_number' => 'المرجع الخارجي',
            'reversal_reason' => 'سبب العكس',
            'reversed_at' => 'تاريخ العكس',
            'created_by_name' => 'أنشئ بواسطة',
        ];
    }

    public function serializeExportRow(mixed $row): array
    {
        return [
            'repayment_id' => $row->repayment_id,
            'repayment_uuid' => $row->repayment_uuid,
            'repayment_date' => $this->dateValue($row->repayment_date),
            'pos_name' => $row->pos?->pos_name,
            'amount' => $this->amountValue($row->amount),
            'status' => $this->enumValue($row->status),
            'payment_method' => $row->payment_method,
            'reference_number' => $row->reference_number,
            'reversal_reason' => $row->reversal_reason,
            'reversed_at' => $this->dateValue($row->reversed_at),
            'created_by_name' => $row->createdBy?->user_full_name,
        ];
    }
}
