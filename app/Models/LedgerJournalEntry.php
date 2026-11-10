<?php
namespace App\Models;
use App\Enums\LedgerEntryStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class LedgerJournalEntry extends Model {
 use ExactDateSerialization;
 protected $table='ledger_journal_entries'; protected $primaryKey='journal_entry_id';
 protected $fillable=['entry_number','currency_id','entry_date','source_type','source_id','status','idempotency_key','description','reversal_of_id','posted_by','posted_at'];
 protected function casts(): array { return ['status'=>LedgerEntryStatus::class,'entry_date'=>'datetime','posted_at'=>'datetime']; }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
 public function lines(): HasMany { return $this->hasMany(LedgerJournalLine::class,'journal_entry_id','journal_entry_id'); }
 public function reversalOf(): BelongsTo { return $this->belongsTo(self::class,'reversal_of_id','journal_entry_id'); }
 public function scopeForCurrency(Builder $q,int $id): Builder { return $q->where('currency_id',$id); }
}