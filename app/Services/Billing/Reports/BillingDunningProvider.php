<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingDunningProvider extends ConfiguredBillingReportProvider
{
 public function summary(?int $networkId,array $filters):array{
  $q=DB::table('billing_dunning_attempts as a')->join('billing_invoices as i','i.invoice_id','=','a.invoice_id')
   ->join('billing_accounts as ba','ba.billing_account_id','=','i.billing_account_id');
  if($networkId!==null)$q->where('ba.network_id',$networkId);
  if(!empty($filters['from_date']))$q->whereDate('a.scheduled_at','>=',$filters['from_date']);
  if(!empty($filters['to_date']))$q->whereDate('a.scheduled_at','<=',$filters['to_date']);
  if(!empty($filters['status']))$q->where('a.status',strtoupper((string)$filters['status']));
  $this->applySearchColumns($q, $filters, ['a.result_message', 'a.status']);
  $groups=(clone $q)->select('a.status')->selectRaw('COUNT(*) as total')->groupBy('a.status')->get();
  $attempts=(int)$groups->sum('total');
  $queued=(int)($groups->firstWhere('status','QUEUED')?->total??0);
  $pending=(int)($groups->firstWhere('status','PENDING')?->total??0);
  $cancelled=(int)($groups->firstWhere('status','CANCELLED')?->total??0);
  $recovered=(clone $q)->where('a.status','QUEUED')->where('i.status','PAID')->distinct()->count('i.invoice_id');
  $eligible=(clone $q)->where('a.status','QUEUED')->distinct()->count('i.invoice_id');
  return ['record_count'=>$attempts,'attempts'=>$attempts,'queued_reminders'=>$queued,'pending_attempts'=>$pending,
   'cancelled_attempts'=>$cancelled,'recovered_invoices'=>(int)$recovered,
   'recovery_rate_percent'=>$eligible===0?0.0:round(((int)$recovered/(int)$eligible)*100,2),
   'status_distribution'=>$groups->map(static fn($x)=>['status'=>$x->status,'count'=>(int)$x->total])->all()];
 }
}