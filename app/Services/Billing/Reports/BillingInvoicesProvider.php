<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingInvoicesProvider extends ConfiguredBillingReportProvider
{
    public function summary(?int $networkId,array $filters):array
    {
        $q=DB::table('billing_invoices as i')->whereNotIn('i.status',['DRAFT','VOID'])->whereNotNull('i.issued_on');
        if($networkId!==null)$q->whereIn('i.billing_account_id',DB::table('billing_accounts')->select('billing_account_id')->where('network_id',$networkId));
        if(!empty($filters['from_date']))$q->whereDate('i.issued_on','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$q->whereDate('i.issued_on','<=',$filters['to_date']);
        $totals=$q->select('i.currency_id')->selectRaw('COUNT(*) as invoice_count')
          ->selectRaw('SUM(i.total_minor) as invoiced_minor')
          ->selectRaw('SUM(i.credited_minor) as credited_minor')
          ->selectRaw('SUM(i.remaining_minor) as outstanding_minor')
          ->groupBy('i.currency_id')->orderBy('i.currency_id')->get();
        return [
          'record_count'=>(int)$totals->sum('invoice_count'),
          'amounts_by_currency'=>$totals->map(static fn($x)=>[
            'currency_id'=>(int)$x->currency_id,'invoice_count'=>(int)$x->invoice_count,
            'invoiced_minor'=>(int)$x->invoiced_minor,'credited_minor'=>(int)$x->credited_minor,
            'outstanding_minor'=>(int)$x->outstanding_minor,
          ])->all(),
        ];
    }
}