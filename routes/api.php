<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\NetworkController;
use App\Http\Controllers\Api\V1\NetworkPosController;
use App\Http\Controllers\Api\V1\NetworkWalletController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\PosWalletController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VoucherPackageController;
use App\Http\Controllers\Api\V1\VoucherPackageDiscountController;
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

        Route::middleware('throttle:auth')->group(function () {
            Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');
            Route::post('register-network', [AuthController::class, 'registerNetwork'])->name('api.v1.auth.register_network');
            Route::post('register-pos', [AuthController::class, 'registerPos'])->name('api.v1.auth.register_pos');
            Route::post('register', [AuthController::class, 'register'])->name('api.v1.auth.register');
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
    });

    // ==========================================
    // المسارات المحمية بالجلسة (Protected Routes - Session Auth)
    // ==========================================
    Route::middleware('session.auth')->group(function () {

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

    });

});
