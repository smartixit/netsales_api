<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingTrialConversionProvider extends ConfiguredBillingReportProvider
{
    private function window($q,string $column,array $filters):void{
        if(!empty($filters['from_date']))$q->whereDate($column,'>=',$filters['from_date']);
        if(!empty($filters['to_date']))$q->whereDate($column,'<=',$filters['to_date']);
    }
    public function summary(?int $networkId,array $filters):array{
        $eligible=DB::table('billing_trial_eligibility_checks as c')->where('c.eligibility_status','ELIGIBLE');
        if($networkId!==null)$eligible->where('c.network_id',$networkId);
        $this->window($eligible,'c.checked_at',$filters);
        $eligible=(int)$eligible->distinct()->count(DB::raw("CONCAT(c.billing_account_id,':',c.price_id,':',COALESCE(c.trial_policy_version_id,0))"));
        $r=DB::table('billing_trial_redemptions as r')->join('billing_accounts as ba','ba.billing_account_id','=','r.billing_account_id');
        if($networkId!==null)$r->where('ba.network_id',$networkId);
        $this->window($r,'r.reserved_at',$filters);
        $reserved=(int)(clone $r)->count('r.trial_redemption_id');
        $pending=(int)(clone $r)->where('r.status','PENDING_APPROVAL')->count('r.trial_redemption_id');
        $started=(int)(clone $r)->whereNotNull('r.started_at')->count('r.trial_redemption_id');
        $completed=(int)(clone $r)->where('r.status','COMPLETED')->count('r.trial_redemption_id');
        $expired=(int)(clone $r)->where('r.status','EXPIRED')->count('r.trial_redemption_id');
        $rejected=(int)(clone $r)->where('r.status','REJECTED')->count('r.trial_redemption_id');
        $revoked=(int)(clone $r)->where('r.status','REVOKED')->count('r.trial_redemption_id');
        $events=DB::table('billing_subscription_events as e')
          ->join('billing_subscriptions as s','s.billing_subscription_id','=','e.billing_subscription_id')
          ->join('billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id')
          ->where('e.event_type','TRIAL_CONVERSION_INVOICE_ISSUED');
        if($networkId!==null)$events->where('ba.network_id',$networkId);
        $this->window($events,'e.occurred_at',$filters);
        $invoiceIssued=(int)$events->distinct()->count('e.billing_subscription_id');
        $paid=DB::table('billing_trial_redemptions as r')
          ->join('billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id')
          ->join('billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id')
          ->join('billing_invoices as i','i.billing_subscription_id','=','s.billing_subscription_id')
          ->where('s.status','ACTIVE')->where('i.status','PAID');
        if($networkId!==null)$paid->where('ba.network_id',$networkId);
        $this->window($paid,'r.reserved_at',$filters);
        $paid=(int)$paid->distinct()->count('r.trial_redemption_id');
        $segments=DB::table('billing_trial_eligibility_checks as c')
          ->where('c.eligibility_status','ELIGIBLE')
          ->select('c.trial_policy_version_id','c.plan_id','c.price_id')
          ->selectRaw('COUNT(*) as checks');
        if($networkId!==null)$segments->where('c.network_id',$networkId);
        $this->window($segments,'c.checked_at',$filters);
        $segments=$segments->groupBy('c.trial_policy_version_id','c.plan_id','c.price_id')
          ->orderBy('c.trial_policy_version_id')->get()->map(static fn($x)=>[
            'trial_policy_version_id'=>$x->trial_policy_version_id,'plan_id'=>$x->plan_id,
            'price_id'=>$x->price_id,'eligible_checks'=>(int)$x->checks,
          ])->all();
        $rate=static fn(int $n,int $d):float=>$d===0?0.0:round($n/$d*100,2);
        return [
          'funnel'=>['trial_eligible'=>$eligible,'trial_reserved'=>$reserved,'pending_approval'=>$pending,
            'trial_started'=>$started,'trial_completed'=>$completed,'conversion_invoice_issued'=>$invoiceIssued,
            'paid_conversion'=>$paid,'expired_without_conversion'=>$expired,'rejected'=>$rejected,'revoked'=>$revoked],
          'rates'=>['eligible_to_reserved_percent'=>$rate($reserved,$eligible),
            'reserved_to_started_percent'=>$rate($started,$reserved),'started_to_completed_percent'=>$rate($completed,$started),
            'completed_to_invoice_percent'=>$rate($invoiceIssued,$completed),'invoice_to_paid_percent'=>$rate($paid,$invoiceIssued),
            'trial_conversion_percent'=>$rate($paid,$completed),'rejection_percent'=>$rate($rejected,$reserved)],
          'eligible_by_policy_plan_price'=>$segments,
        ];
    }
}