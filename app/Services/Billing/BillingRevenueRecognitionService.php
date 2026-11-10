<?php

namespace App\Services\Billing;

use App\Models\{BillingRevenueRecognitionEntry, BillingRevenueRecognitionSchedule, Currency};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use OverflowException;

class BillingRevenueRecognitionService
{
    public function __construct(
        private BillingLedgerService $ledger,
        private BillingLedgerChartService $chart
    ) {}

    /**
     * Recognize revenue earned through the requested date. A delayed scheduler
     * catches up the full earned amount without duplicating prior postings.
     *
     * @return array{processed:int, amount_by_currency:array<int,int>}
     */
    public function process(int $limit = 100, ?CarbonImmutable $asOf = null): array
    {
        $through = ($asOf ?? CarbonImmutable::now('Asia/Aden'))->startOfDay();
        $date = $through->toDateString();

        $ids = BillingRevenueRecognitionSchedule::query()
            ->whereIn('status', ['PENDING', 'ACTIVE'])
            ->whereDate('service_start_on', '<=', $date)
            ->whereColumn('recognized_minor', '<', 'total_minor')
            ->orderBy('revenue_schedule_id')
            ->limit(min(max($limit, 1), 500))
            ->pluck('revenue_schedule_id');

        $processed = 0;
        $amountByCurrency = [];

        foreach ($ids as $id) {
            $result = DB::transaction(function () use ($id, $through): ?array {
                $schedule = BillingRevenueRecognitionSchedule::query()
                    ->lockForUpdate()
                    ->find($id);

                if (! $schedule || ! in_array($schedule->status, ['PENDING', 'ACTIVE'], true)) {
                    return null;
                }

                $start = CarbonImmutable::parse($schedule->service_start_on->toDateString(), 'Asia/Aden');
                $end = CarbonImmutable::parse($schedule->service_end_on->toDateString(), 'Asia/Aden');
                if ($end->lt($start) || $through->lt($start)) {
                    return null;
                }

                $total = (int) $schedule->total_minor;
                $recognized = (int) $schedule->recognized_minor;
                if ($recognized >= $total) {
                    $schedule->status = 'COMPLETED';
                    $schedule->save();

                    return null;
                }

                $nextServiceDay = $schedule->last_recognized_on
                    ? CarbonImmutable::parse($schedule->last_recognized_on->toDateString(), 'Asia/Aden')->addDay()
                    : $start;

                if ($nextServiceDay->gt($end)) {
                    return null;
                }

                $recognizedThrough = $through->gt($end) ? $end : $through;
                if ($recognizedThrough->lt($nextServiceDay)) {
                    return null;
                }

                $remainingAmount = $total - $recognized;
                $remainingDays = (int) $nextServiceDay->diffInDays($end) + 1;
                $elapsedDays = (int) $nextServiceDay->diffInDays($recognizedThrough) + 1;

                if ($remainingDays <= 0 || $elapsedDays <= 0 || $elapsedDays > $remainingDays) {
                    throw new OverflowException('فترة الاعتراف بالإيراد غير متسقة.');
                }

                $amount = $elapsedDays === $remainingDays
                    ? $remainingAmount
                    : $this->proportionalAmount($remainingAmount, $elapsedDays, $remainingDays);

                if ($amount <= 0) {
                    $schedule->last_recognized_on = $recognizedThrough;
                    $schedule->status = $recognizedThrough->gte($end) ? 'COMPLETED' : 'ACTIVE';
                    $schedule->save();

                    return null;
                }

                $currency = Currency::query()->findOrFail($schedule->currency_id);
                $accounts = $this->chart->ensureForCurrency($currency);
                $revenueAccount = $accounts[$schedule->revenue_account_code] ?? null;
                if (! $revenueAccount) {
                    throw new \LogicException('حساب الإيراد المحدد في جدول الاعتراف غير موجود في دليل الحسابات.');
                }

                $idempotencyKey = 'billing-revenue-recognition-'.$schedule->revenue_schedule_id.'-'.$recognizedThrough->format('Ymd');
                $entry = $this->ledger->post(
                    (int) $schedule->currency_id,
                    'الاعتراف بإيراد الفاتورة رقم '.$schedule->invoice_id.' حتى '.$recognizedThrough->toDateString(),
                    $idempotencyKey,
                    [
                        ['ledger_account_id' => $accounts['2100']->ledger_account_id, 'debit_minor' => $amount, 'credit_minor' => 0],
                        ['ledger_account_id' => $revenueAccount->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $amount],
                    ],
                    null,
                    'BILLING_REVENUE_RECOGNITION',
                    (int) $schedule->revenue_schedule_id
                );

                BillingRevenueRecognitionEntry::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    [
                        'revenue_schedule_id' => $schedule->revenue_schedule_id,
                        'journal_entry_id' => $entry->journal_entry_id,
                        'recognized_through' => $recognizedThrough->toDateString(),
                        'amount_minor' => $amount,
                    ]
                );

                $schedule->recognized_minor = $recognized + $amount;
                $schedule->last_recognized_on = $recognizedThrough->toDateString();
                $schedule->status = $schedule->recognized_minor >= $total || $recognizedThrough->gte($end)
                    ? 'COMPLETED'
                    : 'ACTIVE';
                $schedule->save();

                return ['currency_id' => (int) $schedule->currency_id, 'amount_minor' => $amount];
            }, 3);

            if ($result !== null) {
                $processed++;
                $currencyId = $result['currency_id'];
                $amountByCurrency[$currencyId] = ($amountByCurrency[$currencyId] ?? 0) + $result['amount_minor'];
            }
        }

        return ['processed' => $processed, 'amount_by_currency' => $amountByCurrency];
    }

    private function proportionalAmount(int $amount, int $numerator, int $denominator): int
    {
        if ($amount < 0 || $numerator < 0 || $denominator <= 0 || $numerator > $denominator) {
            throw new OverflowException('معامل توزيع الإيراد غير صالح.');
        }

        $whole = intdiv($amount, $denominator);
        $remainder = $amount % $denominator;

        return ($whole * $numerator) + intdiv($remainder * $numerator, $denominator);
    }
}
