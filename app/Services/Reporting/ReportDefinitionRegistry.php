<?php

namespace App\Services\Reporting;

use InvalidArgumentException;

class ReportDefinitionRegistry
{
    /**
     * Definitions are versioned contracts shared by the report UI and the
     * server executor. Values/aggregates must be supplied by domain services.
     *
     * @return array<string, array<string, mixed>>
     */
    public function networkDefinitions(): array
    {
        return [
            'sales-invoices' => [
                'key' => 'sales-invoices',
                'scope' => 'NETWORK',
                'title' => 'فواتير المبيعات التشغيلية',
                'description' => 'تفاصيل فواتير البيع بين الشبكة ونقاط البيع ضمن الشبكة المحددة.',
                'permission' => 'sales.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 5000,
                'filters' => [
                    'from' => ['type' => 'date'],
                    'to' => ['type' => 'date'],
                    'payment_status' => ['type' => 'string', 'max' => 32],
                    'sale_type' => ['type' => 'string', 'max' => 32],
                ],
            ],
            'operating-expenses' => [
                'key' => 'operating-expenses',
                'scope' => 'NETWORK',
                'title' => 'المصروفات التشغيلية',
                'description' => 'سجل المصروفات وبنودها المسجلة على الشبكة.',
                'permission' => 'expenses.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 5000,
                'filters' => [
                    'from' => ['type' => 'date'],
                    'to' => ['type' => 'date'],
                ],
            ],
            'billing-account-statement' => [
                'key' => 'billing-account-statement',
                'scope' => 'NETWORK',
                'title' => 'كشف حساب اشتراك المنصة',
                'description' => 'كشف الذمم والدفعات المعتمدة والأرصدة الدائنة لكل عملة على حدة.',
                'permission' => 'NETWORK_OWNER_ONLY',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 100,
                'filters' => [
                    'billing_account_id' => ['type' => 'integer', 'required' => true],
                    'from' => ['type' => 'date'],
                    'to' => ['type' => 'date'],
                    'currency_id' => ['type' => 'integer'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function platformDefinitions(): array
    {
        return [
            'billing-overview' => [
                'key' => 'billing-overview',
                'scope' => 'PLATFORM',
                'title' => 'ملخص فوترة المنصة',
                'description' => 'مؤشرات الاشتراكات والفواتير والتحصيلات مفصولة حسب العملة.',
                'permission' => 'billing.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 500,
                'filters' => [
                    'currency_id' => ['type' => 'integer'],
                ],
            ],
            'billing-subscriptions' => [
                'key' => 'billing-subscriptions',
                'scope' => 'PLATFORM',
                'title' => 'سجل اشتراكات المنصة',
                'description' => 'الاشتراكات وحالاتها وخططها وفتراتها بحسب المرشحات المحددة.',
                'permission' => 'billing.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 5000,
                'filters' => [
                    'network_id' => ['type' => 'integer'],
                    'billing_account_id' => ['type' => 'integer'],
                    'currency_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'max' => 32],
                    'search' => ['type' => 'string', 'max' => 120],
                ],
            ],
            'billing-invoices' => [
                'key' => 'billing-invoices',
                'scope' => 'PLATFORM',
                'title' => 'فواتير اشتراكات المنصة',
                'description' => 'فواتير المنصة وبنودها وحالات التحصيل والذمم المتبقية.',
                'permission' => 'billing.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 5000,
                'filters' => [
                    'network_id' => ['type' => 'integer'],
                    'billing_account_id' => ['type' => 'integer'],
                    'currency_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'max' => 32],
                    'search' => ['type' => 'string', 'max' => 120],
                    'from' => ['type' => 'date'],
                    'to' => ['type' => 'date'],
                ],
            ],
            'billing-payments' => [
                'key' => 'billing-payments',
                'scope' => 'PLATFORM',
                'title' => 'تحصيلات اشتراكات المنصة',
                'description' => 'عمليات التحصيل ومراجعها ووسائل الدفع وحالات اعتمادها حسب العملة.',
                'permission' => 'billing.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 5000,
                'filters' => [
                    'network_id' => ['type' => 'integer'],
                    'billing_account_id' => ['type' => 'integer'],
                    'invoice_id' => ['type' => 'integer'],
                    'currency_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'max' => 32],
                    'from' => ['type' => 'date'],
                    'to' => ['type' => 'date'],
                ],
            ],
            'billing-credit-notes' => [
                'key' => 'billing-credit-notes',
                'scope' => 'PLATFORM',
                'title' => 'الإشعارات الدائنة لاشتراكات المنصة',
                'description' => 'الإشعارات الدائنة ومبالغها المطبقة على الذمم والمبالغ القابلة للاسترداد حسب العملة.',
                'permission' => 'billing.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 5000,
                'filters' => [
                    'network_id' => ['type' => 'integer'],
                    'billing_account_id' => ['type' => 'integer'],
                    'currency_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'max' => 32],
                ],
            ],
            'billing-refunds' => [
                'key' => 'billing-refunds',
                'scope' => 'PLATFORM',
                'title' => 'استردادات مدفوعات الاشتراك',
                'description' => 'عمليات رد الأموال المرتبطة بمدفوعات حسابات الاشتراك وحالاتها.',
                'permission' => 'billing.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 5000,
                'filters' => [
                    'network_id' => ['type' => 'integer'],
                    'billing_account_id' => ['type' => 'integer'],
                    'currency_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'max' => 32],
                ],
            ],
            'billing-journal-entries' => [
                'key' => 'billing-journal-entries',
                'scope' => 'PLATFORM',
                'title' => 'قيود الأستاذ العام للفوترة',
                'description' => 'قيود اليومية وخطوط الحسابات بحسب العملة والمصدر والفترة.',
                'permission' => 'billing.report',
                'definition_version' => '1.0.0',
                'artifact_format' => 'JSON',
                'max_records' => 5000,
                'filters' => [
                    'currency_id' => ['type' => 'integer'],
                    'source_type' => ['type' => 'string', 'max' => 64],
                    'from_date' => ['type' => 'date'],
                    'to_date' => ['type' => 'date'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $scope, string $key): array
    {
        $definitions = $scope === 'NETWORK'
            ? $this->networkDefinitions()
            : ($scope === 'PLATFORM' ? $this->platformDefinitions() : []);

        if (! isset($definitions[$key])) {
            throw new InvalidArgumentException('نوع التقرير غير معروف أو غير متاح ضمن هذا النطاق.');
        }

        return $definitions[$key];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(string $scope): array
    {
        $definitions = $scope === 'NETWORK'
            ? $this->networkDefinitions()
            : ($scope === 'PLATFORM' ? $this->platformDefinitions() : []);

        return array_values(array_map(static function (array $definition): array {
            return [
                'key' => $definition['key'],
                'scope' => $definition['scope'],
                'title' => $definition['title'],
                'description' => $definition['description'],
                'definition_version' => $definition['definition_version'],
                'artifact_format' => $definition['artifact_format'],
                'filters' => $definition['filters'],
                'max_records' => $definition['max_records'],
            ];
        }, $definitions));
    }
}
