<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
/**
 * @property int $payment_intent_id
 * @property int $invoice_id
 * @property int $billing_account_id
 * @property int $currency_id
 * @property int $amount_minor
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $succeeded_at
 */
class BillingPaymentIntent extends Model {
 use ExactDateSerialization;
 protected $table='billing_payment_intents'; protected $primaryKey='payment_intent_id';
 protected $fillable=['intent_uuid','invoice_id','billing_account_id','currency_id','payment_method_id','amount_minor','status','idempotency_key','metadata','expires_at','succeeded_at'];
 protected function casts(): array { return ['amount_minor'=>'integer','metadata'=>'array','expires_at'=>'datetime','succeeded_at'=>'datetime']; }
 public function invoice(): BelongsTo { return $this->belongsTo(BillingInvoice::class,'invoice_id','invoice_id'); }
 public function billingAccount(): BelongsTo { return $this->belongsTo(BillingAccount::class,'billing_account_id','billing_account_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
 public function paymentMethod(): BelongsTo { return $this->belongsTo(BillingPaymentMethod::class,'payment_method_id','payment_method_id'); }
 public function attempts(): HasMany { return $this->hasMany(BillingPaymentAttempt::class,'payment_intent_id','payment_intent_id'); }
 public function payments(): HasMany { return $this->hasMany(BillingPayment::class,'payment_intent_id','payment_intent_id'); }
}