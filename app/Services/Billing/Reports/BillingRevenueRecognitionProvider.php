<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingRevenueRecognitionProvider extends ConfiguredBillingReportProvider
{
 public function summary(?int $networkId,array $filters):array{
  $schedules=DB::table('billing_revenue_recognition_schedules as s')
   ->join('billing_invoices as i','i.invoice_id','=','s.invoice_id')
   ->join('billing_accounts as ba','ba.billing_account_id','=','i.billing_account_id');
  if($networkId!==null)$schedules->where('ba.network_id',$networkId);
  $balances=(clone $schedules)->select('s.currency_id')
   ->selectRaw('SUM(s.total_minor) as deferred_basis_minor')
   ->selectRaw('SUM(s.recognized_minor) as recognized_to_date_minor')
   ->selectRaw('SUM(s.total_minor-s.recognized_minor) as deferred_minor')
   ->groupBy('s.currency_id')->get()->keyBy('currency_id');
  $entries=DB::table('billing_revenue_recognition_entries as e')
   ->join('billing_revenue_recognition_schedules as s','s.revenue_schedule_id','=','e.revenue_schedule_id')
   ->join('billing_invoices as i','i.invoice_id','=','s.invoice_id')
   ->join('billing_accounts as ba','ba.billing_account_id','=','i.billing_account_id');
  if($networkId!==null)$entries->where('ba.network_id',$networkId);
  if(!empty($filters['from_date']))$entries->whereDate('e.recognized_through','>=',$filters['from_date']);
  if(!empty($filters['to_date']))$entries->whereDate('e.recognized_through','<=',$filters['to_date']);
  $recognized=$entries->select('s.currency_id')->selectRaw('SUM(e.amount_minor) as recognized_in_period_minor')
   ->groupBy('s.currency_id')->get()->keyBy('currency_id');
  $ids=$balances->keys()->merge($recognized->keys())->unique()->sort()->values();
  return ['record_count'=>(int)$entries->count(),'revenue_by_currency'=>$ids->map(function($id)use($balances,$recognized):array{
   $b=$balances->get($id);$r=(int)($recognized->get($id)?->recognized_in_period_minor??0);
   return ['currency_id'=>(int)$id,'recognized_in_period_minor'=>$r,
    'recognized_to_date_minor'=>(int)($b?->recognized_to_date_minor??0),
    'deferred_revenue_minor'=>(int)($b?->deferred_minor??0)];
  })->all()];
 }
}