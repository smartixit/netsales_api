<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingCollectionsProvider extends ConfiguredBillingReportProvider
{
    public function summary(?int $networkId,array $filters):array
    {
        $payments=DB::table('billing_payments as p')->whereIn('p.status',['VERIFIED','PARTIALLY_REFUNDED','REFUNDED']);
        if($networkId!==null)$payments->whereIn('p.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
        if(!empty($filters['from_date']))$payments->whereDate('p.received_at','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$payments->whereDate('p.received_at','<=',$filters['to_date']);
        $gross=$payments->select('p.currency_id')->selectRaw('SUM(p.amount_minor) as gross_minor')->groupBy('p.currency_id')->get()->keyBy('currency_id');
        $refunds=DB::table('billing_refunds as r')->join('billing_payments as p','p.payment_id','=','r.payment_id')
          ->where('r.status','COMPLETED');
        if($networkId!==null)$refunds->whereIn('p.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
        if(!empty($filters['from_date']))$refunds->whereDate('r.processed_at','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$refunds->whereDate('r.processed_at','<=',$filters['to_date']);
        $refunded=$refunds->select('r.currency_id')->selectRaw('SUM(r.amount_minor) as refunded_minor')->groupBy('r.currency_id')->get()->keyBy('currency_id');
        $currencyIds=$gross->keys()->merge($refunded->keys())->unique()->sort()->values();
        $amounts=$currencyIds->map(function($id)use($gross,$refunded):array{
          $g=(int)($gross->get($id)?->gross_minor??0);$r=(int)($refunded->get($id)?->refunded_minor??0);
          return ['currency_id'=>(int)$id,'gross_collected_minor'=>$g,'refunded_minor'=>$r,'net_collected_minor'=>$g-$r];
        })->all();
        $statusCounts=DB::table('billing_payments as p');
        if($networkId!==null)$statusCounts->whereIn('p.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
        if(!empty($filters['from_date']))$statusCounts->whereDate('p.received_at','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$statusCounts->whereDate('p.received_at','<=',$filters['to_date']);
        $statuses=$statusCounts->select('p.status')->selectRaw('COUNT(*) as total')->groupBy('p.status')->get();
        return ['record_count'=>(int)$statuses->sum('total'),'collection_statuses'=>$statuses->map(static fn($x)=>['status'=>$x->status,'count'=>(int)$x->total])->all(),'collections_by_currency'=>$amounts];
    }
}