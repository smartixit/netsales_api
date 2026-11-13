<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingRenewalsProvider extends ConfiguredBillingReportProvider
{
 public function summary(?int $networkId,array $filters):array{
  $q=DB::table('billing_subscription_events as e')
   ->join('billing_subscriptions as s','s.billing_subscription_id','=','e.billing_subscription_id')
   ->join('billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id')
   ->whereIn('e.event_type',['RENEWAL_INVOICE_ISSUED','ZERO_VALUE_RENEWAL_SETTLED','TRIAL_CONVERSION_INVOICE_ISSUED','TRIAL_CONVERTED_TO_FREE_PLAN','SUBSCRIPTION_CANCELED_AT_PERIOD_END','TRIAL_EXPIRED_WITHOUT_CONVERSION']);
  if($networkId!==null)$q->where('ba.network_id',$networkId);
  if(!empty($filters['from_date']))$q->whereDate('e.occurred_at','>=',$filters['from_date']);
  if(!empty($filters['to_date']))$q->whereDate('e.occurred_at','<=',$filters['to_date']);
  $groups=$q->select('e.event_type')->selectRaw('COUNT(*) as total')->groupBy('e.event_type')->get();
  $counts=$groups->keyBy('event_type');
  $count=static fn(string $type)=>(int)($counts->get($type)?->total??0);
  return ['record_count'=>(int)$groups->sum('total'),'renewal_invoice_issued'=>$count('RENEWAL_INVOICE_ISSUED'),
   'zero_value_renewals_settled'=>$count('ZERO_VALUE_RENEWAL_SETTLED'),
   'trial_conversion_invoices_issued'=>$count('TRIAL_CONVERSION_INVOICE_ISSUED'),
   'trial_converted_to_free_plan'=>$count('TRIAL_CONVERTED_TO_FREE_PLAN'),
   'canceled_at_period_end'=>$count('SUBSCRIPTION_CANCELED_AT_PERIOD_END'),
   'trial_expired_without_conversion'=>$count('TRIAL_EXPIRED_WITHOUT_CONVERSION'),
   'event_distribution'=>$groups->map(static fn($x)=>['event_type'=>$x->event_type,'count'=>(int)$x->total])->all()];
 }
}