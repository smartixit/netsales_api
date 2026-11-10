<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingRefund extends Model {
 use ExactDateSerialization;
 protected $table='billing_refunds'; protected $primaryKey='refund_id';
 protected $fillable=['refund_reference','payment_id','currency_id','amount_minor','status','reason','processed_by','processed_at'];
 protected function casts(): array { return ['amount_minor'=>'integer','processed_at'=>'datetime']; }
 public function payment(): BelongsTo { return $this->belongsTo(BillingPayment::class,'payment_id','payment_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
}