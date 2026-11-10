<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingUsageReservation extends Model {
 use ExactDateSerialization;
 protected $table='billing_usage_reservations'; protected $primaryKey='reservation_id';
 protected $fillable=['billing_subscription_id','meter_id','reservation_key','quantity','status','expires_at','committed_at'];
 protected function casts(): array { return ['quantity'=>'decimal:6','expires_at'=>'datetime','committed_at'=>'datetime']; }
 public function subscription(): BelongsTo { return $this->belongsTo(BillingSubscription::class,'billing_subscription_id','billing_subscription_id'); }
 public function meter(): BelongsTo { return $this->belongsTo(BillingUsageMeter::class,'meter_id','meter_id'); }
}