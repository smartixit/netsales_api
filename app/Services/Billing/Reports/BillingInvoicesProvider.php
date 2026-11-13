<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingInvoicesProvider extends ConfiguredBillingReportProvider
{
    public function summary(?int $networkId,array $filters):array
    {
        $q=DB::table('billing_invoices as i')->join('currencies as c','c.currency_id','=','i.currency_id')->whereNotIn('i.status',['DRAFT','VOID'])->whereNotNull('i.issued_on');
        if($networkId!==null)$q->whereIn('i.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
        if(!empty($filters['from_date']))$q->whereDate('i.issued_on','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$q->whereDate('i.issued_on','<=',$filters['to_date']);
        if(!empty($filters['status']))$q->where('i.status',strtoupper((string)$filters['status']));
        $statuses=(clone $q)->select('i.status')->selectRaw('COUNT(*) as total')->groupBy('i.status')->get();
        $totals=(clone $q)->select('i.currency_id','c.code as currency_code','c.name as currency_name','c.decimal_places')->selectRaw('COUNT(*) as invoice_count')
          ->selectRaw('SUM(i.total_minor) as invoiced_minor')
          ->selectRaw('SUM(i.credited_minor) as credited_minor')
          ->selectRaw('SUM(i.remaining_minor) as outstanding_minor')
          ->groupBy('i.currency_id','c.code','c.name','c.decimal_places')->orderBy('i.currency_id')->get();
        return [
          'record_count'=>(int)$totals->sum('invoice_count'),
          'status_distribution'=>$statuses->map(static fn($x)=>['status'=>$x->status,'count'=>(int)$x->total])->all(),
          'amounts_by_currency'=>$totals->map(static fn($x)=>[
            'currency_id'=>(int)$x->currency_id,'currency_code'=>$x->currency_code,'currency_name'=>$x->currency_name,'decimal_places'=>(int)$x->decimal_places,'invoice_count'=>(int)$x->invoice_count,
            'invoiced_minor'=>(int)$x->invoiced_minor,'credited_minor'=>(int)$x->credited_minor,
            'outstanding_minor'=>(int)$x->outstanding_minor,
          ])->all(),
        ];
    }
}