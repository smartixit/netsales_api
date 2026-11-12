<?php

namespace App\Services\Reports;

use App\Enums\OperationalReportType;
use App\Services\OperationalReportQueryService;

final class OperationalReportProviderFactory
{
    public function __construct(private readonly OperationalReportQueryService $queries) {}

    public function make(): OperationalReportProviderRegistry
    {
        $commonColumns = [
            ['id' => 'record_id', 'label' => 'المعرف', 'data_type' => 'integer', 'visible_by_default' => true],
            ['id' => 'record_date', 'label' => 'التاريخ', 'data_type' => 'date', 'visible_by_default' => true],
            ['id' => 'record_reference', 'label' => 'المرجع', 'data_type' => 'text', 'visible_by_default' => true],
        ];

        $definitions = [
            OperationalReportType::SALES->value => [
                'label' => 'تقرير المبيعات والفواتير',
                'description' => 'فواتير البيع ومبالغها وحالة السداد ضمن الشبكة ونقطة البيع المحددة.',
                'permissions' => ['view' => 'sales.report', 'export' => 'sales.export'],
                'supported_scopes' => ['network', 'pos'],
                'pos_selection' => 'optional',
                'filters' => $this->filters(['PAID', 'PARTIALLY_PAID', 'UNPAID'], true),
                'columns' => [
                    ['id' => 'invoice_id', 'label' => 'رقم الفاتورة', 'data_type' => 'integer'],
                    ['id' => 'invoice_uuid', 'label' => 'مرجع الفاتورة', 'data_type' => 'text'],
                    ['id' => 'invoice_date', 'label' => 'تاريخ الفاتورة', 'data_type' => 'date'],
                    ['id' => 'sale_type', 'label' => 'نوع البيع', 'data_type' => 'text'],
                    ['id' => 'payment_status', 'label' => 'حالة السداد', 'data_type' => 'text'],
                    ['id' => 'invoice_total_amount', 'label' => 'الإجمالي', 'data_type' => 'decimal'],
                    ['id' => 'paid_amount', 'label' => 'المدفوع', 'data_type' => 'decimal'],
                    ['id' => 'remaining_amount', 'label' => 'المتبقي', 'data_type' => 'decimal'],
                ],
                'metric_slots' => $this->metrics(['record_count' => 'عدد الفواتير', 'total_amount' => 'إجمالي المبيعات', 'paid_amount' => 'المبالغ المسددة', 'remaining_amount' => 'المبالغ المتبقية']),
                'empty_export_columns' => ['invoice_id' => 'رقم الفاتورة', 'invoice_uuid' => 'مرجع الفاتورة', 'invoice_date' => 'تاريخ الفاتورة', 'sale_type' => 'نوع البيع', 'payment_status' => 'حالة السداد', 'invoice_total_amount' => 'الإجمالي', 'paid_amount' => 'المدفوع', 'remaining_amount' => 'المتبقي', 'network_id' => 'الشبكة', 'pos_id' => 'نقطة البيع'],
            ],
            OperationalReportType::EXPENSES->value => [
                'label' => 'تقرير المصروفات التشغيلية',
                'description' => 'المصروفات التشغيلية المسجلة على الشبكة مع بنودها ومرفقاتها.',
                'permissions' => ['view' => 'expenses.report', 'export' => 'expenses.export'],
                'supported_scopes' => ['network'],
                'pos_selection' => 'forbidden',
                'filters' => $this->filters([]),
                'columns' => [
                    ['id' => 'expense_id', 'label' => 'رقم المصروف', 'data_type' => 'integer'],
                    ['id' => 'expense_uuid', 'label' => 'مرجع المصروف', 'data_type' => 'text'],
                    ['id' => 'expense_date', 'label' => 'التاريخ', 'data_type' => 'date'],
                    ['id' => 'expense_total_amount', 'label' => 'الإجمالي', 'data_type' => 'decimal'],
                    ['id' => 'items', 'label' => 'البنود', 'data_type' => 'text'],
                ],
                'metric_slots' => $this->metrics(['record_count' => 'عدد المصروفات', 'total_amount' => 'إجمالي المصروفات']),
                'empty_export_columns' => ['expense_id' => 'رقم المصروف', 'expense_uuid' => 'مرجع المصروف', 'expense_date' => 'التاريخ', 'expense_total_amount' => 'الإجمالي', 'network_id' => 'الشبكة', 'created_by' => 'أنشئ بواسطة'],
            ],
            OperationalReportType::NETWORK_WALLET->value => [
                'label' => 'كشف حساب محفظة الشبكة',
                'description' => 'حركات الإيداع والسحب المسجلة على محفظة الشبكة.',
                'permissions' => ['view' => 'network_wallets.report', 'export' => 'network_wallets.export'],
                'supported_scopes' => ['network'],
                'pos_selection' => 'forbidden',
                'filters' => $this->filters(['DEPOSIT', 'WITHDRAW']),
                'columns' => [
                    ['id' => 'log_id', 'label' => 'رقم الحركة', 'data_type' => 'integer'],
                    ['id' => 'log_date', 'label' => 'التاريخ', 'data_type' => 'date'],
                    ['id' => 'log_operation', 'label' => 'نوع الحركة', 'data_type' => 'text'],
                    ['id' => 'log_amount', 'label' => 'المبلغ', 'data_type' => 'decimal'],
                    ['id' => 'log_statement', 'label' => 'البيان', 'data_type' => 'text'],
                ],
                'metric_slots' => $this->metrics(['record_count' => 'عدد الحركات', 'deposits' => 'إجمالي الإيداعات', 'withdrawals' => 'إجمالي السحوبات']),
                'empty_export_columns' => ['log_id' => 'رقم الحركة', 'log_date' => 'التاريخ', 'log_operation' => 'نوع الحركة', 'log_amount' => 'المبلغ', 'log_statement' => 'البيان', 'wallet_id' => 'المحفظة'],
            ],
            OperationalReportType::POS_WALLET->value => [
                'label' => 'كشف حساب محفظة نقطة البيع',
                'description' => 'حركات محفظة نقطة البيع المرتبطة بالشبكة المحددة.',
                'permissions' => ['view' => 'pos_wallets.report', 'export' => 'pos_wallets.export'],
                'supported_scopes' => ['pos'],
                'pos_selection' => 'required',
                'filters' => $this->filters(['DEPOSIT', 'WITHDRAW']),
                'columns' => [
                    ['id' => 'log_id', 'label' => 'رقم الحركة', 'data_type' => 'integer'],
                    ['id' => 'log_date', 'label' => 'التاريخ', 'data_type' => 'date'],
                    ['id' => 'log_operation_type', 'label' => 'نوع الحركة', 'data_type' => 'text'],
                    ['id' => 'log_amount', 'label' => 'المبلغ', 'data_type' => 'decimal'],
                    ['id' => 'log_statement', 'label' => 'البيان', 'data_type' => 'text'],
                ],
                'metric_slots' => $this->metrics(['record_count' => 'عدد الحركات', 'deposits' => 'إجمالي الإيداعات', 'withdrawals' => 'إجمالي السحوبات']),
                'empty_export_columns' => ['log_id' => 'رقم الحركة', 'log_date' => 'التاريخ', 'log_operation_type' => 'نوع الحركة', 'log_amount' => 'المبلغ', 'log_statement' => 'البيان', 'wallet_id' => 'المحفظة'],
            ],
            OperationalReportType::REPAYMENTS->value => [
                'label' => 'تقرير سندات السداد',
                'description' => 'سندات التحصيل والسداد مع حالات العكس ومراجعها.',
                'permissions' => ['view' => 'pos_wallets.report', 'export' => 'pos_wallets.export'],
                'supported_scopes' => ['network', 'pos'],
                'pos_selection' => 'optional',
                'filters' => $this->filters(['COMPLETED', 'REVERSED']),
                'columns' => [
                    ['id' => 'repayment_id', 'label' => 'رقم السند', 'data_type' => 'integer'],
                    ['id' => 'repayment_uuid', 'label' => 'مرجع السند', 'data_type' => 'text'],
                    ['id' => 'repayment_date', 'label' => 'التاريخ', 'data_type' => 'date'],
                    ['id' => 'amount', 'label' => 'المبلغ', 'data_type' => 'decimal'],
                    ['id' => 'status', 'label' => 'الحالة', 'data_type' => 'text'],
                    ['id' => 'reference_number', 'label' => 'المرجع الخارجي', 'data_type' => 'text'],
                    ['id' => 'notes', 'label' => 'البيان', 'data_type' => 'text'],
                ],
                'metric_slots' => $this->metrics(['record_count' => 'عدد السندات', 'total_amount' => 'إجمالي السداد المكتمل', 'completed_count' => 'سندات مكتملة', 'reversed_count' => 'سندات معكوسة', 'reversed_amount' => 'قيمة السندات المعكوسة']),
                'empty_export_columns' => ['repayment_id' => 'رقم السند', 'repayment_uuid' => 'مرجع السند', 'repayment_date' => 'التاريخ', 'amount' => 'المبلغ', 'status' => 'الحالة', 'reference_number' => 'المرجع', 'notes' => 'البيان'],
            ],
            OperationalReportType::DEBT_AGING->value => [
                'label' => 'تقرير أعمار الديون',
                'description' => 'الفواتير غير المسددة وتصنيف أعمار المبالغ المتبقية.',
                'permissions' => ['view' => 'sales.report', 'export' => 'sales.export'],
                'supported_scopes' => ['network', 'pos'],
                'pos_selection' => 'optional',
                'filters' => $this->filters(['PARTIALLY_PAID', 'UNPAID']),
                'columns' => [
                    ['id' => 'invoice_id', 'label' => 'رقم الفاتورة', 'data_type' => 'integer'],
                    ['id' => 'invoice_uuid', 'label' => 'مرجع الفاتورة', 'data_type' => 'text'],
                    ['id' => 'invoice_date', 'label' => 'التاريخ', 'data_type' => 'date'],
                    ['id' => 'invoice_total_amount', 'label' => 'الإجمالي', 'data_type' => 'decimal'],
                    ['id' => 'paid_amount', 'label' => 'المدفوع', 'data_type' => 'decimal'],
                    ['id' => 'remaining_amount', 'label' => 'المتبقي', 'data_type' => 'decimal'],
                    ['id' => 'age_days', 'label' => 'عمر الدين بالأيام', 'data_type' => 'integer'],
                    ['id' => 'aging_bucket', 'label' => 'شريحة العمر', 'data_type' => 'text'],
                ],
                'metric_slots' => $this->metrics(['record_count' => 'عدد الفواتير', 'total_outstanding' => 'إجمالي المديونية', 'bucket_0_30' => '0–30 يوماً', 'bucket_31_60' => '31–60 يوماً', 'bucket_61_90' => '61–90 يوماً', 'bucket_over_90' => 'أكثر من 90 يوماً']),
                'empty_export_columns' => ['invoice_id' => 'رقم الفاتورة', 'invoice_uuid' => 'مرجع الفاتورة', 'invoice_date' => 'التاريخ', 'invoice_total_amount' => 'الإجمالي', 'paid_amount' => 'المدفوع', 'remaining_amount' => 'المتبقي', 'payment_status' => 'الحالة', 'network_id' => 'الشبكة', 'pos_id' => 'نقطة البيع'],
            ],
            OperationalReportType::REQUESTS->value => [
                'label' => 'تقرير حركة الطلبات',
                'description' => 'طلبات التوريد وحالات مراجعتها ونوع البيع.',
                'permissions' => ['view' => 'requests.report', 'export' => 'requests.export'],
                'supported_scopes' => ['network', 'pos'],
                'pos_selection' => 'optional',
                'filters' => $this->filters(['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'], true),
                'columns' => [
                    ['id' => 'request_id', 'label' => 'رقم الطلب', 'data_type' => 'integer'],
                    ['id' => 'request_uuid', 'label' => 'مرجع الطلب', 'data_type' => 'text'],
                    ['id' => 'request_date', 'label' => 'التاريخ', 'data_type' => 'date'],
                    ['id' => 'sale_type', 'label' => 'نوع البيع', 'data_type' => 'text'],
                    ['id' => 'status', 'label' => 'الحالة', 'data_type' => 'text'],
                    ['id' => 'request_total_amount', 'label' => 'الإجمالي', 'data_type' => 'decimal'],
                    ['id' => 'request_note', 'label' => 'البيان', 'data_type' => 'text'],
                ],
                'metric_slots' => $this->metrics(['record_count' => 'عدد الطلبات', 'total_amount' => 'إجمالي قيمة الطلبات', 'pending_count' => 'قيد الانتظار', 'approved_count' => 'معتمدة', 'rejected_count' => 'مرفوضة', 'cancelled_count' => 'ملغاة']),
                'empty_export_columns' => ['request_id' => 'رقم الطلب', 'request_uuid' => 'مرجع الطلب', 'request_date' => 'التاريخ', 'sale_type' => 'نوع البيع', 'status' => 'الحالة', 'request_total_amount' => 'الإجمالي', 'request_note' => 'البيان', 'network_id' => 'الشبكة', 'pos_id' => 'نقطة البيع'],
            ],
        ];

        $providers = [];
        foreach (OperationalReportType::cases() as $type) {
            $definition = $definitions[$type->value];
            $definition += [
                'id' => $type->value,
                'schema_version' => 1,
                'title_key' => 'operational_reports.'.$type->value.'.title',
                'templates' => [['id' => 'netsales.operational-report.executive', 'version' => '1.0.0']],
                'execution_policy' => 'server',
                'client_row_limit' => null,
                'charts_available' => false,
                'export_formats' => ['CSV'],
            ];
            $providers[] = new OperationalReportProviderAdapter($type, $definition, $this->queries);
        }

        return new OperationalReportProviderRegistry($providers);
    }

