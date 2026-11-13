<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingReceivablesProvider extends ConfiguredBillingReportProvider
{
 public function summary(?int $networkId,array $filters):array{
  $q=DB::table('billing_invoices as i')
   ->whereIn('i.status',['OPEN','PARTIALLY_PAID','PARTIALLY_CREDITED'])
   ->where('i.remaining_minor','>',0);
  if($networkId!==null)$q->whereIn('i.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
  if(!empty($filters['from_date']))$q->whereDate('i.issued_on','>=',$filters['from_date']);
  if(!empty($filters['to_date']))$q->whereDate('i.issued_on','<=',$filters['to_date']);
  $asOf=now('Asia/Aden')->toDateString();
  $aging=$q->select('i.currency_id')
   ->selectRaw('SUM(CASE WHEN i.due_on >= ? THEN i.remaining_minor ELSE 0 END) as current_minor',[$asOf])
   ->selectRaw('SUM(CASE WHEN i.due_on < ? AND DATEDIFF(?,i.due_on) BETWEEN 1 AND 30 THEN i.remaining_minor ELSE 0 END) as days_1_30_minor',[$asOf,$asOf])
   ->selectRaw('SUM(CASE WHEN DATEDIFF(?,i.due_on) BETWEEN 31 AND 60 THEN i.remaining_minor ELSE 0 END) as days_31_60_minor',[$asOf])
   ->selectRaw('SUM(CASE WHEN DATEDIFF(?,i.due_on) BETWEEN 61 AND 90 THEN i.remaining_minor ELSE 0 END) as days_61_90_minor',[$asOf])
   ->selectRaw('SUM(CASE WHEN DATEDIFF(?,i.due_on) > 90 THEN i.remaining_minor ELSE 0 END) as over_90_minor',[$asOf])
   ->selectRaw('COUNT(*) as invoice_count')
   ->groupBy('i.currency_id')->orderBy('i.currency_id')->get();
  return ['record_count'=>(int)$aging->sum('invoice_count'),'aging_by_currency'=>$aging->map(static fn($x)=>[
   'currency_id'=>(int)$x->currency_id,'invoice_count'=>(int)$x->invoice_count,
   'current_minor'=>(int)$x->current_minor,'days_1_30_minor'=>(int)$x->days_1_30_minor,
   'days_31_60_minor'=>(int)$x->days_31_60_minor,'days_61_90_minor'=>(int)$x->days_61_90_minor,
   'over_90_minor'=>(int)$x->over_90_minor,
  ])->all()];
 }
}