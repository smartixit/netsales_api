<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
class BillingRefund extends Model {
 use ExactDateSerialization;
 protected $table='billing_refunds'; protected $primaryKey='refund_id';
 protected $fillable=['refund_reference','idempotency_key','payment_id','currency_id','amount_minor','status','reason','processed_by','processed_at'];
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
}