    /**
     * @param  list<string>  $statuses
     * @return list<array<string, mixed>>
     */
    private function filters(array $statuses, bool $withSaleType = false): array
    {
        $filters = [
            ['id' => 'from_date', 'label' => 'من تاريخ', 'kind' => 'date', 'required' => false],
            ['id' => 'to_date', 'label' => 'إلى تاريخ', 'kind' => 'date', 'required' => false],
            ['id' => 'search', 'label' => 'بحث', 'kind' => 'text', 'required' => false, 'max_length' => 120],
        ];
        if ($statuses !== []) {
            $filters[] = ['id' => 'status', 'label' => 'الحالة', 'kind' => 'single_choice', 'required' => false, 'choices' => $statuses];
        }
        if ($withSaleType) {
            $filters[] = ['id' => 'sale_type', 'label' => 'نوع البيع', 'kind' => 'single_choice', 'required' => false, 'choices' => ['CASH', 'CREDIT']];
        }

        return $filters;
    }

    /**
     * @param  array<string, string>  $metrics
     * @return list<array<string, string>>
     */
    private function metrics(array $metrics): array
    {
        $result = [];
        foreach ($metrics as $id => $label) {
            $result[] = ['id' => $id, 'label' => $label, 'data_type' => str_contains($id, 'count') ? 'integer' : 'decimal'];
        }

        return $result;
    }
}
