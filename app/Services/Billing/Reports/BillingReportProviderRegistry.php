<?php

namespace App\Services\Billing\Reports;

use Illuminate\\Validation\\ValidationException;

final class BillingReportProviderRegistry
{
    /** @var array<string, BillingReportProvider> */
    private array $providers;

    public function __construct()
    {
        $configs = [
            ['subscriptions','الاشتراكات','دورة حياة الاشتراكات وحالاتها','billing_subscriptions','r.billing_account_id','account','created_at','billing_subscription_id','r.billing_subscription_id,r.billing_account_id,r.plan_id,r.plan_version_id,r.price_id,r.currency_id,r.status,r.current_period_start,r.current_period_end,r.trial_start,r.trial_end,r.cancel_at_period_end,r.created_at',['r.status','r.billing_subscription_id'],null,null,[]],
            ['trial_redemptions','استخدام التجارب','حجوزات التجارب وحالات اعتمادها وبدئها','billing_trial_redemptions','r.billing_account_id','account','r.reserved_at','r.trial_redemption_id','r.trial_redemption_id,r.billing_account_id,r.billing_subscription_id,r.trial_policy_id,r.trial_policy_version_id,r.status,r.redemption_scope,r.reserved_at,r.started_at,r.completed_at',['r.status','r.trial_redemption_id'],null,null,[]],
            ['trial_conversion','تحويل التجارب','تحويل التجارب إلى اشتراكات مدفوعة بعد اعتماد التحصيل','billing_trial_redemptions','r.billing_account_id','account','r.reserved_at','r.trial_redemption_id','r.trial_redemption_id,r.billing_subscription_id,r.status as redemption_status,r.started_at,r.completed_at,s.status as subscription_status,s.current_period_start,s.current_period_end',['r.status','s.status','r.trial_redemption_id'],[['billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id']],null,null,[]],
            ['renewals','التجديدات','فواتير تجديد الاشتراكات واستحقاقاتها','billing_invoices','r.billing_account_id','account','r.issued_on','r.invoice_id','r.invoice_id,r.invoice_number,r.billing_account_id,r.billing_subscription_id,r.currency_id,r.status,r.invoice_type,r.issued_on,r.due_on,r.total_minor,r.paid_minor,r.remaining_minor',['r.invoice_number','r.status'],null,'r.total_minor','r.currency_id',[['r.invoice_type','=','RENEWAL']]],
            ['invoices','الفواتير','فواتير الاشتراكات ومبالغها وأرصدتها','billing_invoices','r.billing_account_id','account','r.issued_on','r.invoice_id','r.invoice_id,r.invoice_number,r.billing_account_id,r.billing_subscription_id,r.currency_id,r.status,r.invoice_type,r.issued_on,r.due_on,r.total_minor,r.paid_minor,r.credited_minor,r.remaining_minor',['r.invoice_number','r.status'],null,'r.total_minor','r.currency_id',[]],
            ['collections','التحصيلات','المدفوعات والتحصيلات المعتمدة والمعلقة','billing_payments','r.billing_account_id','account','r.received_at','r.payment_id','r.payment_id,r.payment_reference,r.invoice_id,r.billing_account_id,r.currency_id,r.payment_method_id,r.amount_minor,r.status,r.received_at,r.verified_at',['r.payment_reference','r.external_reference','r.status'],null,'r.amount_minor','r.currency_id',[]],
            ['receivables','الذمم المدينة','الفواتير ذات المبالغ المستحقة والمتأخرة','billing_invoices','r.billing_account_id','account','r.due_on','r.invoice_id','r.invoice_id,r.invoice_number,r.billing_account_id,r.billing_subscription_id,r.currency_id,r.status,r.due_on,r.total_minor,r.paid_minor,r.credited_minor,r.remaining_minor',['r.invoice_number','r.status'],null,'r.remaining_minor','r.currency_id',[['r.remaining_minor','>',0]]],
            ['usage','الاستخدام','تجميعات عدادات الاستخدام حسب فترة الاشتراك','billing_usage_aggregates','ba.network_id','joined','r.period_start','r.usage_aggregate_id','r.usage_aggregate_id,r.billing_subscription_id,r.meter_id,r.currency_id,r.period_start,r.period_end,r.quantity,r.amount_minor,r.last_event_at',['r.billing_subscription_id'],[['billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id'],['billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id']], 'r.amount_minor','r.currency_id',[]],
            ['dunning','التعثر والتحصيل الآلي','محاولات التذكير والتحصيل ومعالجة التأخر','billing_dunning_attempts','ba.network_id','joined','r.scheduled_at','r.dunning_attempt_id','r.dunning_attempt_id,r.invoice_id,r.attempt_number,r.status,r.channel,r.scheduled_at,r.attempted_at,r.result_message',['r.result_message','r.status'],[['billing_invoices as i','i.invoice_id','=','r.invoice_id'],['billing_accounts as ba','ba.billing_account_id','=','i.billing_account_id']],null,null,[]],
            ['refunds','الاستردادات','طلبات الاسترداد المنفذة والمعلقة','billing_refunds','ba.network_id','joined','r.processed_at','r.refund_id','r.refund_id,r.refund_reference,r.payment_id,r.credit_note_id,r.currency_id,r.amount_minor,r.status,r.reason,r.processed_at',['r.refund_reference','r.reason','r.status'],[['billing_payments as p','p.payment_id','=','r.payment_id'],['billing_accounts as ba','ba.billing_account_id','=','p.billing_account_id']], 'r.amount_minor','r.currency_id',[]],
            ['revenue_recognition','الاعتراف بالإيراد','قيود الاعتراف بالإيراد ومواعيدها ومبالغها','billing_revenue_recognition_entries','ba.network_id','joined','r.recognized_through','r.revenue_recognition_entry_id','r.revenue_recognition_entry_id,r.revenue_schedule_id,r.journal_entry_id,r.recognized_through,r.amount_minor',['r.idempotency_key'],[['billing_revenue_recognition_schedules as s','s.revenue_schedule_id','=','r.revenue_schedule_id'],['billing_invoices as i','i.invoice_id','=','s.invoice_id'],['billing_accounts as ba','ba.billing_account_id','=','i.billing_account_id']], 'r.amount_minor','s.currency_id',[]],
            ['subscription_events','أحداث الاشتراك','سجل أحداث دورة حياة الاشتراك والتغييرات','billing_subscription_events','ba.network_id','joined','r.occurred_at','r.event_id','r.event_id,r.billing_subscription_id,r.event_type,r.from_status,r.to_status,r.actor_id,r.occurred_at',['r.event_type','r.from_status','r.to_status'],[['billing_subscriptions as s','s.billing_subscription_id','=','r.billing_subscription_id'],['billing_accounts as ba','ba.billing_account_id','=','s.billing_account_id']],null,null,[]],
        ];

        $this->providers = [];
        foreach ($configs as $c) {
            [$key,$label,$description,$table,$scopeColumn,$scopeKind,$date,$keyColumn,$select,$search,$joins,$amount,$currency,$where] = $c;
            $this->providers[$key] = new ConfiguredBillingReportProvider([
                'key'=>$key,'label'=>$label,'description'=>$description,'table'=>$table,
                'scope'=>['kind'=>$scopeKind,'column'=>$scopeColumn],
                'date'=>$date,'key_column'=>$keyColumn,'select'=>explode(',', $select),
                'search'=>$search,'joins'=>$joins ?? [],
                'amount'=>$amount,'currency'=>$currency,'where'=>$where,
                'status'=>str_contains($select, 'r.status') ? 'r.status' : null,
                'columns'=>array_map(static fn ($field): array => ['key'=>trim($field),'label'=>trim($field)], explode(',', $select)),
            ]);
        }
    }

    public function get(string $key): BillingReportProvider
    {
        return $this->providers[$key] ?? throw ValidationException::withMessages(['report_type' => ['تقرير الفوترة المطلوب غير معروف.']]);
    }

    public function all(): array { return array_values($this->providers); }
}
