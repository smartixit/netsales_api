<?php

namespace App\Providers;

use App\Interfaces\BankAccountRepositoryInterface;
use App\Interfaces\ExpenseAttachmentRepositoryInterface;
use App\Interfaces\ExpenseItemRepositoryInterface;
use App\Interfaces\ExpenseRepositoryInterface;
use App\Interfaces\FeatureRepositoryInterface;
use App\Interfaces\NetworkPosRepositoryInterface;
use App\Interfaces\NetworkRepositoryInterface;
use App\Interfaces\NetworkWalletLogRepositoryInterface;
use App\Interfaces\NetworkWalletRepositoryInterface;
use App\Interfaces\NotificationRepositoryInterface;
use App\Interfaces\OtpRepositoryInterface;
use App\Interfaces\PosRepositoryInterface;
use App\Interfaces\PosWalletLogRepositoryInterface;
use App\Interfaces\PosWalletRepositoryInterface;
use App\Interfaces\ProviderSubscriptionRepositoryInterface;
use App\Interfaces\RequestItemRepositoryInterface;
use App\Interfaces\RequestRepositoryInterface;
use App\Interfaces\SaleInvoiceItemRepositoryInterface;
use App\Interfaces\SaleInvoiceRepositoryInterface;
use App\Interfaces\SecurityBanRepositoryInterface;
use App\Interfaces\SubscriptionPlanFeatureConsumptionRepositoryInterface;
use App\Interfaces\SubscriptionPlanFeatureRepositoryInterface;
use App\Interfaces\SubscriptionPlanRepositoryInterface;
use App\Interfaces\SubscriptionPlanRequestRepositoryInterface;
use App\Interfaces\SupportTicketMessageRepositoryInterface;
use App\Interfaces\SupportTicketRepositoryInterface;
use App\Interfaces\SystemLogRepositoryInterface;
use App\Interfaces\SystemSettingRepositoryInterface;
use App\Interfaces\UserDeviceRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserSessionRepositoryInterface;
use App\Interfaces\VoucherPackageDiscountRepositoryInterface;
use App\Interfaces\VoucherPackageRepositoryInterface;
use App\Repositories\BankAccountRepository;
use App\Repositories\ExpenseAttachmentRepository;
use App\Repositories\ExpenseItemRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\FeatureRepository;
use App\Repositories\NetworkPosRepository;
use App\Repositories\NetworkRepository;
use App\Repositories\NetworkWalletLogRepository;
use App\Repositories\NetworkWalletRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OtpRepository;
use App\Repositories\PosRepository;
use App\Repositories\PosWalletLogRepository;
use App\Repositories\PosWalletRepository;
use App\Repositories\ProviderSubscriptionRepository;
use App\Repositories\RequestItemRepository;
use App\Repositories\RequestRepository;
use App\Repositories\SaleInvoiceItemRepository;
use App\Repositories\SaleInvoiceRepository;
use App\Repositories\SecurityBanRepository;
use App\Repositories\SubscriptionPlanFeatureConsumptionRepository;
use App\Repositories\SubscriptionPlanFeatureRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\SubscriptionPlanRequestRepository;
use App\Repositories\SupportTicketMessageRepository;
use App\Repositories\SupportTicketRepository;
use App\Repositories\SystemLogRepository;
use App\Repositories\SystemSettingRepository;
use App\Repositories\UserDeviceRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Repositories\VoucherPackageDiscountRepository;
use App\Repositories\VoucherPackageRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<string, string>
     */
    public array $bindings = [
        // Phase 1: Auth & Security Core
        UserRepositoryInterface::class => UserRepository::class,
        UserDeviceRepositoryInterface::class => UserDeviceRepository::class,
        UserSessionRepositoryInterface::class => UserSessionRepository::class,
        SecurityBanRepositoryInterface::class => SecurityBanRepository::class,
        OtpRepositoryInterface::class => OtpRepository::class,
        NetworkRepositoryInterface::class => NetworkRepository::class,
        PosRepositoryInterface::class => PosRepository::class,
        NetworkPosRepositoryInterface::class => NetworkPosRepository::class,

        // Phase 2: Packages & Discounts
        VoucherPackageRepositoryInterface::class => VoucherPackageRepository::class,
        VoucherPackageDiscountRepositoryInterface::class => VoucherPackageDiscountRepository::class,

        // Phase 3: Digital Wallets & Financial Ledger System
        NetworkWalletRepositoryInterface::class => NetworkWalletRepository::class,
        NetworkWalletLogRepositoryInterface::class => NetworkWalletLogRepository::class,
        PosWalletRepositoryInterface::class => PosWalletRepository::class,
        PosWalletLogRepositoryInterface::class => PosWalletLogRepository::class,

        // Phase 4: Voucher Requests & Sales Invoicing Engine
        RequestRepositoryInterface::class => RequestRepository::class,
        RequestItemRepositoryInterface::class => RequestItemRepository::class,
        SaleInvoiceRepositoryInterface::class => SaleInvoiceRepository::class,
        SaleInvoiceItemRepositoryInterface::class => SaleInvoiceItemRepository::class,

        // Phase 5: Expenses & Operational Disbursals
        ExpenseRepositoryInterface::class => ExpenseRepository::class,
        ExpenseItemRepositoryInterface::class => ExpenseItemRepository::class,
        ExpenseAttachmentRepositoryInterface::class => ExpenseAttachmentRepository::class,

        // Phase 6: Subscription Plans, Features, Bank Accounts & Provider Subscriptions
        SubscriptionPlanRepositoryInterface::class => SubscriptionPlanRepository::class,
        FeatureRepositoryInterface::class => FeatureRepository::class,
        SubscriptionPlanFeatureRepositoryInterface::class => SubscriptionPlanFeatureRepository::class,
        BankAccountRepositoryInterface::class => BankAccountRepository::class,
        SubscriptionPlanRequestRepositoryInterface::class => SubscriptionPlanRequestRepository::class,
        ProviderSubscriptionRepositoryInterface::class => ProviderSubscriptionRepository::class,
        SubscriptionPlanFeatureConsumptionRepositoryInterface::class => SubscriptionPlanFeatureConsumptionRepository::class,

        // Phase 7: Support Tickets & Interactive Messaging Engine
        SupportTicketRepositoryInterface::class => SupportTicketRepository::class,
        SupportTicketMessageRepositoryInterface::class => SupportTicketMessageRepository::class,

        // Phase 8: Notifications Engine
        NotificationRepositoryInterface::class => NotificationRepository::class,

        // Phase 9: System Settings & Configurations Engine
        SystemSettingRepositoryInterface::class => SystemSettingRepository::class,

        // Phase 10: System Audit Logs Engine
        SystemLogRepositoryInterface::class => SystemLogRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }
    }

    public function boot(): void {}
}
