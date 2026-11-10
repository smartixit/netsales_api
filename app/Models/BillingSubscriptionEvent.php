<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingSubscriptionEvent extends Model {
 use ExactDateSerialization;
 protected $table='billing_subscription_events'; protected $primaryKey='event_id'; public $timestamps=false;
 protected $fillable=['billing_subscription_id','event_type','from_status','to_status','payload','actor_id','occurred_at'];
 protected function casts(): array { return ['payload'=>'array','occurred_at'=>'datetime']; }
 public function subscription(): BelongsTo { return $this->belongsTo(BillingSubscription::class,'billing_subscription_id','billing_subscription_id'); }
}