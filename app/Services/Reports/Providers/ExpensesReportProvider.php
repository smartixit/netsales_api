<?php

namespace App\Services\Reports\Providers;

use App\Enums\OperationalReportType;
use App\Models\Expense;
use App\Models\User;
use App\Services\Reports\AbstractOperationalReportProvider;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class ExpensesReportProvider extends AbstractOperationalReportProvider
{
    public function type(): OperationalReportType { return OperationalReportType::EXPENSES; }
    protected function label(): string { return 'تقرير المصروفات التشغيلية'; }
    protected function description(): string { return 'المصروفات التشغيلية للشبكة وبنودها المسجلة فعلياً.'; }
    protected function viewPermission(): string { return 'expenses.report'; }
    protected function exportPermission(): string { return 'expenses.export'; }
    protected function scopes(): array { return ['network']; }
    protected function posSelection(): string { return 'forbidden'; }
    protected function statusChoices(): array { return []; }

    protected function columns(): array
    {
        return [
            ['id' => 'expense_id', 'label' => 'رقم المصروف', 'data_type' => 'integer'],
            ['id' => 'expense_uuid', 'label' => 'مرجع المصروف', 'data_type' => 'text'],
            ['id' => 'expense_date', 'label' => 'التاريخ', 'data_type' => 'date'],
            ['id' => 'expense_total_amount', 'label' => 'الإجمالي', 'data_type' => 'decimal'],
            ['id' => 'items', 'label' => 'تفاصيل البنود', 'data_type' => 'text'],
            ['id' => 'created_by_name', 'label' => 'أُنشئ بواسطة', 'data_type' => 'text'],
        ];
    }

    protected function metricSlots(): array
    {
        return [
            ['id' => 'record_count', 'label' => 'عدد المصروفات', 'data_type' => 'integer'],
            ['id' => 'total_amount', 'label' => 'إجمالي المصروفات', 'data_type' => 'decimal'],
        ];
    }

    protected function summaryCharts(): array
    {
        return [
            ['id' => 'expenses_by_day', 'label' => 'المصروفات حسب اليوم', 'chart_type' => 'line', 'value_key' => 'amount', 'label_key' => 'date'],
        ];
    }

    private function query(int $networkId, array $filters): Builder
    {
        $pattern = $this->pattern($filters);
        $query = Expense::query()->with(['items', 'createdBy'])
            ->where('network_id', $networkId)
            ->when($pattern !== null, fn (Builder $q): Builder => $q->where(
                fn (Builder $nested): Builder => $nested
                   ->where('expense_uuid', 'like', $pattern)
                    ->orWhereHas('items', fn (Builder $item): Builder => $item->where('item_statement', 'like', $pattern))
            ));

        return $this->applyDates($query, 'expense_date', $filters);
    }

    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        return $this->paginate($this->query($networkId, $filters), $filters, 'expense_date', 'expense_id');
    }

    public function summary(User $user, int $networkId, ?int $posId, array $filters): array
    {
        $query = $this->query($networkId, $filters);
        $daily = (clone $query)->reorder()
            ->selectRaw('DATE(expense_date) as date, SUM(expense_total_amount) as amount')
            ->groupByRaw('DATE(expense_date)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => ['date' => (string) $row->date, 'amount' => $this->amountValue($row->amount)])
            ->all();

        return [
            'record_count' => (clone $query)->count(),
            'total_amount' => $this->amountValue((clone $query)->sum('expense_total_amount')),
            'currency_code' => $this->currencyCode($networkId),
            'charts' => ['expenses_by_day' => $daily],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'expense_id' => 'رقم المصروف',
            'expense_uuid' => 'مرجع المصروف',
            'expense_date' => 'التاريخ',
            'expense_total_amount' => 'الإجمالي',
            'items' => 'تفاصيل البنود',
            'created_by_name' => 'أُنشئ بواسطة',
        ];
    }

    public function serializeRow(mixed $row): array
    {
        return [
            'expense_id' => $row->expense_id,
            'expense_uuid' => $row->expense_uuid,
            'expense_date' => $this->dateValue($row->expense_date),
            'expense_total_amount' => $this->amountValue($row->expense_total_amount),
            'items' => $row->items->map(fn ($item): string => trim((string) $item->item_statement).' ('.$this->amountValue($item->item_amount ?? 0).')')->implode(' | '),
            'created_by_name' => $row->createdBy?->user_full_name,
        ];
    }
}
