<?php

namespace App\Services\Reports\Providers;

use App\Enums\OperationalReportType;
use App\Models\PosWallet;
use App\Models\PosWalletLog;
use App\Models\User;
use App\Services\Reports\AbstractOperationalReportProvider;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class PosWalletReportProvider extends AbstractOperationalReportProvider
{
    public function type(): OperationalReportType { return OperationalReportType::POS_WALLET; }
    protected function label(): string { return 'كشف حساب محفظة نقطة البيع'; }
    protected function description(): string { return 'كشف حركات محفظة نقطة البيع مع الرصيد السابق واللاحق والعملة.'; }
    protected function viewPermission(): string { return 'pos_wallets.report'; }
    protected function exportPermission(): string { return 'pos_wallets.export'; }
    protected function scopes(): array { return ['network', 'pos']; }
    protected function posSelection(): string { return 'required'; }
    protected function pageProfileIds(): array { return ['a4-landscape']; }

    protected function statusChoices(): array { return ['DEPOSIT', 'WITHDRAW']; }
    protected function statusFilterLabel(): string { return 'نوع الحركة'; }

    protected function columns(): array
    {
        return [
            ['id' => 'log_id', 'label' => 'رقم الحركة', 'data_type' => 'integer'],
            ['id' => 'log_date', 'label' => 'التاريخ', 'data_type' => 'date'],
            ['id' => 'log_operation_type', 'label' => 'نوع الحركة', 'data_type' => 'text'],
            ['id' => 'log_amount', 'label' => 'المبلغ', 'data_type' => 'decimal'],
            ['id' => 'balance_before', 'label' => 'الرصيد وقت الترحيل قبل الحركة', 'data_type' => 'decimal'],
            ['id' => 'balance_after', 'label' => 'الرصيد وقت الترحيل بعد الحركة', 'data_type' => 'decimal'],
            ['id' => 'balance_snapshot_source', 'label' => 'مصدر لقطة الرصيد', 'data_type' => 'text'],
            ['id' => 'created_at', 'label' => 'وقت الترحيل', 'data_type' => 'datetime'],
            ['id' => 'currency_code', 'label' => 'العملة', 'data_type' => 'text'],
            ['id' => 'is_voided', 'label' => 'قيد محذوف ناعماً', 'data_type' => 'boolean'],
            ['id' => 'log_statement', 'label' => 'البيان', 'data_type' => 'text'],
        ];
    }

    protected function metricSlots(): array
    {
        return [
            ['id' => 'record_count', 'label' => 'عدد الحركات', 'data_type' => 'integer'],
            ['id' => 'opening_balance', 'label' => 'الرصيد الافتتاحي للفترة', 'data_type' => 'decimal'],
            ['id' => 'deposits', 'label' => 'إجمالي الإيداعات', 'data_type' => 'decimal'],
            ['id' => 'withdrawals', 'label' => 'إجمالي السحوبات', 'data_type' => 'decimal'],
            ['id' => 'closing_balance', 'label' => 'الرصيد الختامي للفترة', 'data_type' => 'decimal'],
            ['id' => 'credit_limit', 'label' => 'السقف الائتماني', 'data_type' => 'decimal'],
        ];
    }

    protected function summaryCharts(): array
    {
        return [['id' => 'wallet_movements', 'label' => 'حركة المحفظة', 'chart_type' => 'bar', 'value_key' => 'amount', 'label_key' => 'operation']];
    }

    private function wallet(int $networkId, int $posId): PosWallet
    {
        return PosWallet::withTrashed()
            ->where('network_id', $networkId)
            ->where('pos_id', $posId)
            ->firstOrFail();
    }

    private function query(int $networkId, int $posId, array $filters): Builder
    {
        $pattern = $this->pattern($filters);
        $wallet = $this->wallet($networkId, $posId);
        $query = PosWalletLog::withTrashed()->with(['wallet' => fn ($relation) => $relation->withTrashed()->with('pos'), 'createdBy'])
            ->where('wallet_id', $wallet->wallet_id)
            ->when($filters['status'] ?? null, fn (Builder $q, string $value): Builder => $q->where('log_operation_type', $value))
            ->when($pattern !== null, fn (Builder $q): Builder => $q->where('log_statement', 'like', $pattern));

        return $this->applyDates($query, 'log_date', $filters);
    }

    private function periodBalances(PosWallet $wallet, array $filters): array
    {
        $ledger = PosWalletLog::withTrashed()->where('wallet_id', $wallet->wallet_id);
        $openingMinor = $this->toMinorUnits($wallet->opening_balance);

        if (! empty($filters['from_date'])) {
            $beforePeriod = (clone $ledger)->where('log_date', '<', $filters['from_date'].' 00:00:00');
            $deposits = $this->toMinorUnits(
                (clone $beforePeriod)->where('log_operation_type', 'DEPOSIT')->sum('log_amount')
            );
            $withdrawals = $this->toMinorUnits(
                (clone $beforePeriod)->where('log_operation_type', 'WITHDRAW')->sum('log_amount')
            );

            $openingMinor = $this->addMinorUnits($openingMinor, $deposits);
            $openingMinor = $this->addMinorUnits($openingMinor, -$withdrawals);
        }

        $closingMinor = $this->toMinorUnits($wallet->wallet_balance);
        if (! empty($filters['to_date'])) {
            $throughPeriod = (clone $ledger)->where('log_date', '<=', $filters['to_date'].' 23:59:59');
            $deposits = $this->toMinorUnits(
                (clone $throughPeriod)->where('log_operation_type', 'DEPOSIT')->sum('log_amount')
            );
            $withdrawals = $this->toMinorUnits(
                (clone $throughPeriod)->where('log_operation_type', 'WITHDRAW')->sum('log_amount')
            );

            $closingMinor = $this->addMinorUnits(
                $this->toMinorUnits($wallet->opening_balance),
                $deposits
            );
            $closingMinor = $this->addMinorUnits($closingMinor, -$withdrawals);
        }

        return [
            $this->fromMinorUnits($openingMinor),
            $this->fromMinorUnits($closingMinor),
        ];
    }

    public function build(User $user, int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        return $this->paginate($this->query($networkId, (int) $posId, $filters), $filters, 'created_at', 'log_id');
    }

    public function summary(User $user, int $networkId, ?int $posId, array $filters): array
    {
        $wallet = $this->wallet($networkId, (int) $posId);
        $query = $this->query($networkId, (int) $posId, $filters);
        [$opening, $closing] = $this->periodBalances($wallet, $filters);
        $groups = (clone $query)->reorder()
            ->selectRaw('log_operation_type as operation, SUM(log_amount) as amount')
            ->groupBy('log_operation_type')
            ->get();

        return [
            'record_count' => (clone $query)->count(),
            'opening_balance' => $this->amountValue($opening),
            'opening_balance_source' => $wallet->opening_balance_source,
            'deposits' => $this->amountValue((clone $query)->where('log_operation_type', 'DEPOSIT')->sum('log_amount')),
            'withdrawals' => $this->amountValue((clone $query)->where('log_operation_type', 'WITHDRAW')->sum('log_amount')),
            'closing_balance' => $this->amountValue($closing),
            'credit_limit' => $wallet->credit_limit === null ? null : $this->amountValue($wallet->credit_limit),
            'currency_code' => $wallet->currency_code,
            'charts' => [
                'wallet_movements' => $groups->map(fn ($row): array => [
                    'operation' => $row->operation,
                    'amount' => $this->amountValue($row->amount),
                ])->all(),
            ],
        ];
    }

    public function exportColumns(): array
    {
        return [
            'log_id' => 'رقم الحركة',
            'log_date' => 'التاريخ',
            'log_operation_type' => 'نوع الحركة',
            'log_amount' => 'المبلغ',
            'balance_before' => 'الرصيد قبل الحركة',
            'balance_after' => 'الرصيد بعد الحركة',
            'currency_code' => 'العملة',
            'balance_snapshot_source' => 'مصدر لقطة الرصيد',
            'created_at' => 'وقت الترحيل',
            'is_voided' => 'قيد محذوف ناعماً',
            'log_statement' => 'البيان',
            'pos_name' => 'نقطة البيع',
            'wallet_uuid' => 'مرجع المحفظة',
        ];
    }

    public function serializeRow(mixed $row): array
    {
        return [
            'log_id' => $row->log_id,
            'log_date' => $this->dateValue($row->log_date),
            'log_operation_type' => $this->enumValue($row->log_operation_type),
            'log_amount' => $this->amountValue($row->log_amount),
            'balance_before' => $this->amountValue($row->balance_before),
            'balance_after' => $this->amountValue($row->balance_after),
            'balance_snapshot_source' => $row->balance_snapshot_source,
            'created_at' => $this->dateValue($row->created_at),
            'currency_code' => $row->currency_code,
            'is_voided' => $row->trashed(),
            'log_statement' => $row->log_statement,
            'pos_name' => $row->wallet?->pos?->pos_name,
            'wallet_uuid' => $row->wallet?->wallet_uuid,
        ];
    }
}
