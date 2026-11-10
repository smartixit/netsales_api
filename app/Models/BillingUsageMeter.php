<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class BillingUsageMeter extends Model {
 use ExactDateSerialization;
 protected $table='billing_usage_meters'; protected $primaryKey='meter_id';
 protected $fillable=['meter_key','name','unit','aggregation','reset_period','description','is_active'];
 protected function casts(): array { return ['is_active'=>'boolean']; }
 public function events(): HasMany { return $this->hasMany(BillingUsageEvent::class,'meter_id','meter_id'); }
 public function aggregates(): HasMany { return $this->hasMany(BillingUsageAggregate::class,'meter_id','meter_id'); }
}