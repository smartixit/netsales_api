<?php

namespace App\Services;

use App\Enums\OperationalReportType;
use App\Models\Expense;
use App\Models\NetworkWallet;
use App\Models\PosRepayment;
use App\Models\PosWallet;
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

    private function applyDates(Builder $query, string $column, array $filters): Builder
    {
        return $query
            ->when($filters['from_date'] ?? null, fn (Builder $q, string $date): Builder => $q->where($column, '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['to_date'] ?? null, fn (Builder $q, string $date): Builder => $q->where($column, '<=', Carbon::parse($date)->endOfDay()));
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
            ->when($filters['sale_type'] ?? null, fn (Builder $q, string $v): Builder => $q->where('sale_type', $v));
        return $this->paginate($this->applyDates($query, 'invoice_date', $filters), $filters, 'invoice_date', 'invoice_id');
    }

    private function expenses(int $networkId, array $filters): CursorPaginator
    {
        $query = Expense::query()->with(['network', 'createdBy', 'items'])
            ->where('network_id', $networkId);
        return $this->paginate($this->applyDates($query, 'expense_date', $filters), $filters, 'expense_date', 'expense_id');
    }

    private function networkWallet(int $networkId, array $filters): CursorPaginator|array
    {
        $wallet = NetworkWallet::query()->with(['network', 'logs.createdBy'])
            ->where('network_id', $networkId)->first();
        if (! $wallet) {
            return [];
        }
        $logs = $wallet->logs()->with('createdBy');
        return $this->paginate($this->applyDates($logs, 'log_date', $filters), $filters, 'log_date', 'log_id');
    }

    private function posWallet(int $networkId, ?int $posId, array $filters): CursorPaginator|array
    {
        if ($posId === null) {
            throw ValidationException::withMessages(['pos_id' => ['تقرير محفظة نقطة البيع يتطلب تحديد نقطة البيع.']]);
        }
        $wallet = PosWallet::query()->where('network_id', $networkId)->where('pos_id', $posId)->first();
        if (! $wallet) {
            return [];
        }
        $logs = $wallet->logs()->with('createdBy');
        return $this->paginate($this->applyDates($logs, 'log_date', $filters), $filters, 'log_date', 'log_id');
    }

    private function repayments(int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        $query = PosRepayment::query()->with(['allocations.invoice', 'pos', 'network', 'createdBy', 'reversedBy'])
            ->where('network_id', $networkId)
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId));
        return $this->paginate($this->applyDates($query, 'repayment_date', $filters), $filters, 'repayment_date', 'repayment_id');
    }

    private function debtAging(int $networkId, ?int $posId, array $filters): CursorPaginator
    {
        $query = SaleInvoice::query()->with(['pos', 'network', 'items.voucherPackage'])
            ->where('network_id', $networkId)
            ->where('remaining_amount', '>', 0)
            ->where('payment_status', '!=', 'PAID')
            ->when($posId, fn (Builder $q): Builder => $q->where('pos_id', $posId));
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
        return $this->paginate($this->applyDates($query, 'request_date', $filters), $filters, 'request_date', 'request_id');
    }
}
