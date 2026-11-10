<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingUsageEvent extends Model {
 use ExactDateSerialization;
 protected $table='billing_usage_events'; protected $primaryKey='usage_event_id';
 protected $fillable=['billing_subscription_id','meter_id','idempotency_key','quantity','occurred_at','period_start','period_end','dimensions','source_type','source_id'];
 protected function casts(): array { return ['quantity'=>'decimal:6','occurred_at'=>'datetime','period_start'=>'date','period_end'=>'date','dimensions'=>'array']; }
 public function subscription(): BelongsTo { return $this->belongsTo(BillingSubscription::class,'billing_subscription_id','billing_subscription_id'); }
 public function meter(): BelongsTo { return $this->belongsTo(BillingUsageMeter::class,'meter_id','meter_id'); }
}