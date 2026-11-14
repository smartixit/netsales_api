<?php
namespace App\Models;
use App\Enums\LedgerAccountClass;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
/**
 * @property int $ledger_account_id
 * @property string $account_code
 * @property string $name
 * @property \App\Enums\LedgerAccountClass $account_class
 * @property bool $is_control_account
 * @property bool $is_active
 * @property-read Currency|null $currency
 */
class LedgerAccount extends Model {
 use ExactDateSerialization;
 protected $table='ledger_accounts'; protected $primaryKey='ledger_account_id';
 protected $fillable=['account_code','name','account_class','normal_balance','currency_id','is_control_account','is_active'];
 protected function casts(): array { return ['account_class'=>LedgerAccountClass::class,'is_control_account'=>'boolean','is_active'=>'boolean']; }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
 public function lines(): HasMany { return $this->hasMany(LedgerJournalLine::class,'ledger_account_id','ledger_account_id'); }
 public function scopeForCurrency(Builder $q,int $id): Builder { return $q->where('currency_id',$id); }
}