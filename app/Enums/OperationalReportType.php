<?php

namespace App\Enums;

/**
 * Stable identifiers for operational report providers.
 * Report metadata and permissions are owned by the registered provider.
 */
enum OperationalReportType: string
{
    case SALES = 'sales';
    case EXPENSES = 'expenses';
    case NETWORK_WALLET = 'network_wallet_statement';
    case POS_WALLET = 'pos_wallet_statement';
    case REPAYMENTS = 'repayments';
    case DEBT_AGING = 'debt_aging';
    case REQUESTS = 'requests';

    public function label(): string
    {
        return match ($this) {
            self::SALES => 'تقرير المبيعات والفواتير',
            self::EXPENSES => 'تقرير المصروفات التشغيلية',
            self::NETWORK_WALLET => 'كشف حساب محفظة الشبكة',
            self::POS_WALLET => 'كشف حساب محفظة نقطة البيع',
            self::REPAYMENTS => 'تقرير سندات السداد',
            self::DEBT_AGING => 'تقرير أعمار الديون',
            self::REQUESTS => 'تقرير حركة الطلبات',
        };
    }
}
