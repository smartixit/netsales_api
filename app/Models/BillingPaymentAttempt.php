<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingPaymentAttempt extends Model {
 use ExactDateSerialization;
 protected $table='billing_payment_attempts'; protected $primaryKey='payment_attempt_id';
 protected $fillable=['payment_intent_id','attempt_number','status','provider_reference','failure_code','failure_message','attempted_at','completed_at'];
 protected function casts(): array { return ['attempt_number'=>'integer','attempted_at'=>'datetime','completed_at'=>'datetime']; }
 public function intent(): BelongsTo { return $this->belongsTo(BillingPaymentIntent::class,'payment_intent_id','payment_intent_id'); }
}