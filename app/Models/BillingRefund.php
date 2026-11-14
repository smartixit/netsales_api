<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
/**
 * @property int $refund_id
 * @property string $refund_reference
 * @property string $idempotency_key
 * @property string $reason
 * @property int $processed_by
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property int $payment_id
 * @property int $credit_note_id
 * @property int $treasury_account_id
 * @property int $currency_id
 * @property int $amount_minor
 * @property string $status
 * @property-read BillingPayment $payment
 * @property-read BillingCreditNote $creditNote
 * @property-read Currency $currency
 */
class BillingRefund extends Model {
 use ExactDateSerialization;
 protected $table='billing_refunds'; protected $primaryKey='refund_id';
 protected $fillable=['refund_reference','idempotency_key','payment_id','credit_note_id','treasury_account_id','currency_id','amount_minor','status','reason','processed_by','processed_at'];
 protected static function booted(): void {
  static::updating(function (self $refund): void {
   if ($refund->getRawOriginal('status') !== 'PENDING'
       || array_diff(array_keys($refund->getDirty()), ['status','processed_at','updated_at']) !== []
       || $refund->status !== 'COMPLETED') {
    throw new LogicException('سجل الاسترداد غير قابل للتعديل بعد إنشائه؛ يسمح فقط بانتقال PENDING إلى COMPLETED.');
   }
  });
  static::deleting(function (): void {
   throw new LogicException('سجلات الاسترداد المالي لا تحذف.');
  });
 }
 protected function casts(): array { return ['amount_minor'=>'integer','processed_at'=>'datetime']; }
 public function payment(): BelongsTo { return $this->belongsTo(BillingPayment::class,'payment_id','payment_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
 public function creditNote(): BelongsTo { return $this->belongsTo(BillingCreditNote::class,'credit_note_id','credit_note_id'); }
 public function treasuryAccount(): BelongsTo { return $this->belongsTo(TreasuryAccount::class,'treasury_account_id','treasury_account_id'); }
}