<?php
namespace App\Models;
use App\Enums\BillingPaymentStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
use LogicException;
/**
 * @property int $payment_id
 * @property int $invoice_id
 * @property int $billing_account_id
 * @property int $currency_id
 * @property int $payment_method_id
 * @property int|null $treasury_account_id
 * @property int $amount_minor
 * @property \App\Enums\BillingPaymentStatus $status
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property-read BillingInvoice $invoice
 * @property-read BillingAccount $billingAccount
 * @property-read Currency $currency
 * @property-read BillingPaymentMethod $paymentMethod
 * @property-read TreasuryAccount|null $treasuryAccount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BillingPaymentProof> $proofs
 */
class BillingPayment extends Model {
 use ExactDateSerialization;
 protected $table='billing_payments'; protected $primaryKey='payment_id';
 protected $fillable=['payment_reference','idempotency_key','payment_intent_id','invoice_id','billing_account_id','currency_id','payment_method_id','treasury_account_id','amount_minor','status','external_reference','received_at','verified_at','recorded_by','verified_by','review_note'];
 protected function casts(): array { return ['status'=>BillingPaymentStatus::class,'amount_minor'=>'integer','received_at'=>'datetime','verified_at'=>'datetime']; }
 protected static function booted(): void {
  static::updating(function (self $payment): void {
   $originalStatus = $payment->getRawOriginal('status');
   $refundTransition = in_array($originalStatus, [BillingPaymentStatus::VERIFIED->value, BillingPaymentStatus::PARTIALLY_REFUNDED->value], true)
    && $payment->isDirty('status')
    && in_array($payment->status, [BillingPaymentStatus::REFUNDED, BillingPaymentStatus::PARTIALLY_REFUNDED], true);
   if ($originalStatus !== BillingPaymentStatus::PENDING_REVIEW->value && ! $refundTransition) {
    throw new LogicException('لا يمكن تعديل دفعة بعد اعتمادها أو رفضها؛ استخدم مسار الاسترداد المالي.');
   }
   $allowed = ['status','treasury_account_id','verified_at','verified_by','review_note','updated_at'];
   if (array_diff(array_keys($payment->getDirty()), $allowed) !== []) {
    throw new LogicException('بيانات الدفعة المالية غير قابلة للتعديل بعد تسجيلها.');
   }
   if ($payment->isDirty('status')) {
    $newStatus = $payment->status instanceof BillingPaymentStatus ? $payment->status : BillingPaymentStatus::from((string) $payment->status);
    if (! in_array($newStatus, [BillingPaymentStatus::VERIFIED, BillingPaymentStatus::REJECTED, BillingPaymentStatus::REFUNDED, BillingPaymentStatus::PARTIALLY_REFUNDED], true)) {
     throw new LogicException('انتقال حالة الدفعة غير مسموح.');
    }
   }
  });
  static::deleting(function (): void {
   throw new LogicException('عمليات الدفع لا تحذف؛ استخدم حالة الرفض أو الاسترداد.');
  });
 }
 public function intent(): BelongsTo { return $this->belongsTo(BillingPaymentIntent::class,'payment_intent_id','payment_intent_id'); }
 public function invoice(): BelongsTo { return $this->belongsTo(BillingInvoice::class,'invoice_id','invoice_id'); }
 public function billingAccount(): BelongsTo { return $this->belongsTo(BillingAccount::class,'billing_account_id','billing_account_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
 public function paymentMethod(): BelongsTo { return $this->belongsTo(BillingPaymentMethod::class,'payment_method_id','payment_method_id'); }
 public function treasuryAccount(): BelongsTo { return $this->belongsTo(TreasuryAccount::class,'treasury_account_id','treasury_account_id'); }
 public function proofs(): HasMany { return $this->hasMany(BillingPaymentProof::class,'payment_id','payment_id'); }
 public function refunds(): HasMany { return $this->hasMany(BillingRefund::class,'payment_id','payment_id'); }
}