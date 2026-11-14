<?php

namespace App\Models;

use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $revenue_recognition_entry_id
 * @property int $revenue_schedule_id
 * @property int $journal_entry_id
 * @property-read LedgerJournalEntry $journalEntry
 * @property string $idempotency_key
 * @property \Illuminate\Support\Carbon $recognized_through
 * @property int $amount_minor
 */
class BillingRevenueRecognitionEntry extends Model
{
    use ExactDateSerialization;

    protected $table = 'billing_revenue_recognition_entries';
    protected $primaryKey = 'revenue_recognition_entry_id';

    protected $fillable = [
        'revenue_schedule_id',
        'journal_entry_id',
        'recognized_through',
        'amount_minor',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'recognized_through' => 'date',
            'amount_minor' => 'integer',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(BillingRevenueRecognitionSchedule::class, 'revenue_schedule_id', 'revenue_schedule_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerJournalEntry::class, 'journal_entry_id', 'journal_entry_id');
    }
}
