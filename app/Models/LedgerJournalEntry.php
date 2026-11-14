<?php

namespace App\Models;

use App\Enums\LedgerEntryStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $journal_entry_id
 * @property int $currency_id
 * @property \App\Enums\LedgerEntryStatus $status
 * @property \Illuminate\Support\Carbon $entry_date
 * @property-read Currency $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LedgerJournalLine> $lines
 */
class LedgerJournalEntry extends Model
{
    use ExactDateSerialization;

    protected $table = 'ledger_journal_entries';
    protected $primaryKey = 'journal_entry_id';

    protected $fillable = [
        'entry_number', 'currency_id', 'entry_date', 'source_type', 'source_id',
        'status', 'idempotency_key', 'description', 'reversal_of_id', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LedgerEntryStatus::class,
            'entry_date' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            $allowed = ['status', 'reversal_of_id', 'updated_at'];
            if (array_diff(array_keys($entry->getDirty()), $allowed) !== []) {
                throw new LogicException('بيانات القيد المحاسبي المنشور غير قابلة للتعديل؛ أنشئ قيداً عكسياً.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('القيود المحاسبية لا تحذف؛ استخدم قيداً عكسياً.');
        });
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'currency_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LedgerJournalLine::class, 'journal_entry_id', 'journal_entry_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id', 'journal_entry_id');
    }

    public function scopeForCurrency(Builder $query, int $id): Builder
    {
        return $query->where('currency_id', $id);
    }
}
