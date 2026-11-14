<?php

namespace App\Models;

use App\Enums\LedgerEntryStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @property int $journal_line_id
 * @property int $journal_entry_id
 * @property int $ledger_account_id
 * @property int $currency_id
 * @property int $debit_minor
 * @property int $credit_minor
 * @property-read LedgerAccount $account
 */
class LedgerJournalLine extends Model
{
    use ExactDateSerialization;

    protected $table = 'ledger_journal_lines';
    protected $primaryKey = 'journal_line_id';

    protected $fillable = [
        'journal_entry_id', 'ledger_account_id', 'currency_id',
        'debit_minor', 'credit_minor', 'description',
    ];

    protected function casts(): array
    {
        return ['debit_minor' => 'integer', 'credit_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            $entry = LedgerJournalEntry::query()->find($line->journal_entry_id);
            $account = LedgerAccount::query()->find($line->ledger_account_id);

            if (! $entry || ! $account || $entry->status !== LedgerEntryStatus::POSTED) {
                throw new LogicException('لا يمكن إضافة سطر إلا إلى قيد محاسبي مرحّل وحساب معروف.');
            }

            if ((int) $line->currency_id !== (int) $entry->currency_id
                || (int) $account->currency_id !== (int) $entry->currency_id) {
                throw ValidationException::withMessages([
                    'currency_id' => ['عملة سطر القيد والحساب يجب أن تطابق عملة القيد.'],
                ]);
            }

            $debit = (int) $line->debit_minor;
            $credit = (int) $line->credit_minor;
            if ($debit < 0 || $credit < 0 || (($debit > 0) === ($credit > 0))) {
                throw ValidationException::withMessages([
                    'lines' => ['يجب أن يحتوي سطر القيد على قيمة مدين أو دائن واحدة فقط وبقيمة موجبة.'],
                ]);
            }
        });

        static::updating(function (): void {
            throw new LogicException('أسطر القيود المحاسبية المنشورة غير قابلة للتعديل.');
        });

        static::deleting(function (): void {
            throw new LogicException('أسطر القيود المحاسبية لا تحذف؛ أنشئ قيداً عكسياً.');
        });
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LedgerJournalEntry::class, 'journal_entry_id', 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id', 'ledger_account_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id');
    }
}
