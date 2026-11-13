<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingTrialsProvider extends ConfiguredBillingReportProvider
{
    public function summary(?int $networkId,array $filters):array
    {
        $q=DB::table('billing_trial_redemptions as r')->join('billing_accounts as ba','ba.billing_account_id','=','r.billing_account_id');
        if($networkId!==null)$q->where('ba.network_id',$networkId);
        if(!empty($filters['from_date']))$q->whereDate('r.reserved_at','>=',$filters['from_date']);
        if(!empty($filters['to_date']))$q->whereDate('r.reserved_at','<=',$filters['to_date']);
        if(!empty($filters['status']))$q->where('r.status',strtoupper((string)$filters['status']));
        $statuses=(clone $q)->select('r.status')->selectRaw('COUNT(*) as total')->groupBy('r.status')->get();
        $policies=(clone $q)->select('r.trial_policy_version_id','r.redemption_scope')->selectRaw('COUNT(*) as total')
          ->groupBy('r.trial_policy_version_id','r.redemption_scope')->get();
        $countStatus=static fn(string $status)=>(int)($statuses->firstWhere('status',$status)?->total??0);
        return [
          'record_count'=>(int)$statuses->sum('total'),
          'reserved'=>(int)$statuses->sum('total'),
          'pending_approval'=>$countStatus('PENDING_APPROVAL'),
          'started'=>(int)(clone $q)->whereNotNull('r.started_at')->count('r.trial_redemption_id'),
          'completed'=>$countStatus('COMPLETED'),
          'expired'=>$countStatus('EXPIRED'),
          'rejected'=>$countStatus('REJECTED'),
          'revoked'=>$countStatus('REVOKED'),
          'status_distribution'=>$statuses->map(static fn($x)=>['status'=>$x->status,'count'=>(int)$x->total])->all(),
          'by_policy_version_and_scope'=>$policies->map(static fn($x)=>[
            'trial_policy_version_id'=>(int)$x->trial_policy_version_id,'redemption_scope'=>$x->redemption_scope,'count'=>(int)$x->total,
          ])->all(),
        ];
    }
}