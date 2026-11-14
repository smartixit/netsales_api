<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany,HasMany};
/**
 * @property int $payment_method_id
 * @property string $name
 * @property bool $is_active
 * @property bool $requires_proof
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Currency> $currencies
 */
class BillingPaymentMethod extends Model {
 use ExactDateSerialization;
 protected $table='billing_payment_methods'; protected $primaryKey='payment_method_id';
 protected $fillable=['method_key','name','method_type','requires_proof','is_active','instructions'];
 protected function casts(): array { return ['requires_proof'=>'boolean','is_active'=>'boolean','instructions'=>'array']; }
 public function currencies(): BelongsToMany { return $this->belongsToMany(Currency::class,'billing_payment_method_currencies','payment_method_id','currency_id','payment_method_id','currency_id')->withPivot(['is_active'])->withTimestamps(); }
 public function intents(): HasMany { return $this->hasMany(BillingPaymentIntent::class,'payment_method_id','payment_method_id'); }
 public function payments(): HasMany { return $this->hasMany(BillingPayment::class,'payment_method_id','payment_method_id'); }
}