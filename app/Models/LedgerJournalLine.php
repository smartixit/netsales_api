<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class LedgerJournalLine extends Model {
 use ExactDateSerialization;
 protected $table='ledger_journal_lines'; protected $primaryKey='journal_line_id';
 protected $fillable=['journal_entry_id','ledger_account_id','currency_id','debit_minor','credit_minor','description'];
 protected function casts(): array { return ['debit_minor'=>'integer','credit_minor'=>'integer']; }
 public function entry(): BelongsTo { return $this->belongsTo(LedgerJournalEntry::class,'journal_entry_id','journal_entry_id'); }
 public function account(): BelongsTo { return $this->belongsTo(LedgerAccount::class,'ledger_account_id','ledger_account_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
}