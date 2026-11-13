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
        $statuses=(clone $q)->select('r.status')->selectRaw('COUNT(*) as total')->groupBy('r.status')->get();
        $policies=(clone $q)->select('r.trial_policy_version_id','r.redemption_scope')->selectRaw('COUNT(*) as total')
          ->groupBy('r.trial_policy_version_id','r.redemption_scope')->get();
        return [
          'record_count'=>(int)$statuses->sum('total'),
          'status_distribution'=>$statuses->map(static fn($x)=>['status'=>$x->status,'count'=>(int)$x->total])->all(),
          'by_policy_version_and_scope'=>$policies->map(static fn($x)=>[
            'trial_policy_version_id'=>(int)$x->trial_policy_version_id,'redemption_scope'=>$x->redemption_scope,'count'=>(int)$x->total,
          ])->all(),
        ];
    }
}