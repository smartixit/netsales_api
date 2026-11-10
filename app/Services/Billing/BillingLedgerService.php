<?php

namespace App\Services\Billing;

use App\Enums\LedgerEntryStatus;
use App\Interfaces\BillingLedgerRepositoryInterface;
use App\Models\{Currency, LedgerJournalEntry, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OverflowException;

class BillingLedgerService
{
    public function __construct(
        private BillingLedgerRepositoryInterface $ledger,
        private CurrencyService $currencies
    ) {}

    /**
     * @param array<int, array{ledger_account_id:int,debit_minor:int,credit_minor:int,description?:?string}> $lines
     */
    public function post(
        int $currencyId,
        string $description,
        string $idempotencyKey,
        array $lines,
        ?User $actor = null,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): LedgerJournalEntry {
        $this->validateIdempotencyKey($idempotencyKey);

        $existing = $this->ledger->findByIdempotencyKey($idempotencyKey);
        if ($existing) {
            $this->assertSamePayload($existing, $currencyId, $lines);

            return $existing;
        }

        return DB::transaction(function () use ($currencyId, $description, $idempotencyKey, $lines, $actor, $sourceType, $sourceId): LedgerJournalEntry {
            $existing = $this->ledger->findByIdempotencyKey($idempotencyKey);
            if ($existing) {
                $this->assertSamePayload($existing, $currencyId, $lines);

                return $existing;
            }

            $currency = $this->currencies->getActive($currencyId);
            $normalizedLines = $this->normalizeLines($lines, $currency);
            $accountIds = array_values(array_unique(array_column($normalizedLines, 'ledger_account_id')));
            $accounts = $this->ledger->findAccountsForCurrency($currencyId, $accountIds);

            if ($accounts->count() !== count($accountIds)) {
                throw ValidationException::withMessages([
                    'ledger_account_id' => ['يجب أن تنتمي جميع الحسابات المحاسبية إلى عملة القيد وأن تكون مفعلة.'],
                ]);
            }

            $debitTotal = $this->sumAmounts($normalizedLines, 'debit_minor');
            $creditTotal = $this->sumAmounts($normalizedLines, 'credit_minor');

            if ($debitTotal <= 0 || $debitTotal !== $creditTotal) {
                throw ValidationException::withMessages([
                    'lines' => ['القيد غير متوازن؛ يجب أن يتساوى مجموع المدين مع مجموع الدائن وأن يكون كلاهما أكبر من صفر.'],
                ]);
            }

            $entry = $this->ledger->createEntry([
                'entry_number' => 'JRN-'.now('Asia/Aden')->format('Ymd').'-'.Str::upper(Str::random(10)),
                'currency_id' => $currencyId,
                'entry_date' => now('Asia/Aden'),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => LedgerEntryStatus::POSTED,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'posted_by' => $actor?->user_id,
                'posted_at' => now('Asia/Aden'),
            ], array_map(
                static fn (array $line): array => $line + ['currency_id' => $currencyId],
                $normalizedLines
            ));

            return $entry;
        }, 3);
    }

    public function reverse(int $entryId, string $reason, string $idempotencyKey, ?User $actor = null): LedgerJournalEntry
    {
        $this->validateIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use ($entryId, $reason, $idempotencyKey, $actor): LedgerJournalEntry {
            $original = LedgerJournalEntry::query()->with('lines')->lockForUpdate()->find($entryId);

            if (! $original) {
                throw ValidationException::withMessages(['journal_entry_id' => ['القيد المحاسبي غير موجود.']]);
            }

            if ($original->status !== LedgerEntryStatus::POSTED || $original->reversal_of_id !== null) {
                throw ValidationException::withMessages(['journal_entry_id' => ['لا يمكن عكس هذا القيد.']]);
            }

            $alreadyReversed = LedgerJournalEntry::query()
                ->where('reversal_of_id', $original->journal_entry_id)
                ->exists();

            if ($alreadyReversed) {
                throw ValidationException::withMessages(['journal_entry_id' => ['تم عكس هذا القيد مسبقاً.']]);
            }

            $reversal = $this->post(
                (int) $original->currency_id,
                'عكس القيد '.$original->entry_number.': '.$reason,
                $idempotencyKey,
                $original->lines->map(static fn ($line): array => [
                    'ledger_account_id' => (int) $line->ledger_account_id,
                    'debit_minor' => (int) $line->credit_minor,
                    'credit_minor' => (int) $line->debit_minor,
                    'description' => $line->description,
                ])->all(),
                $actor,
                'LEDGER_REVERSAL',
                (int) $original->journal_entry_id
            );

            $original->status = LedgerEntryStatus::REVERSED;
            $original->save();

            $reversal->reversal_of_id = $original->journal_entry_id;
            $reversal->save();

            return $reversal->load('lines.account');
        }, 3);
    }

    private function normalizeLines(array $lines, Currency $currency): array
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => ['يتطلب القيد المحاسبي سطرين على الأقل.']]);
        }

        return array_map(static function (array $line) use ($currency): array {
            $accountId = filter_var($line['ledger_account_id'] ?? null, FILTER_VALIDATE_INT);
            $debit = filter_var($line['debit_minor'] ?? 0, FILTER_VALIDATE_INT);
            $credit = filter_var($line['credit_minor'] ?? 0, FILTER_VALIDATE_INT);

            if ($accountId === false || $accountId <= 0 || $debit === false || $credit === false || $debit < 0 || $credit < 0) {
                throw ValidationException::withMessages(['lines' => ['بيانات سطر القيد غير صالحة.']]);
            }

            if (($debit > 0 && $credit > 0) || ($debit === 0 && $credit === 0)) {
                throw ValidationException::withMessages(['lines' => ['يجب أن يحتوي كل سطر على مدين أو دائن فقط.']]);
            }

            return [
                'ledger_account_id' => (int) $accountId,
                'debit_minor' => (int) $debit,
                'credit_minor' => (int) $credit,
                'description' => isset($line['description']) ? (string) $line['description'] : null,
            ];
        }, $lines);
    }

    private function sumAmounts(array $lines, string $column): int
    {
        $sum = 0;

        foreach ($lines as $line) {
            $amount = (int) $line[$column];

            if ($amount > PHP_INT_MAX - $sum) {
                throw new OverflowException('مجموع القيد يتجاوز السعة الرقمية المسموحة.');
            }

            $sum += $amount;
        }

        return $sum;
    }

    private function validateIdempotencyKey(string $key): void
    {
        if (trim($key) === '' || strlen($key) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مطلوب ولا يتجاوز 128 حرفاً.']]);
        }
    }

    private function assertSamePayload(LedgerJournalEntry $entry, int $currencyId, array $lines): void
    {
        if ((int) $entry->currency_id !== $currencyId) {
            throw ValidationException::withMessages(['idempotency_key' => ['أعيد استخدام مفتاح منع التكرار لعملية بعملة مختلفة.']]);
        }

        $expected = collect($lines)->map(static fn (array $line): array => [
            'ledger_account_id' => (int) ($line['ledger_account_id'] ?? 0),
            'debit_minor' => (int) ($line['debit_minor'] ?? 0),
            'credit_minor' => (int) ($line['credit_minor'] ?? 0),
        ])->sortBy(fn (array $line): string => implode(':', $line))->values()->all();

        $actual = $entry->lines->map(static fn ($line): array => [
            'ledger_account_id' => (int) $line->ledger_account_id,
            'debit_minor' => (int) $line->debit_minor,
            'credit_minor' => (int) $line->credit_minor,
        ])->sortBy(fn (array $line): string => implode(':', $line))->values()->all();

        if ($expected !== $actual) {
            throw ValidationException::withMessages(['idempotency_key' => ['مفتاح منع التكرار مستخدم لبيانات قيد مختلفة.']]);
        }
    }
}
