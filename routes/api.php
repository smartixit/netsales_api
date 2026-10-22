<?php

use App\Http\Controllers\API\V1\AdminNotificationController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\BackupController;
use App\Http\Controllers\API\V1\BankAccountController;
use App\Http\Controllers\API\V1\DashboardController;
use App\Http\Controllers\API\V1\DirectorateController;
use App\Http\Controllers\API\V1\ExpenseController;
use App\Http\Controllers\API\V1\FeatureController;
use App\Http\Controllers\API\V1\FileController;
use App\Http\Controllers\API\V1\GovernorateController;
use App\Http\Controllers\API\V1\NetworkController;
use App\Http\Controllers\API\V1\NetworkPosController;
use App\Http\Controllers\API\V1\NetworkSmsSettingController;
use App\Http\Controllers\API\V1\NetworkWalletController;
use App\Http\Controllers\API\V1\NotificationController;
use App\Http\Controllers\API\V1\OtpController;
use App\Http\Controllers\API\V1\PosController;
use App\Http\Controllers\API\V1\PosWalletController;
use App\Http\Controllers\API\V1\ProviderSubscriptionController;
use App\Http\Controllers\API\V1\RequestController;
use App\Http\Controllers\API\V1\SaleInvoiceController;
use App\Http\Controllers\API\V1\ScheduledTaskRunController;
use App\Http\Controllers\API\V1\SecurityController;
use App\Http\Controllers\API\V1\SubscriptionPlanController;
use App\Http\Controllers\API\V1\SubscriptionPlanRequestController;
use App\Http\Controllers\API\V1\SupportTicketController;
use App\Http\Controllers\API\V1\SystemLogController;
use App\Http\Controllers\API\V1\SystemSettingController;
use App\Http\Controllers\API\V1\UserController;
use App\Http\Controllers\API\V1\VoucherPackageController;
use App\Http\Controllers\API\V1\VoucherPackageDiscountController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NETSALES API Routes — Version 1
|--------------------------------------------------------------------------
|
| مسارات واجهة التطبيق البرمجية لنظام نت سيلز (NetSales API V1).
| مبوبة ومحمية بنظام الجلسات (session.auth) ومحددات الطلبات (Throttles).
|
*/

