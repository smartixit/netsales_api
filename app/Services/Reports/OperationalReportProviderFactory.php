<?php

namespace App\Services\Reports;

use App\Services\Reports\Providers\DebtAgingReportProvider;
use App\Services\Reports\Providers\ExpensesReportProvider;
use App\Services\Reports\Providers\NetworkWalletReportProvider;
use App\Services\Reports\Providers\PosWalletReportProvider;
use App\Services\Reports\Providers\RepaymentsReportProvider;
use App\Services\Reports\Providers\RequestsReportProvider;
use App\Services\Reports\Providers\SalesReportProvider;

final class OperationalReportProviderFactory
{
    public function __construct(
        private readonly SalesReportProvider $sales,
        private readonly ExpensesReportProvider $expenses,
        private readonly NetworkWalletReportProvider $networkWallet,
        private readonly PosWalletReportProvider $posWallet,
        private readonly RepaymentsReportProvider $repayments,
        private readonly DebtAgingReportProvider $debtAging,
        private readonly RequestsReportProvider $requests,
    ) {}

    public function make(): OperationalReportProviderRegistry
    {
        return new OperationalReportProviderRegistry([
            $this->sales,
            $this->expenses,
            $this->networkWallet,
            $this->posWallet,
            $this->repayments,
            $this->debtAging,
            $this->requests,
        ]);
    }
}
