<?php
namespace App\Models;
use App\Traits\ExactDateSerialization;
use Illuminate\Database\Eloquent\Model;
class BillingAutomationJob extends Model {
 use ExactDateSerialization;
 protected $table='billing_automation_jobs'; protected $primaryKey='billing_job_id';
 protected $fillable=['job_type','aggregate_type','aggregate_id','status','scheduled_at','attempt_count','last_error','payload'];
 protected function casts(): array { return ['scheduled_at'=>'datetime','attempt_count'=>'integer','payload'=>'array']; }
}