<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingCollectionsProvider extends ConfiguredBillingReportProvider
{
    public function summary(?int $networkId,array $filters):array
    {
        $payments=DB::table('billing_payments as p')->join('currencies as pc','pc.currency_id','=','p.currency_id')->whereIn('p.status',['VERIFIED','PARTIALLY_REFUNDED','REFUNDED']);
        if($networkId!==null)$payments->whereIn('p.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
        if(!empty($filters['from_date']))$payments->whereDate('p.received_at','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$payments->whereDate('p.received_at','<=',$filters['to_date']);
        if(!empty($filters['status']))$payments->where('p.status',strtoupper((string)$filters['status']));
        $gross=$payments->select('p.currency_id','pc.code as currency_code','pc.name as currency_name','pc.decimal_places')->selectRaw('SUM(p.amount_minor) as gross_minor')->groupBy('p.currency_id','pc.code','pc.name','pc.decimal_places')->get()->keyBy('currency_id');
        $refunds=DB::table('billing_refunds as r')->join('billing_payments as p','p.payment_id','=','r.payment_id')->join('currencies as rc','rc.currency_id','=','r.currency_id')
          ->where('r.status','COMPLETED');
        if($networkId!==null)$refunds->whereIn('p.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
        if(!empty($filters['from_date']))$refunds->whereDate('r.processed_at','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$refunds->whereDate('r.processed_at','<=',$filters['to_date']);
        if(!empty($filters['status']))$refunds->where('p.status',strtoupper((string)$filters['status']));
        $refunded=$refunds->select('r.currency_id','rc.code as currency_code','rc.name as currency_name','rc.decimal_places')->selectRaw('SUM(r.amount_minor) as refunded_minor')->groupBy('r.currency_id','rc.code','rc.name','rc.decimal_places')->get()->keyBy('currency_id');
        $currencyIds=$gross->keys()->merge($refunded->keys())->unique()->sort()->values();
        $amounts=$currencyIds->map(function($id)use($gross,$refunded):array{
          $g=(int)($gross->get($id)?->gross_minor??0);$r=(int)($refunded->get($id)?->refunded_minor??0);
          return ['currency_id'=>(int)$id,'currency_code'=>$gross->get($id)?->currency_code??$refunded->get($id)?->currency_code,'currency_name'=>$gross->get($id)?->currency_name??$refunded->get($id)?->currency_name,'decimal_places'=>(int)($gross->get($id)?->decimal_places??$refunded->get($id)?->decimal_places??2),'gross_collected_minor'=>$g,'refunded_minor'=>$r,'net_collected_minor'=>$g-$r];
        })->all();
        $statusCounts=DB::table('billing_payments as p');
        if($networkId!==null)$statusCounts->whereIn('p.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
        if(!empty($filters['from_date']))$statusCounts->whereDate('p.received_at','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$statusCounts->whereDate('p.received_at','<=',$filters['to_date']);
        if(!empty($filters['status']))$statusCounts->where('p.status',strtoupper((string)$filters['status']));
        $statuses=$statusCounts->select('p.status')->selectRaw('COUNT(*) as total')->groupBy('p.status')->get();
        return ['record_count'=>(int)$statuses->sum('total'),'collection_statuses'=>$statuses->map(static fn($x)=>['status'=>$x->status,'count'=>(int)$x->total])->all(),'collections_by_currency'=>$amounts];
    }
}