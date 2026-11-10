<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingPaymentProof extends Model {
 use ExactDateSerialization;
 protected $table='billing_payment_proofs'; protected $primaryKey='payment_proof_id';
 protected $fillable=['payment_id','file_path','original_name','mime_type','file_size','sha256','uploaded_by','uploaded_at'];
 protected function casts(): array { return ['file_size'=>'integer','uploaded_at'=>'datetime']; }
 public function payment(): BelongsTo { return $this->belongsTo(BillingPayment::class,'payment_id','payment_id'); }
}