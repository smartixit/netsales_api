<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
class TreasuryAccount extends Model {
 use ExactDateSerialization, SoftDeletes;
 protected $table='treasury_accounts'; protected $primaryKey='treasury_account_id';
 protected $fillable=['account_code','name','account_type','currency_id','institution_name','account_reference','status','metadata'];
 protected function casts(): array { return ['metadata'=>'array']; }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
 public function payments(): HasMany { return $this->hasMany(BillingPayment::class,'treasury_account_id','treasury_account_id'); }
}