<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CurrencyConversion extends Model {
 use ExactDateSerialization;
 protected $table='currency_conversions'; protected $primaryKey='conversion_id';
 protected $fillable=['exchange_rate_id','from_currency_id','to_currency_id','source_amount_minor','converted_amount_minor','fee_amount_minor','rounding_mode','rate_snapshot','source_type','source_id','created_by','converted_at'];
 protected function casts(): array { return ['source_amount_minor'=>'integer','converted_amount_minor'=>'integer','fee_amount_minor'=>'integer','rate_snapshot'=>'decimal:12','converted_at'=>'datetime']; }
 public function exchangeRate(): BelongsTo { return $this->belongsTo(CurrencyExchangeRate::class,'exchange_rate_id','exchange_rate_id'); }
 public function fromCurrency(): BelongsTo { return $this->belongsTo(Currency::class,'from_currency_id','currency_id'); }
 public function toCurrency(): BelongsTo { return $this->belongsTo(Currency::class,'to_currency_id','currency_id'); }
}