<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingUsageAggregate extends Model {
 use ExactDateSerialization;
 protected $table='billing_usage_aggregates'; protected $primaryKey='usage_aggregate_id';
 protected $fillable=['billing_subscription_id','meter_id','period_start','period_end','quantity','amount_minor','currency_id','last_event_at'];
 protected function casts(): array { return ['period_start'=>'date','period_end'=>'date','quantity'=>'decimal:6','amount_minor'=>'integer','last_event_at'=>'datetime']; }
 public function subscription(): BelongsTo { return $this->belongsTo(BillingSubscription::class,'billing_subscription_id','billing_subscription_id'); }
 public function meter(): BelongsTo { return $this->belongsTo(BillingUsageMeter::class,'meter_id','meter_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
}