<?php

namespace App\Services\Billing\Reports;

use Illuminate\Validation\ValidationException;

final class BillingReportProviderRegistry
{
    /** @var array<string, BillingReportProvider> */
    private array $providers;

    public function __construct()
    {
        $configs = [
            ['subscriptions','الاشتراكات','دورة حياة الاشتراكات وحالاتها','billing_subscriptions','r.billing_account_id','account','created_at','billing_subscription_id','r.billing_subscription_id,r.billing_account_id,r.plan_id,r.plan_version_id,r.price_id,r.currency_id,r.status,r.current_period_start,r.current_period_end,r.trial_start,r.trial_end,r.cancel_at_period_end,r.created_at',['r.status','r.billing_subscription_id'],null,null,null,[['r.deleted_at','is null',null]]],
            ['trials','التجارب','حجوزات التجارب وحالات اعتمادها وبدئها وانتهائها','billing_trial_redemptions','r.billing_account_id','account','r.reserved_at','r.trial_redemption_id','r.trial_redemption_id,r.billing_account_id,r.billing_subscription_id,r.trial_policy_id,r.trial_policy_version_id,r.status,r.redemption_scope,r.reserved_at,r.started_at,r.completed_at',['r.status','r.trial_redemption_id'],null,null,null,[]],
            ['trial_conversion','تحويل التجارب','تحويل التجارب إلى اشتراكات مدفوعة بعد اعتماد التحصيل','billing_trial_redemptions','r.billing_account_id','account','r.reserved_at','r.trial_redemption_id','r.trial_redemption_id,r.billing_subscription_id,r.status as redemption_status,r.started_at,r.completed_at,s.status as subscription_status,s.current_period_start,s.current_period_end',['r.status','s.status','r.trial_redemption_id'],[['billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id']],null,null,[['s.deleted_at','is null',null]]],
            ['renewals','التجديدات','أحداث تجديد الاشتراك والفواتير المرتبطة بها','billing_subscription_events','ba.network_id','joined','r.occurred_at','r.event_id','r.event_id,r.billing_subscription_id,r.event_type,r.from_status,r.to_status,r.payload,r.occurred_at',['r.event_type','r.billing_subscription_id'],[['billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id'],['billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id']],null,null,[['r.event_type','in',['RENEWAL_INVOICE_ISSUED','ZERO_VALUE_RENEWAL_SETTLED','TRIAL_CONVERSION_INVOICE_ISSUED','TRIAL_CONVERTED_TO_FREE_PLAN','SUBSCRIPTION_CANCELED_AT_PERIOD_END','TRIAL_EXPIRED_WITHOUT_CONVERSION']]]],
            ['invoices','الفواتير','فواتير الاشتراكات ومبالغها وأرصدتها','billing_invoices','r.billing_account_id','account','r.created_at','r.invoice_id','r.invoice_id,r.invoice_number,r.billing_account_id,r.billing_subscription_id,r.currency_id,r.status,r.invoice_type,r.issued_on,r.due_on,r.total_minor,r.paid_minor,r.credited_minor,r.remaining_minor',['r.invoice_number','r.status'],null,null,null,[['r.status','!=','DRAFT'],['r.status','!=','VOID']]],
            ['collections','التحصيلات','سجل المدفوعات وحالات اعتمادها ومراجعها','billing_payments','r.billing_account_id','account','r.received_at','r.payment_id','r.payment_id,r.payment_reference,r.invoice_id,r.billing_account_id,r.currency_id,r.payment_method_id,r.amount_minor,r.status,r.received_at,r.verified_at',['r.payment_reference','r.external_reference','r.status'],null,null,null,[]],
            ['receivables','الذمم المدينة','الفواتير ذات المبالغ المستحقة والمتأخرة','billing_invoices','r.billing_account_id','account','r.due_on','r.invoice_id','r.invoice_id,r.invoice_number,r.billing_account_id,r.billing_subscription_id,r.currency_id,r.status,r.due_on,r.total_minor,r.paid_minor,r.credited_minor,r.remaining_minor',['r.invoice_number','r.status'],null,'r.remaining_minor','r.currency_id',[['r.remaining_minor','>',0],['r.status','!=','DRAFT'],['r.status','!=','VOID']]],
            ['usage','الاستخدام','تجميعات عدادات الاستخدام حسب فترة الاشتراك','billing_usage_aggregates','ba.network_id','joined','r.period_start','r.usage_aggregate_id','r.usage_aggregate_id,r.billing_subscription_id,r.meter_id,r.currency_id,r.period_start,r.period_end,r.quantity,r.amount_minor,r.last_event_at',['r.billing_subscription_id'],[['billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id'],['billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id']], 'r.amount_minor','r.currency_id',[]],
            ['dunning','التعثر والتحصيل الآلي','محاولات التذكير والتحصيل ومعالجة التأخر','billing_dunning_attempts','ba.network_id','joined','r.scheduled_at','r.dunning_attempt_id','r.dunning_attempt_id,r.invoice_id,r.attempt_number,r.status,r.channel,r.scheduled_at,r.attempted_at,r.result_message',['r.result_message','r.status'],[['billing_invoices as i','i.invoice_id','=','r.invoice_id'],['billing_accounts as ba','ba.billing_account_id','=','i.billing_account_id']],null,null,[]],
            ['refunds','الاستردادات','الاستردادات المكتملة ومبالغها حسب العملة','billing_refunds','ba.network_id','joined','r.created_at','r.refund_id','r.refund_id,r.refund_reference,r.payment_id,r.credit_note_id,r.currency_id,r.amount_minor,r.status,r.reason,r.processed_at',['r.refund_reference','r.reason','r.status'],[['billing_payments as p','p.payment_id','=','r.payment_id'],['billing_accounts as ba','ba.billing_account_id','=','p.billing_account_id']], 'r.amount_minor','r.currency_id',[['r.status','=','COMPLETED']]],
            ['revenue_recognition','الاعتراف بالإيراد','قيود الاعتراف بالإيراد ومواعيدها ومبالغها','billing_revenue_recognition_entries','ba.network_id','joined','r.recognized_through','r.revenue_recognition_entry_id','r.revenue_recognition_entry_id,r.revenue_schedule_id,r.journal_entry_id,r.recognized_through,r.amount_minor',['r.idempotency_key'],[['billing_revenue_recognition_schedules as s','s.revenue_schedule_id','=','r.revenue_schedule_id'],['billing_invoices as i','i.invoice_id','=','s.invoice_id'],['billing_accounts as ba','ba.billing_account_id','=','i.billing_account_id']], 'r.amount_minor','s.currency_id',[]],
            ['plan_performance','أداء الخطط','أداء الخطط والاشتراكات النشطة والقيمة الشهرية والسنوية المكافئة','billing_subscriptions','ba.network_id','joined','r.created_at','r.billing_subscription_id','r.billing_subscription_id,r.billing_account_id,r.plan_id,p.plan_key,p.name as plan_name,r.price_id,bp.billing_interval,bp.interval_count,bp.unit_amount_minor,r.quantity,r.currency_id,r.status,r.current_period_start,r.current_period_end',['p.name','p.plan_key','r.status'],[['billing_plans as p','p.plan_id','=','r.plan_id'],['billing_prices as bp','bp.price_id','=','r.price_id'],['billing_accounts as ba','ba.billing_account_id','=','r.billing_account_id']],null,null,[['r.status','=','ACTIVE'],['bp.billing_interval','!=','ONE_TIME'],['r.deleted_at','is null',null]]],
        ];

        $this->providers = [];
        foreach ($configs as $c) {
            [$key,$label,$description,$table,$scopeColumn,$scopeKind,$date,$keyColumn,$select,$search,$joins,$amount,$currency,$where] = $c;
            $providerClass = match ($key) {
                'subscriptions' => BillingSubscriptionsProvider::class,
                'trials' => BillingTrialsProvider::class,
                'trial_conversion' => BillingTrialConversionProvider::class,
                'renewals' => BillingRenewalsProvider::class,
                'plan_performance' => BillingPlanPerformanceProvider::class,
                'invoices' => BillingInvoicesProvider::class,
                'collections' => BillingCollectionsProvider::class,
                'receivables' => BillingReceivablesProvider::class,
                'usage' => BillingUsageProvider::class,
                'dunning' => BillingDunningProvider::class,
                'refunds' => BillingRefundsProvider::class,
                'revenue_recognition' => BillingRevenueRecognitionProvider::class,
                default => ConfiguredBillingReportProvider::class,
            };
            $this->providers[$key] = new $providerClass([
                'key'=>$key,'label'=>$label,'description'=>$description,'table'=>$table,
                'scope'=>['kind'=>$scopeKind,'column'=>$scopeColumn],
                'date'=>$date,'key_column'=>$keyColumn,'select'=>explode(',', $select),
                'search'=>$search,'joins'=>$joins ?? [],
                'amount'=>$amount,'currency'=>$currency,'where'=>$where,
                'status'=>str_contains($select, 'r.status') ? 'r.status' : null,
                'filter_ids' => $key === 'plan_performance' ? ['search'] : match ($key) {
                    'renewals','revenue_recognition' => ['from_date','to_date','search'],
                    default => ['from_date','to_date','status','search'],
                },
                'status_choices' => match ($key) {
                    'subscriptions' => ['INCOMPLETE','TRIALING','ACTIVE','PAST_DUE','PAUSED','SUSPENDED','CANCELED','ENDED'],
                    'trials','trial_conversion' => ['RESERVED','PENDING_APPROVAL','ACTIVE','COMPLETED','EXPIRED','REJECTED','REVOKED'],
                    'invoices' => ['OPEN','PARTIALLY_PAID','PARTIALLY_CREDITED','PAID','CREDITED','UNCOLLECTIBLE'],
                    'receivables' => ['OPEN','PARTIALLY_PAID','PARTIALLY_CREDITED'],
                    'collections' => ['PENDING_REVIEW','VERIFIED','FAILED','REJECTED','REFUNDED','PARTIALLY_REFUNDED'],
                    'refunds' => ['COMPLETED'],
                    'dunning' => ['PENDING','QUEUED','CANCELLED'],
                    default => [],
                },
                'metrics' => match ($key) {
                    'subscriptions' => ['active_subscriptions','trialing_subscriptions','past_due_subscriptions','canceled_subscriptions'],
                    'trials' => ['record_count','reserved','pending_approval','started','completed','expired','rejected','revoked'],
                    'trial_conversion' => ['trial_eligible','trial_reserved','trial_started','trial_completed','conversion_invoice_issued','paid_conversion','trial_conversion_percent','average_reservation_to_approval_hours','average_approval_to_start_hours'],
                    'renewals' => ['renewal_invoice_issued','zero_value_renewals_settled','trial_conversion_invoices_issued','trial_converted_to_free_plan','canceled_at_period_end','trial_expired_without_conversion'],
                    'plan_performance' => ['active_subscriptions','mrr_minor','arr_minor'],
                    'invoices' => ['record_count','invoiced_minor','credited_minor','outstanding_minor'],
                    'collections' => ['record_count','gross_collected_minor','refunded_minor','net_collected_minor'],
                    'receivables' => ['record_count','current_minor','days_1_30_minor','days_31_60_minor','days_61_90_minor','over_90_minor'],
                    'usage' => ['record_count','quantity','amount_minor'],
                    'dunning' => ['attempts','queued_reminders','pending_attempts','cancelled_attempts','recovered_invoices','recovery_rate_percent'],
                    'refunds' => ['record_count','amount_minor'],
                    'revenue_recognition' => ['record_count','recognized_in_period_minor','recognized_to_date_minor','deferred_revenue_minor'],
                    default => [],
                },
                'chart_slots' => match ($key) {
                    'trial_conversion' => ['trial_conversion_funnel','conversion_by_plan'],
                    'plan_performance' => ['mrr_arr_by_currency'],
                    'subscriptions' => ['subscription_status_distribution'],
                    'trials' => ['trial_status_distribution'],
                    'renewals' => ['renewal_outcomes'],
                    'invoices' => ['invoice_status_distribution','invoice_amounts_by_currency'],
                    'collections' => ['collection_status_distribution','collections_by_currency'],
                    'receivables' => ['receivables_aging_by_currency'],
                    'usage' => ['usage_amount_by_currency'],
                    'dunning' => ['dunning_outcomes'],
                    'refunds' => ['refund_amount_by_currency'],
                    'revenue_recognition' => ['revenue_by_currency'],
                    default => [],
                },
                'columns'=>array_map(static function ($field): array {
                    $field = trim($field);
                    $parts = preg_split('/\\s+as\\s+/i', $field);
                    $expression = trim($parts[0]);
                    $id = count($parts) > 1 ? trim($parts[1]) : substr($expression, strrpos($expression, '.') !== false ? strrpos($expression, '.') + 1 : 0);
                    $type = str_ends_with($id, '_minor') || str_ends_with($id, '_id') || str_contains($id, 'quantity') || str_contains($id, 'count')
                        ? 'number'
                        : ((str_ends_with($id, '_at') || str_ends_with($id, '_on') || str_contains($id, 'period_start') || str_contains($id, 'period_end')) ? 'date_time' : 'text');
                    return ['id'=>$id,'key'=>$id,'label'=>$id,'label_key'=>'billing.report.column.'.$id,'data_type'=>$type,'sortable'=>true];
                }, explode(',', $select)),
            ]);
        }
    }

    public function get(string $key): BillingReportProvider
    {
        return $this->providers[$key] ?? throw ValidationException::withMessages(['report_type' => ['تقرير الفوترة المطلوب غير معروف.']]);
    }

    public function all(): array { return array_values($this->providers); }
}
