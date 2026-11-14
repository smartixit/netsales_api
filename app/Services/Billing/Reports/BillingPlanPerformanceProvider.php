<?php
namespace App\Services\Billing\Reports;
use Illuminate\Support\Facades\DB;
final class BillingPlanPerformanceProvider extends ConfiguredBillingReportProvider
{
    public function summary(?int $networkId, array $filters): array
    {
        $query=DB::table('billing_subscriptions as s')
            ->join('billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id')
            ->join('billing_plans as p','p.plan_id','=','s.plan_id')
            ->join('billing_prices as bp','bp.price_id','=','s.price_id')
            ->join('currencies as c','c.currency_id','=','s.currency_id')
            ->where('s.status','ACTIVE')->where('bp.billing_interval','!=','ONE_TIME')->whereNull('s.deleted_at');
        if($networkId!==null)$query->where('ba.network_id',$networkId);
        $this->applySearchColumns($query, $filters, ['p.name', 'p.plan_key', 's.status']);
        $mrr="CASE bp.billing_interval
          WHEN 'DAY' THEN bp.unit_amount_minor*s.quantity*30/NULLIF(bp.interval_count,0)
          WHEN 'WEEK' THEN bp.unit_amount_minor*s.quantity*52/(12*NULLIF(bp.interval_count,0))
          WHEN 'MONTH' THEN bp.unit_amount_minor*s.quantity/NULLIF(bp.interval_count,0)
          WHEN 'YEAR' THEN bp.unit_amount_minor*s.quantity/(12*NULLIF(bp.interval_count,0))
          ELSE 0 END";
        $rows=$query->select('s.currency_id','c.code as currency_code','c.name as currency_name','c.decimal_places','s.plan_id','p.plan_key','p.name as plan_name')
          ->selectRaw('COUNT(DISTINCT s.billing_subscription_id) as active_subscriptions')
          ->selectRaw('ROUND(SUM('.$mrr.')) as mrr_minor')
          ->selectRaw('ROUND(SUM('.$mrr.')*12) as arr_minor')
          ->groupBy('s.currency_id','c.code','c.name','c.decimal_places','s.plan_id','p.plan_key','p.name')
          ->orderBy('s.currency_id')->orderBy('p.name')->get();
        return [
          'snapshot_at'=>now('Asia/Aden')->toDateTimeString(),
          'active_subscriptions'=>(int)$rows->sum('active_subscriptions'),
          'mrr_arr_by_plan_and_currency'=>$rows->map(static fn($r)=>[
            'currency_id'=>(int)$r->currency_id,'currency_code'=>$r->currency_code,'currency_name'=>$r->currency_name,'decimal_places'=>(int)$r->decimal_places,'plan_id'=>(int)$r->plan_id,
            'plan_key'=>$r->plan_key,'plan_name'=>$r->plan_name,
            'active_subscriptions'=>(int)$r->active_subscriptions,
            'mrr_minor'=>(int)$r->mrr_minor,'arr_minor'=>(int)$r->arr_minor,
          ])->all(),
        ];
    }
}