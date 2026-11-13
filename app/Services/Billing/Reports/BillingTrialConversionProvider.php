<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingTrialConversionProvider extends ConfiguredBillingReportProvider
{
    private function window($q,string $column,array $filters):void{
        if(!empty($filters['from_date']))$q->whereDate($column,'>=',$filters['from_date']);
        if(!empty($filters['to_date']))$q->whereDate($column,'<=',$filters['to_date']);
    }
    private function applyRedemptionSearch($query, array $filters): void
    {
        $term = trim((string) ($filters['search'] ?? ''));
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(function ($matching) use ($like): void {
            $matching->where('r.status', 'like', $like)
                ->orWhere('s.status', 'like', $like)
                ->orWhereRaw('CAST(r.trial_redemption_id AS CHAR) LIKE ?', [$like]);
        });
    }

    private function applyEligibleSearch($query, array $filters): void
    {
        $term = trim((string) ($filters['search'] ?? ''));
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->whereExists(function ($matching) use ($like): void {
            $matching->selectRaw('1')
                ->from('billing_trial_redemptions as sr')
                ->join('billing_subscriptions as ss', 'ss.billing_subscription_id', '=', 'sr.billing_subscription_id')
                ->whereColumn('sr.billing_account_id', 'c.billing_account_id')
                ->whereColumn('ss.plan_id', 'c.plan_id')
                ->whereColumn('ss.price_id', 'c.price_id')
                ->whereRaw('COALESCE(sr.trial_policy_version_id, 0) = COALESCE(c.trial_policy_version_id, 0)')
                ->whereNull('ss.deleted_at')
                ->where(function ($matches) use ($like): void {
                    $matches->where('sr.status', 'like', $like)
                        ->orWhere('ss.status', 'like', $like)
                        ->orWhereRaw('CAST(sr.trial_redemption_id AS CHAR) LIKE ?', [$like]);
                });
        });
    }

    private function eventCount(?int $networkId, array $filters, string $eventType): int
    {
        $query = DB::table('billing_subscription_events as e')
            ->join('billing_trial_redemptions as r', 'r.billing_subscription_id', '=', 'e.billing_subscription_id')
            ->join('billing_subscriptions as s', 's.billing_subscription_id', '=', 'e.billing_subscription_id')
            ->join('billing_accounts as ba', 'ba.billing_account_id', '=', 'r.billing_account_id')
            ->whereNull('s.deleted_at')
            ->where('e.event_type', $eventType);

        if ($networkId !== null) {
            $query->where('ba.network_id', $networkId);
        }

        $this->window($query, 'e.occurred_at', $filters);
        $this->applyRedemptionSearch($query, $filters);

        return (int) $query->distinct()->count('r.trial_redemption_id');
    }

    public function summary(?int $networkId,array $filters):array{
        $eligible=DB::table('billing_trial_eligibility_checks as c')
          ->join('billing_plans as ep','ep.plan_id','=','c.plan_id')
          ->where('c.eligibility_status','ELIGIBLE');
        if($networkId!==null)$eligible->where('c.network_id',$networkId);
        $this->window($eligible,'c.checked_at',$filters);
        $this->applyEligibleSearch($eligible, $filters);
        $eligible=(int)$eligible->distinct()->count(DB::raw("CONCAT(c.billing_account_id,':',COALESCE(ep.product_id,0),':',c.plan_id,':',c.price_id,':',COALESCE(c.trial_policy_version_id,0))"));
        $r=DB::table('billing_trial_redemptions as r')
          ->join('billing_accounts as ba','ba.billing_account_id','=','r.billing_account_id')
          ->join('billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id')
          ->leftJoin('billing_plans as rp','rp.plan_id','=','s.plan_id');
        if($networkId!==null)$r->where('ba.network_id',$networkId);
        $r->whereNull('s.deleted_at');
        $this->window($r,'r.reserved_at',$filters);
        $this->applyRedemptionSearch($r, $filters);
        $stageCount=static fn($q)=>(int)$q->distinct()->count(DB::raw("CONCAT(r.billing_account_id,':',COALESCE(rp.product_id,0),':',s.plan_id,':',s.price_id,':',COALESCE(r.trial_policy_version_id,0),':',COALESCE(r.redemption_scope,'UNKNOWN'))"));
        $reserved=$stageCount(clone $r);
        $pending=$stageCount((clone $r)->where('r.status','PENDING_APPROVAL'));
        $started=$stageCount((clone $r)->whereNotNull('r.started_at'));
        $completed=$stageCount((clone $r)->where('r.status','COMPLETED'));
        // Lifecycle events are authoritative for expiry and rejection. The
        // redemption row is COMPLETED on natural EXPIRE and REVOKED on rejection.
        $expired=$this->eventCount($networkId, $filters, 'TRIAL_EXPIRED_WITHOUT_CONVERSION');
        $rejected=$this->eventCount($networkId, $filters, 'TRIAL_REDEMPTION_REJECTED');

        $revokedQuery = (clone $r)->where('r.status', 'REVOKED')
            ->whereNotExists(function ($events): void {
                $events->selectRaw('1')
                    ->from('billing_subscription_events as rejection_events')
                    ->whereColumn('rejection_events.billing_subscription_id', 'r.billing_subscription_id')
                    ->where('rejection_events.event_type', 'TRIAL_REDEMPTION_REJECTED');
            });
        $revoked=$stageCount($revokedQuery);
        $events=DB::table('billing_subscription_events as e')
          ->join('billing_subscriptions as s','s.billing_subscription_id','=','e.billing_subscription_id')
          ->join('billing_trial_redemptions as r','r.billing_subscription_id','=','s.billing_subscription_id')
          ->join('billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id')
          ->where('e.event_type','TRIAL_CONVERSION_INVOICE_ISSUED');
        if($networkId!==null)$events->where('ba.network_id',$networkId);
        $events->whereNull('s.deleted_at');
        $this->window($events,'e.occurred_at',$filters);
        $this->applyRedemptionSearch($events, $filters);
        $invoiceIssued=(int)$events->distinct()->count('e.billing_subscription_id');
        $paid=DB::table('billing_trial_redemptions as r')
          ->join('billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id')
          ->join('billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id')
          ->join('billing_invoices as i','i.billing_subscription_id','=','s.billing_subscription_id')
          ->where('r.status','COMPLETED')->where('s.status','ACTIVE')->where('i.status','PAID')
          ->whereExists(function ($eventQuery): void {
              $eventQuery->selectRaw('1')->from('billing_subscription_events as e')
                  ->whereColumn('e.billing_subscription_id','s.billing_subscription_id')
                  ->where('e.event_type','TRIAL_CONVERSION_INVOICE_ISSUED')
                  ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, '$.invoice_id')) AS UNSIGNED) = i.invoice_id");
          });
        if($networkId!==null)$paid->where('ba.network_id',$networkId);
        $paid->whereNull('s.deleted_at');
        $this->window($paid,'r.reserved_at',$filters);
        $this->applyRedemptionSearch($paid, $filters);
        $paid=(int)$paid->distinct()->count('r.trial_redemption_id');
        $approvalEvents=DB::table('billing_subscription_events as e')
          ->join('billing_trial_redemptions as r','r.billing_subscription_id','=','e.billing_subscription_id')
          ->join('billing_subscriptions as s','s.billing_subscription_id','=','e.billing_subscription_id')
          ->join('billing_accounts as ba','ba.billing_account_id','=','r.billing_account_id')
          ->whereIn('e.event_type',['TRIAL_APPROVED_WAITING_FIRST_ACTIVATION','TRIAL_APPROVED_AND_STARTED']);
        if($networkId!==null)$approvalEvents->where('ba.network_id',$networkId);
        $this->window($approvalEvents,'r.reserved_at',$filters);
        $this->applyRedemptionSearch($approvalEvents, $filters);
        $approvalWaitHours=(float)($approvalEvents->selectRaw('AVG(TIMESTAMPDIFF(SECOND,r.reserved_at,e.occurred_at))/3600 as average_hours')->value('average_hours')??0);
        $startWaitHours=(float)(clone $approvalEvents)->whereNotNull('r.started_at')
          ->selectRaw('AVG(TIMESTAMPDIFF(SECOND,e.occurred_at,r.started_at))/3600 as average_hours')->value('average_hours')??0;
        $segments=DB::table('billing_trial_eligibility_checks as c')
          ->where('c.eligibility_status','ELIGIBLE')
          ->join('billing_plans as p','p.plan_id','=','c.plan_id')
          ->leftJoin('billing_trial_policy_versions as tpv','tpv.trial_policy_version_id','=','c.trial_policy_version_id')
          ->select('c.trial_policy_version_id','p.product_id','c.plan_id','c.price_id','tpv.redemption_scope')
          ->selectRaw('COUNT(DISTINCT c.billing_account_id) as checks');
        if($networkId!==null)$segments->where('c.network_id',$networkId);
        $this->window($segments,'c.checked_at',$filters);
        $this->applyEligibleSearch($segments, $filters);
        $segments=$segments->groupBy('c.trial_policy_version_id','p.product_id','c.plan_id','c.price_id','tpv.redemption_scope')
          ->orderBy('c.trial_policy_version_id')->get()->map(static fn($x)=>[
            'trial_policy_version_id'=>$x->trial_policy_version_id,'product_id'=>$x->product_id,
            'plan_id'=>$x->plan_id,'price_id'=>$x->price_id,'redemption_scope'=>$x->redemption_scope,
            'eligible_checks'=>(int)$x->checks,
          ])->all();
        $rate=static fn(int $n,int $d):float=>$d===0?0.0:round($n/$d*100,2);
        return [
          'funnel'=>['trial_eligible'=>$eligible,'trial_reserved'=>$reserved,'pending_approval'=>$pending,
            'trial_started'=>$started,'trial_completed'=>$completed,'conversion_invoice_issued'=>$invoiceIssued,
            'paid_conversion'=>$paid,'expired_without_conversion'=>$expired,'rejected'=>$rejected,'revoked'=>$revoked],
          'rates'=>['eligible_to_reserved_percent'=>$rate($reserved,$eligible),
            'reserved_to_started_percent'=>$rate($started,$reserved),'started_to_completed_percent'=>$rate($completed,$started),
            'completed_to_invoice_percent'=>$rate($invoiceIssued,$completed),'invoice_to_paid_percent'=>$rate($paid,$invoiceIssued),
            'trial_conversion_percent'=>$rate($paid,$completed),'rejection_percent'=>$rate($rejected,$reserved),
            'average_reservation_to_approval_hours'=>round($approvalWaitHours,2),'average_approval_to_start_hours'=>round($startWaitHours,2)],
          'eligible_by_policy_plan_price'=>$segments,
        ];
    }
}