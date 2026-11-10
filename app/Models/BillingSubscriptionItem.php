<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingSubscriptionItem extends Model {
 use ExactDateSerialization;
 protected $table='billing_subscription_items'; protected $primaryKey='subscription_item_id';
 protected $fillable=['billing_subscription_id','plan_feature_id','item_type','description','quantity','unit_amount_minor','amount_minor','currency_id','terms_snapshot'];
 protected function casts(): array { return ['quantity'=>'decimal:6','unit_amount_minor'=>'integer','amount_minor'=>'integer','terms_snapshot'=>'array']; }
 public function subscription(): BelongsTo { return $this->belongsTo(BillingSubscription::class,'billing_subscription_id','billing_subscription_id'); }
 public function feature(): BelongsTo { return $this->belongsTo(BillingPlanFeature::class,'plan_feature_id','plan_feature_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
}