Route::prefix('v1')->group(function () {

    // ==========================================
    // المصادقة والجلسات (Authentication & Profile)
    // ==========================================
    Route::prefix('auth')->group(function () {

        Route::get('health-check', [AuthController::class, 'healthCheck'])->name('api.v1.auth.health_check');

        Route::middleware('throttle:auth')->group(function () {
            Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');
            Route::post('register-network', [AuthController::class, 'registerNetwork'])->name('api.v1.auth.register_network');
            Route::post('register-pos', [AuthController::class, 'registerPos'])->name('api.v1.auth.register_pos');
        });

        Route::middleware('throttle:auth-otp')->group(function () {
            Route::post('password/request-reset', [AuthController::class, 'requestPasswordReset'])->name('api.v1.auth.password.request_reset');
            Route::post('password/verify-otp', [AuthController::class, 'verifyResetOtp'])->name('api.v1.auth.password.verify_otp');
            Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('api.v1.auth.password.reset');
            Route::post('2fa/verify', [AuthController::class, 'verifyTwoFactor'])->name('api.v1.auth.2fa.verify');
        });

        Route::middleware('session.auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::post('logout-other-devices', [AuthController::class, 'logoutOtherDevices'])->name('api.v1.auth.logout_other_devices');
            Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
            Route::post('update-password', [AuthController::class, 'updatePassword'])->name('api.v1.auth.update_password');
            Route::post('toggle-2fa', [AuthController::class, 'toggleTwoFactor'])->name('api.v1.auth.toggle_2fa');
            Route::get('devices', [AuthController::class, 'getDevices'])->name('api.v1.auth.devices');
            Route::delete('devices/{id}', [AuthController::class, 'disconnectDevice'])->whereNumber('id')->name('api.v1.auth.devices.disconnect');
        });

    });

    // ==========================================
    // خدمات التحقق بالرمز المؤقت (OTP Verification)
    // ==========================================
    Route::prefix('otp')->middleware('throttle:auth-otp')->group(function () {
        Route::post('generate', [OtpController::class, 'generate'])->name('api.v1.otp.generate');
        Route::post('verify', [OtpController::class, 'verify'])->name('api.v1.otp.verify');
        Route::post('resend', [OtpController::class, 'resend'])->name('api.v1.otp.resend');
    });

    // ==========================================
    // البيانات المرجعية وتدفق الملفات (Public Files & Reference)
    // ==========================================
    Route::middleware('throttle:api-public')->group(function () {
        Route::get('security/public-keys', [SecurityController::class, 'getPublicKeys'])->name('api.v1.security.public_keys');

        // Protected Files Stream (Public Whitelisted & Private Signed)
        Route::get('files/signed/{path}', [FileController::class, 'showSigned'])->where('path', '.*')->name('api.v1.files.signed');
        Route::get('files/{path}', [FileController::class, 'show'])->where('path', '.*')->name('api.v1.files.show');

        // المحرك الجغرافي المرجعي لعمليات التسجيل العامة (Public Geography Reference)
        Route::get('governorates', [GovernorateController::class, 'index'])->name('api.v1.governorates.index');
        Route::get('governorates/{id}', [GovernorateController::class, 'show'])->whereNumber('id')->name('api.v1.governorates.show');
        Route::get('directorates', [DirectorateController::class, 'index'])->name('api.v1.directorates.index');
        Route::get('directorates/governorate/{governorateId}', [DirectorateController::class, 'byGovernorate'])->whereNumber('governorateId')->name('api.v1.directorates.by_governorate');
        Route::get('directorates/{id}', [DirectorateController::class, 'show'])->whereNumber('id')->name('api.v1.directorates.show');

        // إعدادات النظام العامة المصرح بنشرها للواجهات والتطبيقات دون مصادقة (Public System Settings)
        Route::get('system-settings/public', [SystemSettingController::class, 'publicSettings'])->name('api.v1.system_settings.public');
    });

    // ==========================================
    // المسارات المحمية بالجلسة (Protected Routes - Session Auth)
    // ==========================================
    Route::middleware('session.auth')->group(function () {

        // ==========================================
        // 0. لوحة القيادة والمؤشرات التجميعية (Executive Dashboard & Aggregations)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('dashboard/overview', [DashboardController::class, 'overview'])->name('api.v1.dashboard.overview');
        });

        // ==========================================
        // 1. إدارة المستخدمين (Users Management)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('api.v1.users.index');
            Route::get('users/trashed', [UserController::class, 'trashed'])->name('api.v1.users.trashed');
            Route::get('users/{id}', [UserController::class, 'show'])->whereNumber('id')->name('api.v1.users.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('users/{id}/permissions', [UserController::class, 'syncPermissions'])->name('api.v1.users.sync_permissions');
            Route::post('users/batch-delete', [UserController::class, 'batchDelete'])->name('api.v1.users.batch_delete');
            Route::post('users/batch-restore', [UserController::class, 'batchRestore'])->name('api.v1.users.batch_restore');
            Route::post('users/batch-force-delete', [UserController::class, 'batchForceDelete'])->name('api.v1.users.batch_force_delete');
            Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('api.v1.users.restore');
            Route::delete('users/{id}/force', [UserController::class, 'forceDelete'])->name('api.v1.users.force_delete');
            Route::apiResource('users', UserController::class)->parameters(['users' => 'id'])->except(['index', 'show']);
        });

        Route::post('users/{id}/profile-picture', [UserController::class, 'updateProfilePicture'])
            ->middleware('throttle:api-upload')
            ->name('api.v1.users.profile_picture');

        // ==========================================
        // 2. إدارة الأمان والجلسات والحظر (Security & Sessions)
        // ==========================================
        Route::prefix('security')->group(function () {
            Route::middleware('throttle:api-read')->group(function () {
                Route::get('users/{id}/sessions', [SecurityController::class, 'getUserSessions'])->name('api.v1.security.user_sessions');
                Route::get('users/{id}/devices', [SecurityController::class, 'getUserDevices'])->name('api.v1.security.user_devices');
            });

            Route::middleware('throttle:api-write')->group(function () {
                Route::post('ban-user', [SecurityController::class, 'banUser'])->name('api.v1.security.ban_user');
                Route::post('ban-device', [SecurityController::class, 'banDevice'])->name('api.v1.security.ban_device');
                Route::delete('bans/{id}', [SecurityController::class, 'unban'])->name('api.v1.security.unban');
                Route::post('sessions/{id}/terminate', [SecurityController::class, 'terminateSession'])->name('api.v1.security.terminate_session');
            });
        });

        // Top-level Security aliases for backward compatibility
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('users/{id}/sessions', [SecurityController::class, 'getUserSessions']);
            Route::get('users/{id}/devices', [SecurityController::class, 'getUserDevices']);
        });
        Route::middleware('throttle:api-write')->group(function () {
            Route::post('ban-user', [SecurityController::class, 'banUser']);
            Route::post('ban-device', [SecurityController::class, 'banDevice']);
            Route::delete('bans/{id}', [SecurityController::class, 'unban']);
            Route::post('sessions/{id}/terminate', [SecurityController::class, 'terminateSession']);
        });

        // ==========================================
        // 3. إدارة مزودي الشبكات (Networks Management)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('networks', [NetworkController::class, 'index'])->name('api.v1.networks.index');
            Route::get('networks/trashed', [NetworkController::class, 'trashed'])->name('api.v1.networks.trashed');
            Route::get('networks/{id}', [NetworkController::class, 'show'])->whereNumber('id')->name('api.v1.networks.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('networks/batch-delete', [NetworkController::class, 'batchDelete'])->name('api.v1.networks.batch_delete');
            Route::post('networks/batch-restore', [NetworkController::class, 'batchRestore'])->name('api.v1.networks.batch_restore');
            Route::post('networks/batch-force-delete', [NetworkController::class, 'batchForceDelete'])->name('api.v1.networks.batch_force_delete');
            Route::post('networks/{id}/restore', [NetworkController::class, 'restore'])->name('api.v1.networks.restore');
            Route::delete('networks/{id}/force', [NetworkController::class, 'forceDelete'])->name('api.v1.networks.force_delete');
            Route::post('networks/{id}/approve', [NetworkController::class, 'approve'])->whereNumber('id')->name('api.v1.networks.approve');
            Route::post('networks/{id}/reject', [NetworkController::class, 'reject'])->whereNumber('id')->name('api.v1.networks.reject');
            Route::apiResource('networks', NetworkController::class)->parameters(['networks' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 4. إدارة نقاط البيع والوكلاء (POS Management)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('pos', [PosController::class, 'index'])->name('api.v1.pos.index');
            Route::get('pos/trashed', [PosController::class, 'trashed'])->name('api.v1.pos.trashed');
            Route::get('pos/{id}', [PosController::class, 'show'])->whereNumber('id')->name('api.v1.pos.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('pos/batch-delete', [PosController::class, 'batchDelete'])->name('api.v1.pos.batch_delete');
            Route::post('pos/batch-restore', [PosController::class, 'batchRestore'])->name('api.v1.pos.batch_restore');
            Route::post('pos/batch-force-delete', [PosController::class, 'batchForceDelete'])->name('api.v1.pos.batch_force_delete');
            Route::post('pos/{id}/restore', [PosController::class, 'restore'])->name('api.v1.pos.restore');
            Route::delete('pos/{id}/force', [PosController::class, 'forceDelete'])->name('api.v1.pos.force_delete');
            Route::post('pos/{id}/approve', [PosController::class, 'approve'])->whereNumber('id')->name('api.v1.pos.approve');
            Route::post('pos/{id}/reject', [PosController::class, 'reject'])->whereNumber('id')->name('api.v1.pos.reject');
            Route::apiResource('pos', PosController::class)->parameters(['pos' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 5. ربط نقاط البيع بمزودي الشبكات (Network POS Links)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('network-pos', [NetworkPosController::class, 'index'])->name('api.v1.network_pos.index');
            Route::get('network-pos/{id}', [NetworkPosController::class, 'show'])->whereNumber('id')->name('api.v1.network_pos.show');
            Route::get('networks/{id}/pos', [NetworkPosController::class, 'networkPosList'])->whereNumber('id')->name('api.v1.networks.pos_list');
            Route::get('pos/{id}/networks', [NetworkPosController::class, 'posNetworksList'])->whereNumber('id')->name('api.v1.pos.networks_list');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('network-pos', [NetworkPosController::class, 'store'])->name('api.v1.network_pos.store');
            Route::put('network-pos/{id}', [NetworkPosController::class, 'update'])->whereNumber('id')->name('api.v1.network_pos.update');
            Route::delete('network-pos/{id}', [NetworkPosController::class, 'destroy'])->whereNumber('id')->name('api.v1.network_pos.destroy');
            Route::post('network-pos/{id}/toggle-status', [NetworkPosController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.network_pos.toggle_status');
            Route::post('network-pos/batch-delete', [NetworkPosController::class, 'batchDelete'])->name('api.v1.network_pos.batch_delete');
            Route::post('network-pos/batch-status', [NetworkPosController::class, 'batchStatus'])->name('api.v1.network_pos.batch_status');
        });

        // ==========================================
        // 6. باقات كروت الإنترنت (Voucher Packages)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('voucher-packages', [VoucherPackageController::class, 'index'])->name('api.v1.voucher_packages.index');
            Route::get('voucher-packages/trashed', [VoucherPackageController::class, 'trashed'])->name('api.v1.voucher_packages.trashed');
            Route::get('voucher-packages/{id}', [VoucherPackageController::class, 'show'])->whereNumber('id')->name('api.v1.voucher_packages.show');
            Route::get('networks/{id}/voucher-packages', [VoucherPackageController::class, 'networkPackages'])->whereNumber('id')->name('api.v1.networks.voucher_packages');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('voucher-packages/batch-delete', [VoucherPackageController::class, 'batchDelete'])->name('api.v1.voucher_packages.batch_delete');
            Route::post('voucher-packages/batch-restore', [VoucherPackageController::class, 'batchRestore'])->name('api.v1.voucher_packages.batch_restore');
            Route::post('voucher-packages/batch-force-delete', [VoucherPackageController::class, 'batchForceDelete'])->name('api.v1.voucher_packages.batch_force_delete');
            Route::post('voucher-packages/{id}/restore', [VoucherPackageController::class, 'restore'])->whereNumber('id')->name('api.v1.voucher_packages.restore');
            Route::delete('voucher-packages/{id}/force', [VoucherPackageController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.voucher_packages.force_delete');
            Route::post('voucher-packages/{id}/toggle-status', [VoucherPackageController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.voucher_packages.toggle_status');
            Route::apiResource('voucher-packages', VoucherPackageController::class)->parameters(['voucher-packages' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 7. خصومات وعمولات باقات الكروت المخصصة (Voucher Package Discounts)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('voucher-package-discounts', [VoucherPackageDiscountController::class, 'index'])->name('api.v1.voucher_package_discounts.index');
            Route::get('voucher-package-discounts/trashed', [VoucherPackageDiscountController::class, 'trashed'])->name('api.v1.voucher_package_discounts.trashed');
            Route::get('voucher-package-discounts/effective', [VoucherPackageDiscountController::class, 'effectiveDiscount'])->name('api.v1.voucher_package_discounts.effective');
            Route::get('voucher-package-discounts/{id}', [VoucherPackageDiscountController::class, 'show'])->whereNumber('id')->name('api.v1.voucher_package_discounts.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('voucher-package-discounts/batch-delete', [VoucherPackageDiscountController::class, 'batchDelete'])->name('api.v1.voucher_package_discounts.batch_delete');
            Route::post('voucher-package-discounts/batch-restore', [VoucherPackageDiscountController::class, 'batchRestore'])->name('api.v1.voucher_package_discounts.batch_restore');
            Route::post('voucher-package-discounts/batch-force-delete', [VoucherPackageDiscountController::class, 'batchForceDelete'])->name('api.v1.voucher_package_discounts.batch_force_delete');
            Route::post('voucher-package-discounts/{id}/restore', [VoucherPackageDiscountController::class, 'restore'])->whereNumber('id')->name('api.v1.voucher_package_discounts.restore');
            Route::delete('voucher-package-discounts/{id}/force', [VoucherPackageDiscountController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.voucher_package_discounts.force_delete');
            Route::post('voucher-package-discounts/{id}/toggle-status', [VoucherPackageDiscountController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.voucher_package_discounts.toggle_status');
            Route::apiResource('voucher-package-discounts', VoucherPackageDiscountController::class)->parameters(['voucher-package-discounts' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 8. محافظ مزودي الشبكات ودفتر الأستاذ (Network Wallets & Ledger)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('network-wallets', [NetworkWalletController::class, 'index'])->name('api.v1.network_wallets.index');
            Route::get('network-wallets/trashed', [NetworkWalletController::class, 'trashed'])->name('api.v1.network_wallets.trashed');
            Route::get('network-wallets/{id}', [NetworkWalletController::class, 'show'])->whereNumber('id')->name('api.v1.network_wallets.show');
            Route::get('network-wallets/{id}/logs', [NetworkWalletController::class, 'logs'])->whereNumber('id')->name('api.v1.network_wallets.logs');
            Route::get('network-wallets/network/{networkId}', [NetworkWalletController::class, 'showByNetwork'])->whereNumber('networkId')->name('api.v1.network_wallets.show_by_network');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('network-wallets', [NetworkWalletController::class, 'store'])->name('api.v1.network_wallets.store');
            Route::put('network-wallets/{id}', [NetworkWalletController::class, 'update'])->whereNumber('id')->name('api.v1.network_wallets.update');
            Route::delete('network-wallets/{id}', [NetworkWalletController::class, 'destroy'])->whereNumber('id')->name('api.v1.network_wallets.destroy');
            Route::post('network-wallets/{id}/deposit', [NetworkWalletController::class, 'deposit'])->whereNumber('id')->name('api.v1.network_wallets.deposit');
            Route::post('network-wallets/{id}/withdraw', [NetworkWalletController::class, 'withdraw'])->whereNumber('id')->name('api.v1.network_wallets.withdraw');
            Route::post('network-wallets/{id}/restore', [NetworkWalletController::class, 'restore'])->whereNumber('id')->name('api.v1.network_wallets.restore');
            Route::delete('network-wallets/{id}/force', [NetworkWalletController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.network_wallets.force_delete');
            Route::post('network-wallets/batch-delete', [NetworkWalletController::class, 'batchDelete'])->name('api.v1.network_wallets.batch_delete');
            Route::post('network-wallets/batch-restore', [NetworkWalletController::class, 'batchRestore'])->name('api.v1.network_wallets.batch_restore');
            Route::post('network-wallets/batch-force-delete', [NetworkWalletController::class, 'batchForceDelete'])->name('api.v1.network_wallets.batch_force_delete');
        });

        // ==========================================
        // 9. محافظ نقاط البيع والتحويلات المالية (POS Wallets & Transfers)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('pos-wallets', [PosWalletController::class, 'index'])->name('api.v1.pos_wallets.index');
            Route::get('pos-wallets/trashed', [PosWalletController::class, 'trashed'])->name('api.v1.pos_wallets.trashed');
            Route::get('pos-wallets/{id}', [PosWalletController::class, 'show'])->whereNumber('id')->name('api.v1.pos_wallets.show');
            Route::get('pos-wallets/{id}/logs', [PosWalletController::class, 'logs'])->whereNumber('id')->name('api.v1.pos_wallets.logs');
            Route::get('pos-wallets/network/{networkId}/pos/{posId}', [PosWalletController::class, 'showByNetworkAndPos'])->whereNumber('networkId')->whereNumber('posId')->name('api.v1.pos_wallets.show_by_network_pos');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('pos-wallets', [PosWalletController::class, 'store'])->name('api.v1.pos_wallets.store');
            Route::put('pos-wallets/{id}', [PosWalletController::class, 'update'])->whereNumber('id')->name('api.v1.pos_wallets.update');
            Route::delete('pos-wallets/{id}', [PosWalletController::class, 'destroy'])->whereNumber('id')->name('api.v1.pos_wallets.destroy');
            Route::post('pos-wallets/{id}/deposit', [PosWalletController::class, 'deposit'])->whereNumber('id')->name('api.v1.pos_wallets.deposit');
            Route::post('pos-wallets/{id}/withdraw', [PosWalletController::class, 'withdraw'])->whereNumber('id')->name('api.v1.pos_wallets.withdraw');
            Route::post('pos-wallets/transfer-from-network', [PosWalletController::class, 'transferFromNetwork'])->name('api.v1.pos_wallets.transfer_from_network');
            Route::post('pos-wallets/transfer-to-network', [PosWalletController::class, 'transferToNetwork'])->name('api.v1.pos_wallets.transfer_to_network');
            Route::post('pos-wallets/{id}/restore', [PosWalletController::class, 'restore'])->whereNumber('id')->name('api.v1.pos_wallets.restore');
            Route::delete('pos-wallets/{id}/force', [PosWalletController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.pos_wallets.force_delete');
            Route::post('pos-wallets/batch-delete', [PosWalletController::class, 'batchDelete'])->name('api.v1.pos_wallets.batch_delete');
            Route::post('pos-wallets/batch-restore', [PosWalletController::class, 'batchRestore'])->name('api.v1.pos_wallets.batch_restore');
            Route::post('pos-wallets/batch-force-delete', [PosWalletController::class, 'batchForceDelete'])->name('api.v1.pos_wallets.batch_force_delete');
        });

        // ==========================================
        // 10. طلبات شراء كروت الإنترنت (Voucher Requests)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('requests', [RequestController::class, 'index'])->name('api.v1.requests.index');
            Route::get('requests/trashed', [RequestController::class, 'trashed'])->name('api.v1.requests.trashed');
            Route::get('requests/{id}', [RequestController::class, 'show'])->whereNumber('id')->name('api.v1.requests.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('requests', [RequestController::class, 'store'])->name('api.v1.requests.store');
            Route::post('requests/{id}/approve', [RequestController::class, 'approve'])->whereNumber('id')->name('api.v1.requests.approve');
            Route::post('requests/{id}/reject', [RequestController::class, 'reject'])->whereNumber('id')->name('api.v1.requests.reject');
            Route::post('requests/{id}/cancel', [RequestController::class, 'cancel'])->whereNumber('id')->name('api.v1.requests.cancel');
            Route::delete('requests/{id}', [RequestController::class, 'destroy'])->whereNumber('id')->name('api.v1.requests.destroy');
            Route::post('requests/{id}/restore', [RequestController::class, 'restore'])->whereNumber('id')->name('api.v1.requests.restore');
            Route::delete('requests/{id}/force', [RequestController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.requests.force_delete');
            Route::post('requests/batch-delete', [RequestController::class, 'batchDelete'])->name('api.v1.requests.batch_delete');
            Route::post('requests/batch-restore', [RequestController::class, 'batchRestore'])->name('api.v1.requests.batch_restore');
            Route::post('requests/batch-force-delete', [RequestController::class, 'batchForceDelete'])->name('api.v1.requests.batch_force_delete');
        });

        // ==========================================
        // 11. فواتير مبيعات الكروت (Sale Invoices)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('sale-invoices', [SaleInvoiceController::class, 'index'])->name('api.v1.sale_invoices.index');
            Route::get('sale-invoices/trashed', [SaleInvoiceController::class, 'trashed'])->name('api.v1.sale_invoices.trashed');
            Route::get('sale-invoices/{id}', [SaleInvoiceController::class, 'show'])->whereNumber('id')->name('api.v1.sale_invoices.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('sale-invoices', [SaleInvoiceController::class, 'store'])->name('api.v1.sale_invoices.store');
            Route::put('sale-invoices/{id}', [SaleInvoiceController::class, 'update'])->whereNumber('id')->name('api.v1.sale_invoices.update');
            Route::delete('sale-invoices/{id}', [SaleInvoiceController::class, 'destroy'])->whereNumber('id')->name('api.v1.sale_invoices.destroy');
            Route::post('sale-invoices/{id}/restore', [SaleInvoiceController::class, 'restore'])->whereNumber('id')->name('api.v1.sale_invoices.restore');
            Route::delete('sale-invoices/{id}/force', [SaleInvoiceController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.sale_invoices.force_delete');
            Route::post('sale-invoices/batch-delete', [SaleInvoiceController::class, 'batchDelete'])->name('api.v1.sale_invoices.batch_delete');
            Route::post('sale-invoices/batch-restore', [SaleInvoiceController::class, 'batchRestore'])->name('api.v1.sale_invoices.batch_restore');
            Route::post('sale-invoices/batch-force-delete', [SaleInvoiceController::class, 'batchForceDelete'])->name('api.v1.sale_invoices.batch_force_delete');
        });

        // ==========================================
        // 12. المصروفات التشغيلية والبنود والمرفقات (Expenses & Operational Disbursals)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('expenses', [ExpenseController::class, 'index'])->name('api.v1.expenses.index');
            Route::get('expenses/trashed', [ExpenseController::class, 'trashed'])->name('api.v1.expenses.trashed');
            Route::get('expenses/{id}', [ExpenseController::class, 'show'])->whereNumber('id')->name('api.v1.expenses.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('expenses', [ExpenseController::class, 'store'])->name('api.v1.expenses.store');
            Route::put('expenses/{id}', [ExpenseController::class, 'update'])->whereNumber('id')->name('api.v1.expenses.update');
            Route::delete('expenses/{id}', [ExpenseController::class, 'destroy'])->whereNumber('id')->name('api.v1.expenses.destroy');
            Route::post('expenses/{id}/restore', [ExpenseController::class, 'restore'])->whereNumber('id')->name('api.v1.expenses.restore');
            Route::delete('expenses/{id}/force', [ExpenseController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.expenses.force_delete');
            Route::post('expenses/batch-delete', [ExpenseController::class, 'batchDelete'])->name('api.v1.expenses.batch_delete');
            Route::post('expenses/batch-restore', [ExpenseController::class, 'batchRestore'])->name('api.v1.expenses.batch_restore');
            Route::post('expenses/batch-force-delete', [ExpenseController::class, 'batchForceDelete'])->name('api.v1.expenses.batch_force_delete');
            Route::post('expenses/{id}/items', [ExpenseController::class, 'addItem'])->whereNumber('id')->name('api.v1.expenses.add_item');
            Route::delete('expenses/{id}/items/{itemId}', [ExpenseController::class, 'deleteItem'])->whereNumber('id')->whereNumber('itemId')->name('api.v1.expenses.delete_item');
            Route::delete('expenses/{id}/attachments/{attachmentId}', [ExpenseController::class, 'deleteAttachment'])->whereNumber('id')->whereNumber('attachmentId')->name('api.v1.expenses.delete_attachment');
            Route::apiResource('expenses', ExpenseController::class)->parameters(['expenses' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 13. باقات الاشتراك السحابية لمزودي الشبكات (Subscription Plans)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('subscription-plans', [SubscriptionPlanController::class, 'index'])->name('api.v1.subscription_plans.index');
            Route::get('subscription-plans/trashed', [SubscriptionPlanController::class, 'trashed'])->name('api.v1.subscription_plans.trashed');
            Route::get('subscription-plans/{id}', [SubscriptionPlanController::class, 'show'])->whereNumber('id')->name('api.v1.subscription_plans.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('subscription-plans', [SubscriptionPlanController::class, 'store'])->name('api.v1.subscription_plans.store');
            Route::put('subscription-plans/{id}', [SubscriptionPlanController::class, 'update'])->whereNumber('id')->name('api.v1.subscription_plans.update');
            Route::delete('subscription-plans/{id}', [SubscriptionPlanController::class, 'destroy'])->whereNumber('id')->name('api.v1.subscription_plans.destroy');
            Route::post('subscription-plans/{id}/restore', [SubscriptionPlanController::class, 'restore'])->whereNumber('id')->name('api.v1.subscription_plans.restore');
            Route::delete('subscription-plans/{id}/force', [SubscriptionPlanController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.subscription_plans.force_delete');
            Route::post('subscription-plans/{id}/toggle-status', [SubscriptionPlanController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.subscription_plans.toggle_status');
            Route::post('subscription-plans/{id}/features', [SubscriptionPlanController::class, 'attachFeature'])->whereNumber('id')->name('api.v1.subscription_plans.attach_feature');
            Route::delete('subscription-plans/{id}/features/{featureId}', [SubscriptionPlanController::class, 'detachFeature'])->whereNumber('id')->whereNumber('featureId')->name('api.v1.subscription_plans.detach_feature');
            Route::post('subscription-plans/batch-delete', [SubscriptionPlanController::class, 'batchDelete'])->name('api.v1.subscription_plans.batch_delete');
            Route::post('subscription-plans/batch-restore', [SubscriptionPlanController::class, 'batchRestore'])->name('api.v1.subscription_plans.batch_restore');
            Route::post('subscription-plans/batch-force-delete', [SubscriptionPlanController::class, 'batchForceDelete'])->name('api.v1.subscription_plans.batch_force_delete');
            Route::apiResource('subscription-plans', SubscriptionPlanController::class)->parameters(['subscription-plans' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 14. كتالوج ميزات النظام (System Features)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('features', [FeatureController::class, 'index'])->name('api.v1.features.index');
            Route::get('features/trashed', [FeatureController::class, 'trashed'])->name('api.v1.features.trashed');
            Route::get('features/{id}', [FeatureController::class, 'show'])->whereNumber('id')->name('api.v1.features.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('features', [FeatureController::class, 'store'])->name('api.v1.features.store');
            Route::put('features/{id}', [FeatureController::class, 'update'])->whereNumber('id')->name('api.v1.features.update');
            Route::delete('features/{id}', [FeatureController::class, 'destroy'])->whereNumber('id')->name('api.v1.features.destroy');
            Route::post('features/{id}/restore', [FeatureController::class, 'restore'])->whereNumber('id')->name('api.v1.features.restore');
            Route::delete('features/{id}/force', [FeatureController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.features.force_delete');
            Route::post('features/{id}/toggle-status', [FeatureController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.features.toggle_status');
            Route::post('features/batch-delete', [FeatureController::class, 'batchDelete'])->name('api.v1.features.batch_delete');
            Route::post('features/batch-restore', [FeatureController::class, 'batchRestore'])->name('api.v1.features.batch_restore');
            Route::post('features/batch-force-delete', [FeatureController::class, 'batchForceDelete'])->name('api.v1.features.batch_force_delete');
            Route::apiResource('features', FeatureController::class)->parameters(['features' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 15. الحسابات البنكية والمحافظ (Bank Accounts)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('api.v1.bank_accounts.index');
            Route::get('bank-accounts/active', [BankAccountController::class, 'active'])->name('api.v1.bank_accounts.active');
            Route::get('bank-accounts/trashed', [BankAccountController::class, 'trashed'])->name('api.v1.bank_accounts.trashed');
            Route::get('bank-accounts/{id}', [BankAccountController::class, 'show'])->whereNumber('id')->name('api.v1.bank_accounts.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('bank-accounts', [BankAccountController::class, 'store'])->name('api.v1.bank_accounts.store');
            Route::put('bank-accounts/{id}', [BankAccountController::class, 'update'])->whereNumber('id')->name('api.v1.bank_accounts.update');
            Route::delete('bank-accounts/{id}', [BankAccountController::class, 'destroy'])->whereNumber('id')->name('api.v1.bank_accounts.destroy');
            Route::post('bank-accounts/{id}/restore', [BankAccountController::class, 'restore'])->whereNumber('id')->name('api.v1.bank_accounts.restore');
            Route::delete('bank-accounts/{id}/force', [BankAccountController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.bank_accounts.force_delete');
            Route::post('bank-accounts/{id}/toggle-status', [BankAccountController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.bank_accounts.toggle_status');
            Route::post('bank-accounts/batch-delete', [BankAccountController::class, 'batchDelete'])->name('api.v1.bank_accounts.batch_delete');
            Route::post('bank-accounts/batch-restore', [BankAccountController::class, 'batchRestore'])->name('api.v1.bank_accounts.batch_restore');
            Route::post('bank-accounts/batch-force-delete', [BankAccountController::class, 'batchForceDelete'])->name('api.v1.bank_accounts.batch_force_delete');
            Route::apiResource('bank-accounts', BankAccountController::class)->parameters(['bank-accounts' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 16. طلبات الاشتراك والترقية (Subscription Plan Requests)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('subscription-plan-requests', [SubscriptionPlanRequestController::class, 'index'])->name('api.v1.subscription_plan_requests.index');
            Route::get('subscription-plan-requests/trashed', [SubscriptionPlanRequestController::class, 'trashed'])->name('api.v1.subscription_plan_requests.trashed');
            Route::get('subscription-plan-requests/{id}', [SubscriptionPlanRequestController::class, 'show'])->whereNumber('id')->name('api.v1.subscription_plan_requests.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('subscription-plan-requests', [SubscriptionPlanRequestController::class, 'store'])->name('api.v1.subscription_plan_requests.store');
            Route::post('subscription-plan-requests/{id}/approve', [SubscriptionPlanRequestController::class, 'approve'])->whereNumber('id')->name('api.v1.subscription_plan_requests.approve');
            Route::post('subscription-plan-requests/{id}/reject', [SubscriptionPlanRequestController::class, 'reject'])->whereNumber('id')->name('api.v1.subscription_plan_requests.reject');
            Route::post('subscription-plan-requests/{id}/cancel', [SubscriptionPlanRequestController::class, 'cancel'])->whereNumber('id')->name('api.v1.subscription_plan_requests.cancel');
            Route::delete('subscription-plan-requests/{id}', [SubscriptionPlanRequestController::class, 'destroy'])->whereNumber('id')->name('api.v1.subscription_plan_requests.destroy');
            Route::post('subscription-plan-requests/{id}/restore', [SubscriptionPlanRequestController::class, 'restore'])->whereNumber('id')->name('api.v1.subscription_plan_requests.restore');
            Route::delete('subscription-plan-requests/{id}/force', [SubscriptionPlanRequestController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.subscription_plan_requests.force_delete');
            Route::post('subscription-plan-requests/batch-delete', [SubscriptionPlanRequestController::class, 'batchDelete'])->name('api.v1.subscription_plan_requests.batch_delete');
            Route::post('subscription-plan-requests/batch-restore', [SubscriptionPlanRequestController::class, 'batchRestore'])->name('api.v1.subscription_plan_requests.batch_restore');
            Route::post('subscription-plan-requests/batch-force-delete', [SubscriptionPlanRequestController::class, 'batchForceDelete'])->name('api.v1.subscription_plan_requests.batch_force_delete');
            Route::apiResource('subscription-plan-requests', SubscriptionPlanRequestController::class)->parameters(['subscription-plan-requests' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 17. اشتراكات مزودي الشبكات وتتبع الحصص (Provider Subscriptions & Feature Quotas)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('provider-subscriptions', [ProviderSubscriptionController::class, 'index'])->name('api.v1.provider_subscriptions.index');
            Route::get('provider-subscriptions/{id}', [ProviderSubscriptionController::class, 'show'])->whereNumber('id')->name('api.v1.provider_subscriptions.show');
            Route::get('provider-subscriptions/network/{networkId}/active', [ProviderSubscriptionController::class, 'activeForNetwork'])->whereNumber('networkId')->name('api.v1.provider_subscriptions.active_for_network');
            Route::get('provider-subscriptions/{id}/consumptions', [ProviderSubscriptionController::class, 'consumptions'])->whereNumber('id')->name('api.v1.provider_subscriptions.consumptions');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('provider-subscriptions/{id}/extend', [ProviderSubscriptionController::class, 'extend'])->whereNumber('id')->name('api.v1.provider_subscriptions.extend');
            Route::post('provider-subscriptions/network/{networkId}/consume', [ProviderSubscriptionController::class, 'consume'])->whereNumber('networkId')->name('api.v1.provider_subscriptions.consume');
            Route::post('provider-subscriptions/network/{networkId}/activate-early', [ProviderSubscriptionController::class, 'activateEarly'])->whereNumber('networkId')->name('api.v1.provider_subscriptions.activate_early');
        });

        // ==========================================
        // 18. تذاكر الدعم الفني والمراسلات (Support Tickets & Communications Engine)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('support-tickets', [SupportTicketController::class, 'index'])->name('api.v1.support_tickets.index');
            Route::get('support-tickets/trashed', [SupportTicketController::class, 'trashed'])->name('api.v1.support_tickets.trashed');
            Route::get('support-tickets/{id}', [SupportTicketController::class, 'show'])->whereNumber('id')->name('api.v1.support_tickets.show');
            Route::get('support-tickets/{id}/messages', [SupportTicketController::class, 'messages'])->whereNumber('id')->name('api.v1.support_tickets.messages');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('support-tickets', [SupportTicketController::class, 'store'])->name('api.v1.support_tickets.store');
            Route::put('support-tickets/{id}', [SupportTicketController::class, 'update'])->whereNumber('id')->name('api.v1.support_tickets.update');
            Route::delete('support-tickets/{id}', [SupportTicketController::class, 'destroy'])->whereNumber('id')->name('api.v1.support_tickets.destroy');
            Route::post('support-tickets/{id}/restore', [SupportTicketController::class, 'restore'])->whereNumber('id')->name('api.v1.support_tickets.restore');
            Route::delete('support-tickets/{id}/force', [SupportTicketController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.support_tickets.force_delete');
            Route::post('support-tickets/batch-delete', [SupportTicketController::class, 'batchDelete'])->name('api.v1.support_tickets.batch_delete');
            Route::post('support-tickets/batch-restore', [SupportTicketController::class, 'batchRestore'])->name('api.v1.support_tickets.batch_restore');
            Route::post('support-tickets/batch-force-delete', [SupportTicketController::class, 'batchForceDelete'])->name('api.v1.support_tickets.batch_force_delete');
            Route::post('support-tickets/{id}/messages', [SupportTicketController::class, 'addMessage'])->whereNumber('id')->name('api.v1.support_tickets.add_message');
            Route::post('support-tickets/{id}/read', [SupportTicketController::class, 'markAsRead'])->whereNumber('id')->name('api.v1.support_tickets.mark_as_read');
            Route::apiResource('support-tickets', SupportTicketController::class)->parameters(['support-tickets' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 19. الإشعارات والتنبيهات الموجهة (Notifications Engine)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
            Route::get('notifications/my', [NotificationController::class, 'myNotifications'])->name('api.v1.notifications.my');
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('api.v1.notifications.unread_count');
            Route::get('notifications/trashed', [NotificationController::class, 'trashed'])->name('api.v1.notifications.trashed');
            Route::get('notifications/{id}', [NotificationController::class, 'show'])->whereNumber('id')->name('api.v1.notifications.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('notifications', [NotificationController::class, 'store'])->name('api.v1.notifications.store');
            Route::put('notifications/{id}', [NotificationController::class, 'update'])->whereNumber('id')->name('api.v1.notifications.update');
            Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id')->name('api.v1.notifications.destroy');
            Route::post('notifications/{id}/restore', [NotificationController::class, 'restore'])->whereNumber('id')->name('api.v1.notifications.restore');
            Route::delete('notifications/{id}/force', [NotificationController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.notifications.force_delete');
            Route::post('notifications/batch-delete', [NotificationController::class, 'batchDelete'])->name('api.v1.notifications.batch_delete');
            Route::post('notifications/batch-restore', [NotificationController::class, 'batchRestore'])->name('api.v1.notifications.batch_restore');
            Route::post('notifications/batch-force-delete', [NotificationController::class, 'batchForceDelete'])->name('api.v1.notifications.batch_force_delete');
            Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->whereNumber('id')->name('api.v1.notifications.mark_as_read');
            Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('api.v1.notifications.mark_all_read');
            Route::post('notifications/fcm-token', [NotificationController::class, 'updateFcmToken'])->name('api.v1.notifications.fcm_token');
            Route::apiResource('notifications', NotificationController::class)->parameters(['notifications' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 20. إعدادات النظام وتهيئة البيئة (System Settings & Engine Configuration)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('system-settings', [SystemSettingController::class, 'index'])->name('api.v1.system_settings.index');
            Route::get('system-settings/trashed', [SystemSettingController::class, 'trashed'])->name('api.v1.system_settings.trashed');
            Route::get('system-settings/category/{category}', [SystemSettingController::class, 'byCategory'])->name('api.v1.system_settings.by_category');
            Route::get('system-settings/key/{key}', [SystemSettingController::class, 'byKey'])->name('api.v1.system_settings.by_key');
            Route::get('system-settings/{id}', [SystemSettingController::class, 'show'])->whereNumber('id')->name('api.v1.system_settings.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('system-settings', [SystemSettingController::class, 'store'])->name('api.v1.system_settings.store');
            Route::post('system-settings/batch-update', [SystemSettingController::class, 'batchUpdate'])->name('api.v1.system_settings.batch_update');
            Route::post('system-settings/clear-cache', [SystemSettingController::class, 'clearCache'])->name('api.v1.system_settings.clear_cache');
            Route::put('system-settings/{id}', [SystemSettingController::class, 'update'])->whereNumber('id')->name('api.v1.system_settings.update');
            Route::delete('system-settings/{id}', [SystemSettingController::class, 'destroy'])->whereNumber('id')->name('api.v1.system_settings.destroy');
            Route::post('system-settings/{id}/restore', [SystemSettingController::class, 'restore'])->whereNumber('id')->name('api.v1.system_settings.restore');
            Route::delete('system-settings/{id}/force', [SystemSettingController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.system_settings.force_delete');
            Route::post('system-settings/batch-delete', [SystemSettingController::class, 'batchDelete'])->name('api.v1.system_settings.batch_delete');
            Route::post('system-settings/batch-restore', [SystemSettingController::class, 'batchRestore'])->name('api.v1.system_settings.batch_restore');
            Route::post('system-settings/batch-force-delete', [SystemSettingController::class, 'batchForceDelete'])->name('api.v1.system_settings.batch_force_delete');
            Route::apiResource('system-settings', SystemSettingController::class)->parameters(['system-settings' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 21. سجلات تدقيق النظام والتتبع الأمني (System Audit Logs & Activity Trails)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('system-logs', [SystemLogController::class, 'index'])->name('api.v1.system_logs.index');
            Route::get('system-logs/stats', [SystemLogController::class, 'stats'])->name('api.v1.system_logs.stats');
            Route::get('system-logs/modules', [SystemLogController::class, 'modules'])->name('api.v1.system_logs.modules');
            Route::get('system-logs/actions', [SystemLogController::class, 'actions'])->name('api.v1.system_logs.actions');
            Route::get('system-logs/entity/{entityType}/{entityId}', [SystemLogController::class, 'byEntity'])->whereNumber('entityId')->name('api.v1.system_logs.by_entity');
            Route::get('system-logs/user/{userId}', [SystemLogController::class, 'byUser'])->whereNumber('userId')->name('api.v1.system_logs.by_user');
            Route::get('system-logs/{id}', [SystemLogController::class, 'show'])->whereNumber('id')->name('api.v1.system_logs.show');
        });

        // ==========================================
        // 22. المحرك الجغرافي: المحافظات والمديريات (Geography Engine)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('governorates/counts', [GovernorateController::class, 'counts'])->name('api.v1.governorates.counts');
            Route::get('governorates/trashed', [GovernorateController::class, 'trashed'])->name('api.v1.governorates.trashed');
            Route::get('directorates/trashed', [DirectorateController::class, 'trashed'])->name('api.v1.directorates.trashed');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('governorates', [GovernorateController::class, 'store'])->name('api.v1.governorates.store');
            Route::put('governorates/{id}', [GovernorateController::class, 'update'])->whereNumber('id')->name('api.v1.governorates.update');
            Route::delete('governorates/{id}', [GovernorateController::class, 'destroy'])->whereNumber('id')->name('api.v1.governorates.destroy');
            Route::post('governorates/{id}/restore', [GovernorateController::class, 'restore'])->whereNumber('id')->name('api.v1.governorates.restore');
            Route::delete('governorates/{id}/force', [GovernorateController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.governorates.force_delete');
            Route::post('governorates/batch-delete', [GovernorateController::class, 'batchDelete'])->name('api.v1.governorates.batch_delete');
            Route::post('governorates/batch-restore', [GovernorateController::class, 'batchRestore'])->name('api.v1.governorates.batch_restore');
            Route::post('governorates/batch-force-delete', [GovernorateController::class, 'batchForceDelete'])->name('api.v1.governorates.batch_force_delete');
            Route::apiResource('governorates', GovernorateController::class)->parameters(['governorates' => 'id'])->except(['index', 'show']);

            Route::post('directorates', [DirectorateController::class, 'store'])->name('api.v1.directorates.store');
            Route::put('directorates/{id}', [DirectorateController::class, 'update'])->whereNumber('id')->name('api.v1.directorates.update');
            Route::delete('directorates/{id}', [DirectorateController::class, 'destroy'])->whereNumber('id')->name('api.v1.directorates.destroy');
            Route::post('directorates/{id}/restore', [DirectorateController::class, 'restore'])->whereNumber('id')->name('api.v1.directorates.restore');
            Route::delete('directorates/{id}/force', [DirectorateController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.directorates.force_delete');
            Route::post('directorates/batch-delete', [DirectorateController::class, 'batchDelete'])->name('api.v1.directorates.batch_delete');
            Route::post('directorates/batch-restore', [DirectorateController::class, 'batchRestore'])->name('api.v1.directorates.batch_restore');
            Route::post('directorates/batch-force-delete', [DirectorateController::class, 'batchForceDelete'])->name('api.v1.directorates.batch_force_delete');
            Route::apiResource('directorates', DirectorateController::class)->parameters(['directorates' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // 23. إعدادات وبوابات الرسائل النصية للشبكات (Network SMS Settings)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('sms-settings', [NetworkSmsSettingController::class, 'getOverview'])->name('api.v1.sms_settings.overview');
            Route::get('sms-settings/networks/{id}', [NetworkSmsSettingController::class, 'show'])->whereNumber('id')->name('api.v1.sms_settings.network');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::put('sms-settings/global', [NetworkSmsSettingController::class, 'updateGlobal'])->name('api.v1.sms_settings.update_global');
            Route::put('sms-settings/networks/{id}', [NetworkSmsSettingController::class, 'update'])->whereNumber('id')->name('api.v1.sms_settings.update');
            Route::post('sms-settings/networks/{id}/test', [NetworkSmsSettingController::class, 'testSms'])->whereNumber('id')->name('api.v1.sms_settings.test');
        });

        // ==========================================
        // 24. محرك النسخ الاحتياطي السحابي الشامل (Cloud Backup & Disaster Recovery)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('backups/settings', [BackupController::class, 'settings'])->name('api.v1.backups.settings');
            Route::get('backups/health', [BackupController::class, 'health'])->name('api.v1.backups.health');
            Route::get('backups/drive/auth', [BackupController::class, 'driveAuthUrl'])->name('api.v1.backups.drive_auth');
            Route::get('backups', [BackupController::class, 'index'])->name('api.v1.backups.index');
            Route::get('backups/{id}', [BackupController::class, 'show'])->whereNumber('id')->name('api.v1.backups.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('backups/drive/token', [BackupController::class, 'storeDriveToken'])->name('api.v1.backups.drive_token');
            Route::post('backups/drive/disconnect', [BackupController::class, 'disconnectDrive'])->name('api.v1.backups.drive_disconnect');
            Route::put('backups/config', [BackupController::class, 'updateConfig'])->name('api.v1.backups.update_config');
            Route::post('backups/run', [BackupController::class, 'runBackup'])->name('api.v1.backups.run');
            Route::delete('backups/{id}', [BackupController::class, 'destroy'])->whereNumber('id')->name('api.v1.backups.destroy');
        });

        // ==========================================
        // 25. سجلات ومحرك المهام المجدولة (Scheduled Tasks & Catch-up Engine)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('scheduled-tasks', [ScheduledTaskRunController::class, 'index'])->name('api.v1.scheduled_tasks.index');
            Route::get('scheduled-tasks/stats', [ScheduledTaskRunController::class, 'stats'])->name('api.v1.scheduled_tasks.stats');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('scheduled-tasks/catchup', [ScheduledTaskRunController::class, 'catchup'])->name('api.v1.scheduled_tasks.catchup');
        });

        // ==========================================
        // 26. إشعارات ورسائل الإدارة الموجهة (Admin Multi-Channel Notifications)
        // ==========================================
        Route::middleware('throttle:api-write')->group(function () {
            Route::post('admin/notifications/send', [AdminNotificationController::class, 'send'])->name('api.v1.admin_notifications.send');
            Route::post('admin/notifications/user', [AdminNotificationController::class, 'sendToUser'])->name('api.v1.admin_notifications.user');
        });

    });

});
