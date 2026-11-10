<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BillingInvoiceLine extends Model {
 use ExactDateSerialization;
 protected $table='billing_invoice_lines'; protected $primaryKey='invoice_line_id';
 protected $fillable=['invoice_id','currency_id','line_type','description','quantity','unit_amount_minor','subtotal_minor','discount_minor','tax_minor','total_minor','plan_version_id','price_id','source_snapshot'];
 protected function casts(): array { return ['quantity'=>'decimal:6','unit_amount_minor'=>'integer','subtotal_minor'=>'integer','discount_minor'=>'integer','tax_minor'=>'integer','total_minor'=>'integer','source_snapshot'=>'array']; }
 public function invoice(): BelongsTo { return $this->belongsTo(BillingInvoice::class,'invoice_id','invoice_id'); }
 public function currency(): BelongsTo { return $this->belongsTo(Currency::class,'currency_id','currency_id'); }
 public function planVersion(): BelongsTo { return $this->belongsTo(BillingPlanVersion::class,'plan_version_id','plan_version_id'); }
 public function price(): BelongsTo { return $this->belongsTo(BillingPrice::class,'price_id','price_id'); }
}