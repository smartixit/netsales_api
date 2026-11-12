<?php
namespace App\Services\Reports;
use App\Services\Reports\Providers\SalesReportProvider;
use App\Services\Reports\Providers\ExpensesReportProvider;
use App\Services\Reports\Providers\NetworkWalletReportProvider;
use App\Services\Reports\Providers\PosWalletReportProvider;
use App\Services\Reports\Providers\RepaymentsReportProvider;
use App\Services\Reports\Providers\DebtAgingReportProvider;
use App\Services\Reports\Providers\RequestsReportProvider;
final class OperationalReportProviderFactory
{
 public function make(): OperationalReportProviderRegistry
 {
  return new OperationalReportProviderRegistry([
   app(SalesReportProvider::class),
   app(ExpensesReportProvider::class),
   app(NetworkWalletReportProvider::class),
   app(PosWalletReportProvider::class),
   app(RepaymentsReportProvider::class),
   app(DebtAgingReportProvider::class),
   app(RequestsReportProvider::class),
  ]);
 }
}
