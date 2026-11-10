<?php
namespace App\Models;
use App\Enums\BillingSubscriptionStatus;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
class BillingSubscription extends Model {
 use ExactDateSerialization, SoftDeletes;
 protected $table='billing_subscriptions'; protected $primaryKey='billing_subscription_id';
 protected $fillable=['billing_account_id','plan_id','plan_version_id','price_id','currency_id','status','collection_method','quantity','current_period_start','current_period_end','trial_start','trial_end','cancel_at_period_end','canceled_at','ended_at','paused_at','grace_period_ends_at','terms_snapshot','metadata','created_by'];
 protected function casts(): array { return ['status'=>BillingSubscriptionStatus::class,'quantity'=>'integer','cancel_at_period_end'=>'boolean','current_period_start'=>'datetime','current_period_end'=>'datetime','trial_start'=>'datetime','trial_end'=>'datetime','canceled_at'=>'datetime','ended_at'=>'datetime','paused_at'=>'datetime','grace_period_ends_at'=>'datetime','terms_snapshot'=>'array','metadata'=>'array']; }
 public function billingAccount(): BelongsTo { return $this->belongsTo(BillingAccount::class,'billing_account_id','billing_account_id'); }
 public function plan(): BelongsTo { return $this->belongsTo(BillingPlan::class,'plan_id','plan_id'); }
 public function planVersion(): BelongsTo { return $this->belongsTo(BillingPlanVersion::class,'plan_version_id','plan_version_id'); }
 public function price(): BelongsTo { return $this->belongsTo(BillingPrice::class,'price_id','price_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
 public function items(): HasMany { return $this->hasMany(BillingSubscriptionItem::class,'billing_subscription_id','billing_subscription_id'); }
 public function events(): HasMany { return $this->hasMany(BillingSubscriptionEvent::class,'billing_subscription_id','billing_subscription_id'); }
 public function invoices(): HasMany { return $this->hasMany(BillingInvoice::class,'billing_subscription_id','billing_subscription_id'); }
 public function usageEvents(): HasMany { return $this->hasMany(BillingUsageEvent::class,'billing_subscription_id','billing_subscription_id'); }
 public function scopeForAccount(Builder $q,int $id): Builder { return $q->where('billing_account_id',$id); }
}