<?php

namespace App\Services\Reports\Providers;

use App\Enums\OperationalReportType;
use App\Models\Request as SupplyRequest;
use App\Models\User;
use App\Services\Reports\AbstractOperationalReportProvider;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class RequestsReportProvider extends AbstractOperationalReportProvider
{
    public function type(): OperationalReportType
    {
        return OperationalReportType::REQUESTS;
    }

    protected function label(): string
    {
        return 'تقرير حركة الطلبات';
    }

    protected function description(): string
    {
        return 'طلبات التوريد وحالاتها ونوع البيع وقيمتها.';
    }

    protected function viewPermission(): string
    {
        return 'requests.report';
    }

    protected function exportPermission(): string
    {
        return 'requests.export';
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
        return ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];
    }

    protected function statusFilterLabel(): string
    {
        return 'حالة الطلب';
    }

    protected function saleTypeChoices(): array
    {
        return ['CASH', 'CREDIT'];
    }

    protected function saleTypeFilterLabel(): string
    {
        return 'نوع الطلب';
    }

    protected function columns(): array
    {
        return [
            ['id' => 'request_id', 'label' => 'رقم الطلب', 'data_type' => 'integer'],
            ['id' => 'request_uuid', 'label' => 'مرجع الطلب', 'data_type' => 'text'],
            ['id' => 'request_date', 'label' => 'التاريخ', 'data_type' => 'date'],
            ['id' => 'pos_name', 'label' => 'نقطة البيع', 'data_type' => 'text'],
            ['id' => 'sale_type', 'label' => 'نوع البيع', 'data_type' => 'text'],
            ['id' => 'status', 'label' => 'الحالة', 'data_type' => 'text'],
            ['id' => 'request_total_amount', 'label' => 'الإجمالي', 'data_type' => 'decimal'],
            ['id' => 'request_note', 'label' => 'البيان', 'data_type' => 'text'],
        ];
    }

    protected function metricSlots(): array
    {
        return [
            ['id' => 'record_count', 'label' => 'عدد الطلبات', 'data_type' => 'integer'],
            ['id' => 'total_amount', 'label' => 'قيمة الطلبات', 'data_type' => 'decimal'],
            ['id' => 'pending_count', 'label' => 'قيد الانتظار', 'data_type' => 'integer'],
            ['id' => 'approved_count', 'label' => 'معتمدة', 'data_type' => 'integer'],
            ['id' => 'rejected_count', 'label' => 'مرفوضة', 'data_type' => 'integer'],
            ['id' => 'cancelled_count', 'label' => 'ملغاة', 'data_type' => 'integer'],
        ];
    }

    protected function summaryCharts(): array
    {
        return [[
            'id' => 'requests_by_status',
            'label' => 'الطلبات حسب الحالة',
            'chart_type' => 'bar',
            'value_key' => 'record_count',
            'label_key' => 'status',
        ]];
    }

    private function query(int $networkId, ?int $posId, array $filters): Builder
    {
        $pattern = $this->pattern($filters);

        $query = SupplyRequest::query()
            ->with('pos')
            ->where('network_id', $networkId)
            ->when($posId !== null, fn (Builder $query): Builder => $query->where('pos_id', $posId))
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('status', $status)
            )
            ->when(
                $filters['sale_type'] ?? null,
                fn (Builder $query, string $saleType): Builder => $query->where('sale_type', $saleType)
            )
            ->when(
                $pattern !== null,
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $nested): Builder => $nested
                        ->where('request_uuid', 'like', $pattern)
                        ->orWhere('request_note', 'like', $pattern)
                )
            );

        return $this->applyDates($query, 'request_date', $filters);
    }

    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        return $this->paginate(
            $this->query($networkId, $posId, $filters),
            $filters,
            'request_date',
            'request_id'
        );
    }

    public function summary(User $user, int $networkId, ?int $posId, array $filters): array
    {
        $query = $this->query($networkId, $posId, $filters);
        $groups = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as record_count')
            ->groupBy('status')
            ->get();

        return [
            'record_count' => (clone $query)->count(),
            'total_amount' => $this->amountValue((clone $query)->sum('request_total_amount')),
            'pending_count' => (clone $query)->where('status', 'PENDING')->count(),
            'approved_count' => (clone $query)->where('status', 'APPROVED')->count(),
            'rejected_count' => (clone $query)->where('status', 'REJECTED')->count(),
            'cancelled_count' => (clone $query)->where('status', 'CANCELLED')->count(),
            'currency_code' => $this->currencyCode($networkId),
            'charts' => [
                'requests_by_status' => $groups->map(fn ($row): array => [
                    'status' => $this->enumValue($row->status),
                    'record_count' => (int) $row->record_count,
                ])->all(),
            ],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'request_id' => 'رقم الطلب',
            'request_uuid' => 'مرجع الطلب',
            'request_date' => 'التاريخ',
            'pos_name' => 'نقطة البيع',
            'sale_type' => 'نوع البيع',
            'status' => 'الحالة',
            'request_total_amount' => 'الإجمالي',
            'request_note' => 'البيان',
        ];
    }

    public function serializeExportRow(mixed $row): array
    {
        return [
            'request_id' => $row->request_id,
            'request_uuid' => $row->request_uuid,
            'request_date' => $this->dateValue($row->request_date),
            'pos_name' => $row->pos?->pos_name,
            'sale_type' => $this->enumValue($row->sale_type),
            'status' => $this->enumValue($row->status),
            'request_total_amount' => $this->amountValue($row->request_total_amount),
            'request_note' => $row->request_note,
        ];
    }
}
