<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class BillingCreditNote extends Model {
 use ExactDateSerialization;
 protected $table='billing_credit_notes'; protected $primaryKey='credit_note_id';
 protected $fillable=['credit_note_number','invoice_id','billing_account_id','currency_id','status','reason','subtotal_minor','tax_minor','total_minor','issued_at','created_by'];
 protected function casts(): array { return ['subtotal_minor'=>'integer','tax_minor'=>'integer','total_minor'=>'integer','issued_at'=>'datetime']; }
 public function invoice(): BelongsTo { return $this->belongsTo(BillingInvoice::class,'invoice_id','invoice_id'); }
 public function lines(): HasMany { return $this->hasMany(BillingCreditNoteLine::class,'credit_note_id','credit_note_id'); }
}