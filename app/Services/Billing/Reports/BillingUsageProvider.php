<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingUsageProvider extends ConfiguredBillingReportProvider
{
 public function summary(?int $networkId,array $filters):array{
  $q=DB::table('billing_usage_aggregates as r')
   ->join('billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id')
   ->join('billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id')
   ->whereNull('s.deleted_at');
  if($networkId!==null)$q->where('ba.network_id',$networkId);
  if(!empty($filters['from_date']))$q->whereDate('r.period_start','>=',$filters['from_date']);
  if(!empty($filters['to_date']))$q->whereDate('r.period_start','<=',$filters['to_date']);
  $recordCount=(int)(clone $q)->count('r.usage_aggregate_id');
  $quantity=(clone $q)->sum('r.quantity');
  $byMeter=(clone $q)->select('r.meter_id')->selectRaw('SUM(r.quantity) as quantity')->selectRaw('COUNT(*) as aggregates')
    ->groupBy('r.meter_id')->orderBy('r.meter_id')->get();
  $amounts=(clone $q)->join('currencies as c','c.currency_id','=','r.currency_id')
    ->select('r.currency_id','c.code as currency_code','c.name as currency_name','c.decimal_places')
    ->selectRaw('SUM(r.amount_minor) as amount_minor')
    ->groupBy('r.currency_id','c.code','c.name','c.decimal_places')->orderBy('r.currency_id')->get()
    ->map(static fn($x)=>['currency_id'=>(int)$x->currency_id,'currency_code'=>$x->currency_code,
      'currency_name'=>$x->currency_name,'decimal_places'=>(int)$x->decimal_places,'amount_minor'=>(int)$x->amount_minor])->all();
  return ['record_count'=>$recordCount,'quantity'=>(float)$quantity,
    'quantity_by_meter'=>$byMeter->map(static fn($x)=>['meter_id'=>(int)$x->meter_id,'quantity'=>(float)$x->quantity,'aggregates'=>(int)$x->aggregates])->all(),
    'amounts_by_currency'=>$amounts];
 }
}