<?php

namespace App\Enums;

/**
 * Supported operational report datasets exposed to Network and POS workspaces.
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

    public function reportPermission(): string
    {
        return match ($this) {
            self::SALES, self::DEBT_AGING => 'sales.report',
            self::EXPENSES => 'expenses.report',
            self::NETWORK_WALLET => 'network_wallets.report',
            self::POS_WALLET, self::REPAYMENTS => 'pos_wallets.report',
            self::REQUESTS => 'requests.report',
        };
    }

    public function exportPermission(): string
    {
        return match ($this) {
            self::SALES, self::DEBT_AGING => 'sales.export',
            self::EXPENSES => 'expenses.export',
            self::NETWORK_WALLET => 'network_wallets.export',
            self::POS_WALLET, self::REPAYMENTS => 'pos_wallets.export',
            self::REQUESTS => 'requests.export',
        };
    }
}
