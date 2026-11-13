<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingSubscriptionsProvider extends ConfiguredBillingReportProvider
{
    public function summary(?int $networkId,array $filters):array
    {
        $q=DB::table('billing_subscriptions as s')->join('billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id')->whereNull('s.deleted_at');
        if($networkId!==null)$q->where('ba.network_id',$networkId);
        if(!empty($filters['from_date']))$q->whereDate('s.created_at','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$q->whereDate('s.created_at','<=',$filters['to_date']);
        if(!empty($filters['status']))$q->where('s.status',strtoupper((string)$filters['status']));
        $this->applySearchColumns($q, $filters, ['s.status', 'CAST(s.billing_subscription_id AS CHAR)']);
        $counts=(clone $q)->select('s.status')->selectRaw('COUNT(*) as total')->groupBy('s.status')->get();
        $byCurrency=(clone $q)->select('s.currency_id')->selectRaw('COUNT(*) as total')->groupBy('s.currency_id')->get();
        $status=fn(string $v)=>(int)($counts->firstWhere('status',$v)?->total??0);
        return [
          'record_count'=>(int)$counts->sum('total'),
          'active_subscriptions'=>$status('ACTIVE'),
          'trialing_subscriptions'=>$status('TRIALING'),
          'past_due_subscriptions'=>$status('PAST_DUE'),
          'canceled_subscriptions'=>$status('CANCELED'),
          'subscriptions_by_currency'=>$byCurrency->map(static fn($x)=>['currency_id'=>(int)$x->currency_id,'count'=>(int)$x->total])->all(),
          'status_distribution'=>$counts->map(static fn($x)=>['status'=>$x->status,'count'=>(int)$x->total])->all(),
        ];
    }
}