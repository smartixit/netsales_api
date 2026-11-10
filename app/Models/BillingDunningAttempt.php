<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingDunningAttempt extends Model {
 use ExactDateSerialization;
 protected $table='billing_dunning_attempts'; protected $primaryKey='dunning_attempt_id';
 protected $fillable=['invoice_id','attempt_number','status','channel','scheduled_at','attempted_at','result_message'];
 protected function casts(): array { return ['attempt_number'=>'integer','scheduled_at'=>'datetime','attempted_at'=>'datetime']; }
 public function invoice(): BelongsTo { return $this->belongsTo(BillingInvoice::class,'invoice_id','invoice_id'); }
}