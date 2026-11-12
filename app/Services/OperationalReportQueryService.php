<?php

namespace App\Services;

use App\Enums\OperationalReportType;
use App\Models\Expense;
use App\Models\NetworkWallet;
use App\Models\NetworkWalletLog;
use App\Models\PosRepayment;
use App\Models\PosWallet;
use App\Models\PosWalletLog;
use App\Models\Request as ProcurementRequest;
use App\Models\SaleInvoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OperationalReportQueryService
{
    public function build(
        User $user,
        OperationalReportType $type,
        int $networkId,
        ?int $posId,
        array $filters
    ): CursorPaginator|array {
        return match ($type) {
            OperationalReportType::SALES => $this->sales($networkId, $posId, $filters),
            OperationalReportType::EXPENSES => $this->expenses($networkId, $filters),
            OperationalReportType::NETWORK_WALLET => $this->networkWallet($networkId, $filters),
            OperationalReportType::POS_WALLET => $this->posWallet($networkId, $posId, $filters),
            OperationalReportType::REPAYMENTS => $this->repayments($networkId, $posId, $filters),
            OperationalReportType::DEBT_AGING => $this->debtAging($networkId, $posId, $filters),
            OperationalReportType::REQUESTS => $this->requests($networkId, $posId, $filters),
        };
    }

    /**
     * Returns authoritative aggregates for the complete filtered dataset.
     * Cursor pagination is intentionally not applied to summary metrics.
     */
    public function summary(
        OperationalReportType $type,
        int $networkId,
        ?int $posId,
        array $filters
    ): array {
        if ($type === OperationalReportType::POS_WALLET && $posId === null) {
            throw ValidationException::withMessages([
                'pos_id' => ['تقرير محفظة نقطة البيع يتطلب تحديد نقطة البيع.'],
            ]);
        }

        return match ($type) {
            OperationalReportType::SALES => $this->salesSummary($networkId, $posId, $filters),
            OperationalReportType::EXPENSES => $this->expensesSummary($networkId, $filters),
            OperationalReportType::NETWORK_WALLET => $this->walletSummary($networkId, null, $filters),
            OperationalReportType::POS_WALLET => $this->walletSummary($networkId, $posId, $filters),
            OperationalReportType::REPAYMENTS => $this->repaymentsSummary($networkId, $posId, $filters),
            OperationalReportType::DEBT_AGING => $this->debtAgingSummary($networkId, $posId, $filters),
            OperationalReportType::REQUESTS => $this->requestsSummary($networkId, $posId, $filters),
        };
    }

    private function salesSummary(int $networkId, ?int $posId, array $filters): array
    {
        $query = SaleInvoice::query()
            ->where('network_id', $networkId)
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['sale_type'] ?? null, fn (Builder $q, string $v): Builder => $q->where('sale_type', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('payment_status', $v));
        $query = $this->applySearch($query, OperationalReportType::SALES, $filters['search'] ?? null);
        $query = $this->applyDates($query, 'invoice_date', $filters);

        return [
            'record_count' => (clone $query)->count(),
            'total_amount' => (clone $query)->sum('invoice_total_amount'),
            'paid_amount' => (clone $query)->sum('paid_amount'),
            'remaining_amount' => (clone $query)->sum('remaining_amount'),
        ];
    }

    private function expensesSummary(int $networkId, array $filters): array
    {
        $query = Expense::query()->where('network_id', $networkId);
        $query = $this->applySearch($query, OperationalReportType::EXPENSES, $filters['search'] ?? null);
        $query = $this->applyDates($query, 'expense_date', $filters);

        return [
            'record_count' => (clone $query)->count(),
            'total_amount' => (clone $query)->sum('expense_total_amount'),
        ];
    }

    private function walletSummary(int $networkId, ?int $posId, array $filters): array
    {
        if ($posId === null) {
            $wallet = NetworkWallet::query()->where('network_id', $networkId)->first();
            if (! $wallet) {
                return ['record_count' => 0, 'deposits' => '0.00', 'withdrawals' => '0.00'];
            }
            $query = $wallet->logs()->getQuery();
            $type = OperationalReportType::NETWORK_WALLET;
            $operationColumn = 'log_operation';
        } else {
            $wallet = PosWallet::query()
                ->where('network_id', $networkId)
                ->where('pos_id', $posId)
                ->first();
            if (! $wallet) {
                return ['record_count' => 0, 'deposits' => '0.00', 'withdrawals' => '0.00'];
            }
            $query = $wallet->logs()->getQuery();
            $type = OperationalReportType::POS_WALLET;
            $operationColumn = 'log_operation_type';
        }

        $query = $query->when(
            $filters['status'] ?? null,
            fn (Builder $q, string $v): Builder => $q->where($operationColumn, $v)
        );
        $query = $this->applySearch($query, $type, $filters['search'] ?? null);
        $query = $this->applyDates($query, 'log_date', $filters);

        $deposits = clone $query;
        $withdrawals = clone $query;

        return [
            'record_count' => (clone $query)->count(),
            'deposits' => $deposits->where($operationColumn, 'DEPOSIT')->sum('log_amount'),
            'withdrawals' => $withdrawals->where($operationColumn, 'WITHDRAW')->sum('log_amount'),
        ];
    }

    private function repaymentsSummary(int $networkId, ?int $posId, array $filters): array
    {
        $query = PosRepayment::query()
            ->where('network_id', $networkId)
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('status', $v));
        $query = $this->applySearch($query, OperationalReportType::REPAYMENTS, $filters['search'] ?? null);
        $query = $this->applyDates($query, 'repayment_date', $filters);

        $completed = (clone $query)->where('status', 'COMPLETED');
        $reversed = (clone $query)->where('status', 'REVERSED');

        return [
            'record_count' => (clone $query)->count(),
            'total_amount' => (clone $completed)->sum('amount'),
            'completed_count' => (clone $completed)->count(),
            'reversed_count' => (clone $reversed)->count(),
            'reversed_amount' => (clone $reversed)->sum('amount'),
        ];
    }

    private function debtAgingSummary(int $networkId, ?int $posId, array $filters): array
    {
        $query = SaleInvoice::query()
            ->where('network_id', $networkId)
            ->where('remaining_amount', '>', 0)
            ->where('payment_status', '!=', 'PAID')
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('payment_status', $v));
        $query = $this->applySearch($query, OperationalReportType::DEBT_AGING, $filters['search'] ?? null);
        $query = $this->applyDates($query, 'invoice_date', $filters);

        $now = Carbon::now('Asia/Aden');
        $day30 = $now->copy()->subDays(30)->startOfDay();
        $day60 = $now->copy()->subDays(60)->startOfDay();
        $day90 = $now->copy()->subDays(90)->startOfDay();

        return [
            'record_count' => (clone $query)->count(),
            'total_outstanding' => (clone $query)->sum('remaining_amount'),
            'bucket_0_30' => (clone $query)->where('invoice_date', '>=', $day30)->sum('remaining_amount'),
            'bucket_31_60' => (clone $query)->where('invoice_date', '<', $day30)->where('invoice_date', '>=', $day60)->sum('remaining_amount'),
            'bucket_61_90' => (clone $query)->where('invoice_date', '<', $day60)->where('invoice_date', '>=', $day90)->sum('remaining_amount'),
            'bucket_over_90' => (clone $query)->where('invoice_date', '<', $day90)->sum('remaining_amount'),
        ];
    }

    private function requestsSummary(int $networkId, ?int $posId, array $filters): array
    {
        $query = ProcurementRequest::query()
            ->where('network_id', $networkId)
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('status', $v));
        $query = $this->applySearch($query, OperationalReportType::REQUESTS, $filters['search'] ?? null);
        $query = $this->applyDates($query, 'request_date', $filters);

        return [
            'record_count' => (clone $query)->count(),
            'total_amount' => (clone $query)->sum('request_total_amount'),
            'pending_count' => (clone $query)->where('status', 'PENDING')->count(),
            'approved_count' => (clone $query)->where('status', 'APPROVED')->count(),
            'rejected_count' => (clone $query)->where('status', 'REJECTED')->count(),
            'cancelled_count' => (clone $query)->where('status', 'CANCELLED')->count(),
        ];
    }

    private function applyDates(Builder $query, string $column, array $filters): Builder
    {
        return $query
            ->when($filters['from_date'] ?? null, fn (Builder $q, string $date): Builder => $q->where($column, '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['to_date'] ?? null, fn (Builder $q, string $date): Builder => $q->where($column, '<=', Carbon::parse($date)->endOfDay()));
    }

    private function applySearch(
        Builder $query,
        OperationalReportType $type,
        ?string $rawSearch
    ): Builder {
        $search = trim((string) $rawSearch);
        if ($search === '') {
            return $query;
        }

        $pattern = '%'.$search.'%';

        return match ($type) {
            OperationalReportType::SALES,
            OperationalReportType::DEBT_AGING => $query->where(
                fn (Builder $q): Builder => $q
                    ->where('invoice_uuid', 'like', $pattern)
                    ->orWhere('invoice_id', 'like', $pattern)
                    ->orWhereHas('pos', fn (Builder $pos): Builder => $pos->where('pos_name', 'like', $pattern))
            ),
            OperationalReportType::EXPENSES => $query->where(
                fn (Builder $q): Builder => $q
                    ->where('expense_uuid', 'like', $pattern)
                    ->orWhere('expense_id', 'like', $pattern)
                    ->orWhereHas('items', fn (Builder $item): Builder => $item->where('item_statement', 'like', $pattern))
            ),
            OperationalReportType::NETWORK_WALLET => $query->where(
                fn (Builder $q): Builder => $q
                    ->where('log_statement', 'like', $pattern)
                    ->orWhere('log_operation', 'like', $pattern)
                    ->orWhere('log_id', 'like', $pattern)
            ),
            OperationalReportType::POS_WALLET => $query->where(
                fn (Builder $q): Builder => $q
                    ->where('log_statement', 'like', $pattern)
                    ->orWhere('log_operation_type', 'like', $pattern)
                    ->orWhere('log_id', 'like', $pattern)
            ),
            OperationalReportType::REPAYMENTS => $query->where(
                fn (Builder $q): Builder => $q
                    ->where('repayment_uuid', 'like', $pattern)
                    ->orWhere('repayment_id', 'like', $pattern)
                    ->orWhere('reference_number', 'like', $pattern)
                    ->orWhere('notes', 'like', $pattern)
            ),
            OperationalReportType::REQUESTS => $query->where(
                fn (Builder $q): Builder => $q
                    ->where('request_uuid', 'like', $pattern)
                    ->orWhere('request_id', 'like', $pattern)
                    ->orWhere('request_note', 'like', $pattern)
                    ->orWhereHas('pos', fn (Builder $pos): Builder => $pos->where('pos_name', 'like', $pattern))
            ),
        };
    }

    private function paginate(Builder $query, array $filters, string $dateColumn, string $key): CursorPaginator
    {
        $query->orderBy($dateColumn, 'desc')->orderBy($key, 'desc');

        return $query->cursorPaginate((int) ($filters['per_page'] ?? 25), ['*'], 'cursor', $filters['cursor'] ?? null);
    }

    private function sales(int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        $query = SaleInvoice::query()->with(['network', 'pos', 'createdBy', 'items.voucherPackage'])
            ->where('network_id', $networkId)
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['sale_type'] ?? null, fn (Builder $q, string $v): Builder => $q->where('sale_type', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('payment_status', $v));
        $query = $this->applySearch($query, OperationalReportType::SALES, $filters['search'] ?? null);
        return $this->paginate($this->applyDates($query, 'invoice_date', $filters), $filters, 'invoice_date', 'invoice_id');
    }

    private function expenses(int $networkId, array $filters): CursorPaginator
    {
        $query = Expense::query()->with(['network', 'createdBy', 'items'])
            ->where('network_id', $networkId);
        $query = $this->applySearch($query, OperationalReportType::EXPENSES, $filters['search'] ?? null);
        return $this->paginate($this->applyDates($query, 'expense_date', $filters), $filters, 'expense_date', 'expense_id');
    }

    private function networkWallet(int $networkId, array $filters): CursorPaginator|array
    {
        $wallet = NetworkWallet::query()->with(['network', 'logs.createdBy'])
            ->where('network_id', $networkId)->first();
        if (! $wallet) {
            $emptyLogs = NetworkWalletLog::query()->whereRaw('1 = 0');

            return $this->paginate($emptyLogs, $filters, 'log_date', 'log_id');
        }
        $logs = $wallet->logs()->getQuery()
            ->with('createdBy')
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('log_operation', $v));
        $logs = $this->applySearch($logs, OperationalReportType::NETWORK_WALLET, $filters['search'] ?? null);
        return $this->paginate($this->applyDates($logs, 'log_date', $filters), $filters, 'log_date', 'log_id');
    }

    private function posWallet(int $networkId, ?int $posId, array $filters): CursorPaginator|array
    {
        if ($posId === null) {
            throw ValidationException::withMessages(['pos_id' => ['تقرير محفظة نقطة البيع يتطلب تحديد نقطة البيع.']]);
        }
        $wallet = PosWallet::query()->where('network_id', $networkId)->where('pos_id', $posId)->first();
        if (! $wallet) {
            $emptyLogs = PosWalletLog::query()->whereRaw('1 = 0');

            return $this->paginate($emptyLogs, $filters, 'log_date', 'log_id');
        }
        $logs = $wallet->logs()->getQuery()
            ->with('createdBy')
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('log_operation_type', $v));
        $logs = $this->applySearch($logs, OperationalReportType::POS_WALLET, $filters['search'] ?? null);
        return $this->paginate($this->applyDates($logs, 'log_date', $filters), $filters, 'log_date', 'log_id');
    }

    private function repayments(int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        $query = PosRepayment::query()->with(['allocations.invoice', 'pos', 'network', 'createdBy', 'reversedBy'])
            ->where('network_id', $networkId)
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('status', $v));
        $query = $this->applySearch($query, OperationalReportType::REPAYMENTS, $filters['search'] ?? null);
        return $this->paginate($this->applyDates($query, 'repayment_date', $filters), $filters, 'repayment_date', 'repayment_id');
    }

    private function debtAging(int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        $query = SaleInvoice::query()->with(['pos', 'network', 'items.voucherPackage'])
            ->where('network_id', $networkId)
            ->where('remaining_amount', '>', 0)
            ->where('payment_status', '!=', 'PAID')
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('payment_status', $v));
        $query = $this->applySearch($query, OperationalReportType::DEBT_AGING, $filters['search'] ?? null);
        $query = $this->applyDates($query, 'invoice_date', $filters);
        $now = Carbon::now('Asia/Aden');
        $query->orderBy('invoice_date')->orderBy('invoice_id');
        $page = $query->cursorPaginate((int) ($filters['per_page'] ?? 25), ['*'], 'cursor', $filters['cursor'] ?? null);
        foreach ($page->items() as $invoice) {
            $age = max(0, (int) Carbon::parse($invoice->invoice_date)->diffInDays($now));
            $invoice->setAttribute('age_days', $age);
            $invoice->setAttribute('aging_bucket', match (true) {
                $age <= 30 => '0_30',
                $age <= 60 => '31_60',
                $age <= 90 => '61_90',
                default => 'over_90',
            });
        }
        return $page;
    }

    private function requests(int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        $query = ProcurementRequest::query()->with(['network', 'pos', 'createdBy', 'reviewedBy', 'items'])
            ->where('network_id', $networkId)
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v): Builder => $q->where('status', $v));
        $query = $this->applySearch($query, OperationalReportType::REQUESTS, $filters['search'] ?? null);
        return $this->paginate($this->applyDates($query, 'request_date', $filters), $filters, 'request_date', 'request_id');
    }
}
