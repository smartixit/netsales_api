<?php

use App\Http\Controllers\API\V1\AdminNotificationController;
use App\Http\Controllers\API\V1\Billing\BillingCatalogController;
use App\Http\Controllers\API\V1\Billing\BillingPolicyController;
use App\Http\Controllers\API\V1\Billing\BillingFinanceController;
use App\Http\Controllers\API\V1\Billing\BillingSelfServiceController;
use App\Http\Controllers\API\V1\Billing\BillingTrialEligibilityController;
use App\Http\Controllers\API\V1\Billing\BillingReportController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\BackupController;
use App\Http\Controllers\API\V1\DashboardController;
use App\Http\Controllers\API\V1\DebtRepaymentController;
use App\Http\Controllers\API\V1\DirectorateController;
use App\Http\Controllers\API\V1\ExpenseController;
use App\Http\Controllers\API\V1\FileController;
use App\Http\Controllers\API\V1\GovernorateController;
use App\Http\Controllers\API\V1\NetworkController;
use App\Http\Controllers\API\V1\NetworkMembershipController;
use App\Http\Controllers\API\V1\NetworkPosController;
use App\Http\Controllers\API\V1\NetworkSmsSettingController;
use App\Http\Controllers\API\V1\NetworkWalletController;
use App\Http\Controllers\API\V1\NetworkWorkspaceOverviewController;
use App\Http\Controllers\API\V1\OperationalReportController;
use App\Http\Controllers\API\V1\NotificationController;
use App\Http\Controllers\API\V1\OtpController;
use App\Http\Controllers\API\V1\PosController;
use App\Http\Controllers\API\V1\PosMembershipController;
use App\Http\Controllers\API\V1\PosWalletController;
use App\Http\Controllers\API\V1\RequestController;
use App\Http\Controllers\API\V1\Reporting\ReportController;
use App\Http\Controllers\API\V1\RoleController;
use App\Http\Controllers\API\V1\SaleInvoiceController;
use App\Http\Controllers\API\V1\ScheduledTaskRunController;
use App\Http\Controllers\API\V1\SecurityController;
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
            Route::post('login-to-dashboard', [AuthController::class, 'loginToDashboard'])->name('api.v1.auth.login_to_dashboard');
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
            Route::post('2fa/request-otp', [AuthController::class, 'requestTwoFactorOtp'])->name('api.v1.auth.2fa.request_otp');
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
        Route::get('roles', [RoleController::class, 'index'])
            ->middleware(['throttle:api-read', 'platform.user.scope', 'permission:roles.view'])
            ->name('api.v1.roles.index');

        Route::middleware(['throttle:api-read', 'platform.user.scope', 'permission:users.view'])->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('api.v1.users.index');
            Route::get('users/trashed', [UserController::class, 'trashed'])->name('api.v1.users.trashed');
            Route::get('users/{id}', [UserController::class, 'show'])->whereNumber('id')->name('api.v1.users.show');
        });

        Route::middleware(['throttle:api-write', 'platform.user.scope'])->group(function () {
            Route::post('users/{id}/permissions', [UserController::class, 'syncPermissions'])
                ->whereNumber('id')->middleware('permission:roles.manage')->name('api.v1.users.sync_permissions');
            Route::post('users/batch-delete', [UserController::class, 'bulkDelete'])
                ->middleware('permission:users.delete')->name('api.v1.users.batch_delete');
            Route::post('users/batch-restore', [UserController::class, 'bulkRestore'])
                ->middleware('permission:users.edit')->name('api.v1.users.batch_restore');
            Route::post('users/batch-force-delete', [UserController::class, 'bulkForceDelete'])
                ->middleware('permission:users.delete')->name('api.v1.users.batch_force_delete');
            Route::post('users/{id}/restore', [UserController::class, 'restore'])
                ->whereNumber('id')->middleware('permission:users.edit')->name('api.v1.users.restore');
            Route::delete('users/{id}/force', [UserController::class, 'forceDelete'])
                ->whereNumber('id')->middleware('permission:users.delete')->name('api.v1.users.force_delete');
            Route::post('users', [UserController::class, 'store'])
                ->middleware('permission:users.create')->name('api.v1.users.store');
            Route::put('users/{id}', [UserController::class, 'update'])
                ->whereNumber('id')->middleware('permission:users.edit')->name('api.v1.users.update');
            Route::delete('users/{id}', [UserController::class, 'destroy'])
                ->whereNumber('id')->middleware('permission:users.delete')->name('api.v1.users.destroy');
            Route::post('users/{id}/status', [UserController::class, 'updateStatus'])
                ->whereNumber('id')->middleware('permission:users.edit')->name('api.v1.users.update_status');
        });

        Route::post('users/{id}/profile-picture', [UserController::class, 'updateProfilePicture'])
            ->whereNumber('id')
            ->middleware(['throttle:api-upload', 'platform.user.scope', 'permission:users.edit'])
            ->name('api.v1.users.profile_picture');

        // 2. إدارة الأمان والجلسات والحظر (Security & Sessions)
        Route::prefix('security')->middleware('platform.user.scope')->group(function () {
            Route::middleware(['throttle:api-read', 'permission:auth.view'])->group(function () {
                Route::get('users/{id}/sessions', [SecurityController::class, 'getUserSessions'])
                    ->whereNumber('id')->name('api.v1.security.user_sessions');
                Route::get('users/{id}/devices', [SecurityController::class, 'getUserDevices'])
                    ->whereNumber('id')->name('api.v1.security.user_devices');
            });
            Route::middleware(['throttle:api-write', 'permission:auth.manage'])->group(function () {
                Route::post('ban-user', [SecurityController::class, 'banUser'])->name('api.v1.security.ban_user');
                Route::post('ban-device', [SecurityController::class, 'banDevice'])->name('api.v1.security.ban_device');
                Route::delete('bans/{id}', [SecurityController::class, 'unban'])
                    ->whereNumber('id')->name('api.v1.security.unban');
                Route::post('sessions/{id}/terminate', [SecurityController::class, 'terminateSession'])
                    ->whereNumber('id')->name('api.v1.security.terminate_session');
            });
        });

        // Backward-compatible aliases keep the same authorization and platform-scope checks.
        Route::middleware(['throttle:api-read', 'platform.user.scope', 'permission:auth.view'])->group(function () {
            Route::get('users/{id}/sessions', [SecurityController::class, 'getUserSessions'])->whereNumber('id');
            Route::get('users/{id}/devices', [SecurityController::class, 'getUserDevices'])->whereNumber('id');
        });
        Route::middleware(['throttle:api-write', 'platform.user.scope', 'permission:auth.manage'])->group(function () {
            Route::post('ban-user', [SecurityController::class, 'banUser']);
            Route::post('ban-device', [SecurityController::class, 'banDevice']);
            Route::delete('bans/{id}', [SecurityController::class, 'unban'])->whereNumber('id');
            Route::post('sessions/{id}/terminate', [SecurityController::class, 'terminateSession'])->whereNumber('id');
        });

        // 3. إدارة مزودي الشبكات (Networks Management)
        // ==========================================
        Route::middleware(['throttle:api-read', 'permission:networks.view'])->group(function () {
            Route::get('networks', [NetworkController::class, 'index'])->name('api.v1.networks.index');
            Route::get('networks/trashed', [NetworkController::class, 'trashed'])->name('api.v1.networks.trashed');
            Route::get('networks/{id}', [NetworkController::class, 'show'])->whereNumber('id')->name('api.v1.networks.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('networks', [NetworkController::class, 'store'])->name('api.v1.networks.store')->middleware('permission:networks.create');
            Route::put('networks/{id}', [NetworkController::class, 'update'])->whereNumber('id')->name('api.v1.networks.update')->middleware('permission:networks.edit');
            Route::delete('networks/{id}', [NetworkController::class, 'destroy'])->whereNumber('id')->name('api.v1.networks.destroy')->middleware('permission:networks.delete');
            Route::post('networks/batch-delete', [NetworkController::class, 'batchDelete'])->name('api.v1.networks.batch_delete')->middleware('permission:networks.delete');
            Route::post('networks/batch-restore', [NetworkController::class, 'batchRestore'])->name('api.v1.networks.batch_restore')->middleware('permission:networks.edit');
            Route::post('networks/batch-force-delete', [NetworkController::class, 'batchForceDelete'])->name('api.v1.networks.batch_force_delete')->middleware('permission:networks.delete');
            Route::post('networks/{id}/restore', [NetworkController::class, 'restore'])->name('api.v1.networks.restore')->middleware('permission:networks.edit');
            Route::delete('networks/{id}/force', [NetworkController::class, 'forceDelete'])->name('api.v1.networks.force_delete')->middleware('permission:networks.delete');
            Route::post('networks/{id}/approve', [NetworkController::class, 'approve'])->whereNumber('id')->name('api.v1.networks.approve')->middleware('permission:networks.approve');
            Route::post('networks/{id}/reject', [NetworkController::class, 'reject'])->whereNumber('id')->name('api.v1.networks.reject')->middleware('permission:networks.reject');
            Route::apiResource('networks', NetworkController::class)->parameters(['networks' => 'id'])->except(['index', 'show', 'store', 'update', 'destroy']);
        });

        // ملخص مساحة عمل الشبكة: scoped to an active network owner/member and permission set.
        Route::middleware(['throttle:api-read', 'operational.scope'])->group(function () {
            Route::get('networks/{networkId}/workspace-overview', [NetworkWorkspaceOverviewController::class, 'show'])
                ->whereNumber('networkId')
                ->name('api.v1.networks.workspace_overview');
        });

        // 3.1. إدارة أعضاء وموظفي الشبكة (Scoped Network Memberships)
        Route::middleware(['throttle:api-read', 'operational.scope'])->group(function () {
            Route::get('networks/{networkId}/members/assignable-roles', [NetworkMembershipController::class, 'assignableRoles'])->whereNumber('networkId')->name('api.v1.networks.members.assignable_roles');
            Route::get('networks/{networkId}/members', [NetworkMembershipController::class, 'index'])->whereNumber('networkId')->name('api.v1.networks.members.index');
        });
        Route::middleware(['throttle:api-write', 'operational.scope'])->group(function () {
            Route::post('networks/{networkId}/members', [NetworkMembershipController::class, 'store'])->whereNumber('networkId')->name('api.v1.networks.members.store');
            Route::put('networks/{networkId}/members/{userId}/role', [NetworkMembershipController::class, 'updateRole'])->whereNumber('networkId')->whereNumber('userId')->name('api.v1.networks.members.update_role');
            Route::put('networks/{networkId}/members/{userId}/status', [NetworkMembershipController::class, 'updateStatus'])->whereNumber('networkId')->whereNumber('userId')->name('api.v1.networks.members.update_status');
            Route::delete('networks/{networkId}/members/{userId}', [NetworkMembershipController::class, 'destroy'])->whereNumber('networkId')->whereNumber('userId')->name('api.v1.networks.members.destroy');
        });

        // ==========================================
        // 4. إدارة نقاط البيع والوكلاء (POS Management)
        // ==========================================
        Route::middleware(['throttle:api-read', 'permission:pos.view'])->group(function () {
            Route::get('pos', [PosController::class, 'index'])->name('api.v1.pos.index');
            Route::get('pos/trashed', [PosController::class, 'trashed'])->name('api.v1.pos.trashed');
            Route::get('pos/{id}', [PosController::class, 'show'])->whereNumber('id')->name('api.v1.pos.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('pos', [PosController::class, 'store'])->name('api.v1.pos.store')->middleware('permission:pos.create');
            Route::put('pos/{id}', [PosController::class, 'update'])->whereNumber('id')->name('api.v1.pos.update')->middleware('permission:pos.edit');
            Route::delete('pos/{id}', [PosController::class, 'destroy'])->whereNumber('id')->name('api.v1.pos.destroy')->middleware('permission:pos.delete');
            Route::post('pos/batch-delete', [PosController::class, 'batchDelete'])->name('api.v1.pos.batch_delete')->middleware('permission:pos.delete');
            Route::post('pos/batch-restore', [PosController::class, 'batchRestore'])->name('api.v1.pos.batch_restore')->middleware('permission:pos.edit');
            Route::post('pos/batch-force-delete', [PosController::class, 'batchForceDelete'])->name('api.v1.pos.batch_force_delete')->middleware('permission:pos.delete');
            Route::post('pos/{id}/restore', [PosController::class, 'restore'])->name('api.v1.pos.restore')->middleware('permission:pos.edit');
            Route::delete('pos/{id}/force', [PosController::class, 'forceDelete'])->name('api.v1.pos.force_delete')->middleware('permission:pos.delete');
            Route::post('pos/{id}/approve', [PosController::class, 'approve'])->whereNumber('id')->name('api.v1.pos.approve')->middleware('permission:pos.edit');
            Route::post('pos/{id}/reject', [PosController::class, 'reject'])->whereNumber('id')->name('api.v1.pos.reject')->middleware('permission:pos.edit');
            Route::apiResource('pos', PosController::class)->parameters(['pos' => 'id'])->except(['index', 'show', 'store', 'update', 'destroy']);
        });

        // 4.1. إدارة أعضاء وموظفي نقاط البيع (Scoped POS Memberships)
        Route::middleware(['throttle:api-read', 'operational.scope'])->group(function () {
            Route::get('pos/{posId}/members/assignable-roles', [PosMembershipController::class, 'assignableRoles'])->whereNumber('posId')->name('api.v1.pos.members.assignable_roles');
            Route::get('pos/{posId}/members', [PosMembershipController::class, 'index'])->whereNumber('posId')->name('api.v1.pos.members.index');
        });
        Route::middleware(['throttle:api-write', 'operational.scope'])->group(function () {
            Route::post('pos/{posId}/members', [PosMembershipController::class, 'store'])->whereNumber('posId')->name('api.v1.pos.members.store');
            Route::put('pos/{posId}/members/{userId}/role', [PosMembershipController::class, 'updateRole'])->whereNumber('posId')->whereNumber('userId')->name('api.v1.pos.members.update_role');
            Route::put('pos/{posId}/members/{userId}/status', [PosMembershipController::class, 'updateStatus'])->whereNumber('posId')->whereNumber('userId')->name('api.v1.pos.members.update_status');
            Route::delete('pos/{posId}/members/{userId}', [PosMembershipController::class, 'destroy'])->whereNumber('posId')->whereNumber('userId')->name('api.v1.pos.members.destroy');
        });

        // ==========================================
        // 5. ربط نقاط البيع بمزودي الشبكات (Network POS Links)
        // ==========================================
        Route::middleware(['throttle:api-read', 'permission:networks.view'])->group(function () {
            Route::get('network-pos', [NetworkPosController::class, 'index'])->name('api.v1.network_pos.index');
            Route::get('network-pos/{id}', [NetworkPosController::class, 'show'])->whereNumber('id')->name('api.v1.network_pos.show');
            Route::get('networks/{id}/pos', [NetworkPosController::class, 'networkPosList'])->whereNumber('id')->name('api.v1.networks.pos_list');
        });

        Route::middleware(['throttle:api-read', 'permission:pos.view'])->group(function () {
            Route::get('pos/{id}/networks', [NetworkPosController::class, 'posNetworksList'])->whereNumber('id')->name('api.v1.pos.networks_list');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('network-pos', [NetworkPosController::class, 'store'])->name('api.v1.network_pos.store')->middleware('permission:networks.edit');
            Route::put('network-pos/{id}', [NetworkPosController::class, 'update'])->whereNumber('id')->name('api.v1.network_pos.update')->middleware('permission:networks.edit');
            Route::delete('network-pos/{id}', [NetworkPosController::class, 'destroy'])->whereNumber('id')->name('api.v1.network_pos.destroy')->middleware('permission:networks.edit');
            Route::post('network-pos/{id}/toggle-status', [NetworkPosController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.network_pos.toggle_status')->middleware('permission:networks.edit');
            Route::post('network-pos/batch-delete', [NetworkPosController::class, 'batchDelete'])->name('api.v1.network_pos.batch_delete')->middleware('permission:networks.edit');
            Route::post('network-pos/batch-status', [NetworkPosController::class, 'batchStatus'])->name('api.v1.network_pos.batch_status')->middleware('permission:networks.edit');

            // QR-Based POS–Network Linking Challenges
            Route::post('network-pos/challenges', [NetworkPosController::class, 'createChallenge'])->name('api.v1.network_pos.challenges.create')->middleware('permission:networks.edit');
            Route::post('network-pos/challenges/preview', [NetworkPosController::class, 'previewChallenge'])->name('api.v1.network_pos.challenges.preview')->middleware('permission:pos.view');
            Route::post('network-pos/challenges/confirm', [NetworkPosController::class, 'confirmChallenge'])->name('api.v1.network_pos.challenges.confirm')->middleware('permission:pos.edit');
            Route::delete('network-pos/challenges/{uuid}', [NetworkPosController::class, 'revokeChallenge'])->name('api.v1.network_pos.challenges.revoke')->middleware('permission:networks.edit');
        });

        // ==========================================
        // 6. باقات كروت الإنترنت (Voucher Packages)
        // ==========================================
        Route::middleware(['throttle:api-read', 'permission:voucher_packages.view'])->group(function () {
            Route::get('voucher-packages', [VoucherPackageController::class, 'index'])->name('api.v1.voucher_packages.index');
            Route::get('voucher-packages/trashed', [VoucherPackageController::class, 'trashed'])->name('api.v1.voucher_packages.trashed');
            Route::get('voucher-packages/{id}', [VoucherPackageController::class, 'show'])->whereNumber('id')->name('api.v1.voucher_packages.show');
            Route::get('networks/{id}/voucher-packages', [VoucherPackageController::class, 'networkPackages'])->whereNumber('id')->name('api.v1.networks.voucher_packages');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('voucher-packages', [VoucherPackageController::class, 'store'])->name('api.v1.voucher_packages.store')->middleware('permission:voucher_packages.create');
            Route::put('voucher-packages/{id}', [VoucherPackageController::class, 'update'])->whereNumber('id')->name('api.v1.voucher_packages.update')->middleware('permission:voucher_packages.edit');
            Route::patch('voucher-packages/{id}', [VoucherPackageController::class, 'update'])->whereNumber('id')->name('api.v1.voucher_packages.update')->middleware('permission:voucher_packages.edit');
            Route::delete('voucher-packages/{id}', [VoucherPackageController::class, 'destroy'])->whereNumber('id')->name('api.v1.voucher_packages.destroy')->middleware('permission:voucher_packages.delete');
            Route::post('voucher-packages/batch-delete', [VoucherPackageController::class, 'batchDelete'])->name('api.v1.voucher_packages.batch_delete')->middleware('permission:voucher_packages.delete');
            Route::post('voucher-packages/batch-restore', [VoucherPackageController::class, 'batchRestore'])->name('api.v1.voucher_packages.batch_restore')->middleware('permission:voucher_packages.edit');
            Route::post('voucher-packages/batch-force-delete', [VoucherPackageController::class, 'batchForceDelete'])->name('api.v1.voucher_packages.batch_force_delete')->middleware('permission:voucher_packages.delete');
            Route::post('voucher-packages/{id}/restore', [VoucherPackageController::class, 'restore'])->whereNumber('id')->name('api.v1.voucher_packages.restore')->middleware('permission:voucher_packages.edit');
            Route::delete('voucher-packages/{id}/force', [VoucherPackageController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.voucher_packages.force_delete')->middleware('permission:voucher_packages.delete');
            Route::post('voucher-packages/{id}/toggle-status', [VoucherPackageController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.voucher_packages.toggle_status')->middleware('permission:voucher_packages.edit');
        });

        // ==========================================
        // 7. خصومات وعمولات باقات الكروت المخصصة (Voucher Package Discounts)
        // ==========================================
        Route::middleware(['throttle:api-read', 'permission:voucher_packages.view'])->group(function () {
            Route::get('voucher-package-discounts', [VoucherPackageDiscountController::class, 'index'])->name('api.v1.voucher_package_discounts.index');
            Route::get('voucher-package-discounts/trashed', [VoucherPackageDiscountController::class, 'trashed'])->name('api.v1.voucher_package_discounts.trashed');
            Route::get('voucher-package-discounts/effective', [VoucherPackageDiscountController::class, 'effectiveDiscount'])->name('api.v1.voucher_package_discounts.effective');
            Route::get('voucher-package-discounts/{id}', [VoucherPackageDiscountController::class, 'show'])->whereNumber('id')->name('api.v1.voucher_package_discounts.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('voucher-package-discounts', [VoucherPackageDiscountController::class, 'store'])->name('api.v1.voucher_package_discounts.store')->middleware('permission:voucher_packages.create');
            Route::put('voucher-package-discounts/{id}', [VoucherPackageDiscountController::class, 'update'])->whereNumber('id')->name('api.v1.voucher_package_discounts.update')->middleware('permission:voucher_packages.edit');
            Route::patch('voucher-package-discounts/{id}', [VoucherPackageDiscountController::class, 'update'])->whereNumber('id')->name('api.v1.voucher_package_discounts.update')->middleware('permission:voucher_packages.edit');
            Route::delete('voucher-package-discounts/{id}', [VoucherPackageDiscountController::class, 'destroy'])->whereNumber('id')->name('api.v1.voucher_package_discounts.destroy')->middleware('permission:voucher_packages.delete');
            Route::post('voucher-package-discounts/batch-delete', [VoucherPackageDiscountController::class, 'batchDelete'])->name('api.v1.voucher_package_discounts.batch_delete')->middleware('permission:voucher_packages.delete');
            Route::post('voucher-package-discounts/batch-restore', [VoucherPackageDiscountController::class, 'batchRestore'])->name('api.v1.voucher_package_discounts.batch_restore')->middleware('permission:voucher_packages.edit');
            Route::post('voucher-package-discounts/batch-force-delete', [VoucherPackageDiscountController::class, 'batchForceDelete'])->name('api.v1.voucher_package_discounts.batch_force_delete')->middleware('permission:voucher_packages.delete');
            Route::post('voucher-package-discounts/{id}/restore', [VoucherPackageDiscountController::class, 'restore'])->whereNumber('id')->name('api.v1.voucher_package_discounts.restore')->middleware('permission:voucher_packages.edit');
            Route::delete('voucher-package-discounts/{id}/force', [VoucherPackageDiscountController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.voucher_package_discounts.force_delete')->middleware('permission:voucher_packages.delete');
            Route::post('voucher-package-discounts/{id}/toggle-status', [VoucherPackageDiscountController::class, 'toggleStatus'])->whereNumber('id')->name('api.v1.voucher_package_discounts.toggle_status')->middleware('permission:voucher_packages.edit');
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
            Route::put('pos-wallets/{id}/credit-limit', [PosWalletController::class, 'updateCreditLimit'])->whereNumber('id')->name('api.v1.pos_wallets.update_credit_limit');
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
        // 9.1 إدارة الديون وسداد الفواتير الآجلة وتحليل الأعمار (Debt Tracking, Repayments & Aging)
        // ==========================================
        Route::middleware(['throttle:api-read'])->group(function () {
            Route::get('pos-debts/summary', [DebtRepaymentController::class, 'summary'])->name('api.v1.pos_debts.summary');
            Route::get('pos-debts/invoices', [DebtRepaymentController::class, 'outstandingInvoices'])->name('api.v1.pos_debts.invoices');
            Route::get('pos-debts/repayments', [DebtRepaymentController::class, 'repayments'])->name('api.v1.pos_debts.repayments');
            Route::get('pos-debts/repayments/{id}', [DebtRepaymentController::class, 'showRepayment'])->whereNumber('id')->name('api.v1.pos_debts.repayment_detail');
        });

        Route::middleware(['throttle:api-write'])->group(function () {
            Route::post('pos-debts/repayments', [DebtRepaymentController::class, 'collect'])->name('api.v1.pos_debts.collect');
            Route::post('pos-debts/repayments/{id}/reverse', [DebtRepaymentController::class, 'reverse'])->whereNumber('id')->name('api.v1.pos_debts.reverse');
        });

        // ==========================================
        // 10. طلبات شراء كروت الإنترنت (Voucher Requests)
        // ==========================================
        Route::middleware(['throttle:api-read', 'permission:requests.view'])->group(function () {
            Route::get('requests', [RequestController::class, 'index'])->name('api.v1.requests.index');
            Route::get('requests/trashed', [RequestController::class, 'trashed'])->name('api.v1.requests.trashed');
            Route::get('requests/{id}', [RequestController::class, 'show'])->whereNumber('id')->name('api.v1.requests.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('requests/quote', [RequestController::class, 'quote'])->name('api.v1.requests.quote')->middleware('permission:requests.create');
            Route::post('requests', [RequestController::class, 'store'])->name('api.v1.requests.store')->middleware('permission:requests.create');
            Route::put('requests/{id}', [RequestController::class, 'update'])->whereNumber('id')->name('api.v1.requests.update')->middleware('permission:requests.edit');
            Route::post('requests/{id}/preview-modification', [RequestController::class, 'previewModification'])->whereNumber('id')->name('api.v1.requests.preview_modification')->middleware('permission:requests.approve');
            Route::post('requests/{id}/approve', [RequestController::class, 'approve'])->whereNumber('id')->name('api.v1.requests.approve')->middleware('permission:requests.approve');
            Route::post('requests/{id}/reject', [RequestController::class, 'reject'])->whereNumber('id')->name('api.v1.requests.reject')->middleware('permission:requests.reject');
            Route::post('requests/{id}/cancel', [RequestController::class, 'cancel'])->whereNumber('id')->name('api.v1.requests.cancel')->middleware('permission:requests.edit');
            Route::delete('requests/{id}', [RequestController::class, 'destroy'])->whereNumber('id')->name('api.v1.requests.destroy')->middleware('permission:requests.delete');
            Route::post('requests/{id}/restore', [RequestController::class, 'restore'])->whereNumber('id')->name('api.v1.requests.restore')->middleware('permission:requests.edit');
            Route::delete('requests/{id}/force', [RequestController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.requests.force_delete')->middleware('permission:requests.delete');
            Route::post('requests/batch-delete', [RequestController::class, 'batchDelete'])->name('api.v1.requests.batch_delete')->middleware('permission:requests.delete');
            Route::post('requests/batch-restore', [RequestController::class, 'batchRestore'])->name('api.v1.requests.batch_restore')->middleware('permission:requests.edit');
            Route::post('requests/batch-force-delete', [RequestController::class, 'batchForceDelete'])->name('api.v1.requests.batch_force_delete')->middleware('permission:requests.delete');
        });

        // ==========================================
        // 11. فواتير مبيعات الكروت (Sale Invoices)
        // ==========================================
        Route::middleware(['throttle:api-read', 'permission:sales.view'])->group(function () {
            Route::get('sale-invoices', [SaleInvoiceController::class, 'index'])->name('api.v1.sale_invoices.index');
            Route::get('sale-invoices/trashed', [SaleInvoiceController::class, 'trashed'])->name('api.v1.sale_invoices.trashed');
            Route::get('sale-invoices/trend', [SaleInvoiceController::class,
                'trend'])->name('api.v1.sale_invoices.trend');
            Route::get('sale-invoices/{id}', [SaleInvoiceController::class, 'show'])->whereNumber('id')->name('api.v1.sale_invoices.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('sale-invoices', [SaleInvoiceController::class, 'store'])->name('api.v1.sale_invoices.store')->middleware('permission:sales.create');
            Route::put('sale-invoices/{id}', [SaleInvoiceController::class, 'update'])->whereNumber('id')->name('api.v1.sale_invoices.update')->middleware('permission:sales.edit');
            Route::delete('sale-invoices/{id}', [SaleInvoiceController::class, 'destroy'])->whereNumber('id')->name('api.v1.sale_invoices.destroy')->middleware('permission:sales.edit');
            Route::post('sale-invoices/{id}/restore', [SaleInvoiceController::class, 'restore'])->whereNumber('id')->name('api.v1.sale_invoices.restore')->middleware('permission:sales.edit');
            Route::delete('sale-invoices/{id}/force', [SaleInvoiceController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.sale_invoices.force_delete')->middleware('permission:sales.edit');
            Route::post('sale-invoices/batch-delete', [SaleInvoiceController::class, 'batchDelete'])->name('api.v1.sale_invoices.batch_delete')->middleware('permission:sales.edit');
            Route::post('sale-invoices/batch-restore', [SaleInvoiceController::class, 'batchRestore'])->name('api.v1.sale_invoices.batch_restore')->middleware('permission:sales.edit');
            Route::post('sale-invoices/batch-force-delete', [SaleInvoiceController::class, 'batchForceDelete'])->name('api.v1.sale_invoices.batch_force_delete')->middleware('permission:sales.edit');
        });

        // ==========================================
        // 12. المصروفات التشغيلية والبنود والمرفقات (Expenses & Operational Disbursals)
        // ==========================================
        Route::middleware(['throttle:api-read', 'permission:expenses.view'])->group(function () {
            Route::get('expenses', [ExpenseController::class, 'index'])->name('api.v1.expenses.index');
            Route::get('expenses/trashed', [ExpenseController::class, 'trashed'])->name('api.v1.expenses.trashed');
            Route::get('expenses/{id}', [ExpenseController::class, 'show'])->whereNumber('id')->name('api.v1.expenses.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('expenses', [ExpenseController::class, 'store'])->name('api.v1.expenses.store')->middleware('permission:expenses.create');
            Route::put('expenses/{id}', [ExpenseController::class, 'update'])->whereNumber('id')->name('api.v1.expenses.update')->middleware('permission:expenses.edit');
            Route::delete('expenses/{id}', [ExpenseController::class, 'destroy'])->whereNumber('id')->name('api.v1.expenses.destroy')->middleware('permission:expenses.delete');
            Route::post('expenses/{id}/restore', [ExpenseController::class, 'restore'])->whereNumber('id')->name('api.v1.expenses.restore')->middleware('permission:expenses.edit');
            Route::delete('expenses/{id}/force', [ExpenseController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.expenses.force_delete')->middleware('permission:expenses.delete');
            Route::post('expenses/batch-delete', [ExpenseController::class, 'batchDelete'])->name('api.v1.expenses.batch_delete')->middleware('permission:expenses.delete');
            Route::post('expenses/batch-restore', [ExpenseController::class, 'batchRestore'])->name('api.v1.expenses.batch_restore')->middleware('permission:expenses.edit');
            Route::post('expenses/batch-force-delete', [ExpenseController::class, 'batchForceDelete'])->name('api.v1.expenses.batch_force_delete')->middleware('permission:expenses.delete');
            Route::post('expenses/{id}/items', [ExpenseController::class, 'addItem'])->whereNumber('id')->name('api.v1.expenses.add_item')->middleware('permission:expenses.edit');
            Route::delete('expenses/{id}/items/{itemId}', [ExpenseController::class, 'deleteItem'])->whereNumber('id')->whereNumber('itemId')->name('api.v1.expenses.delete_item')->middleware('permission:expenses.delete');
            Route::delete('expenses/{id}/attachments/{attachmentId}', [ExpenseController::class, 'deleteAttachment'])->whereNumber('id')->whereNumber('attachmentId')->name('api.v1.expenses.delete_attachment')->middleware('permission:expenses.delete');
            Route::apiResource('expenses', ExpenseController::class)->parameters(['expenses' => 'id'])->except(['index', 'show']);
        });

        // ==========================================
        // Operational reports — Network / POS workspaces only.
        Route::prefix('reports/presets')->middleware(['operational.scope'])->group(function () {
            Route::get('/', [OperationalReportController::class, 'presets'])
                ->middleware('throttle:api-read')->name('api.v1.reports.presets.index');
            Route::post('/', [OperationalReportController::class, 'storePreset'])
                ->middleware('throttle:api-write')->name('api.v1.reports.presets.store');
            Route::put('{presetUuid}', [OperationalReportController::class, 'updatePreset'])
                ->whereUuid('presetUuid')
                ->middleware('throttle:api-write')->name('api.v1.reports.presets.update');
            Route::delete('{presetUuid}', [OperationalReportController::class, 'destroyPreset'])
                ->whereUuid('presetUuid')
                ->middleware('throttle:api-write')->name('api.v1.reports.presets.destroy');
        });

        Route::prefix('reports')->middleware(['throttle:api-read', 'operational.scope'])->group(function () {
            Route::get('catalog', [OperationalReportController::class, 'catalog'])->name('api.v1.reports.catalog');
            Route::get('/', [OperationalReportController::class, 'show'])
                ->name('api.v1.reports.show');
            Route::get('summary', [OperationalReportController::class, 'summary'])
                ->name('api.v1.reports.summary');
        });
        Route::prefix('reports/exports')->middleware(['operational.scope'])->group(function () {
            Route::post('/', [OperationalReportController::class, 'storeExport'])
                ->middleware('throttle:api-write')
                ->name('api.v1.reports.exports.store');
            Route::get('{exportUuid}', [OperationalReportController::class, 'exportStatus'])
                ->middleware('throttle:api-read')
                ->name('api.v1.reports.exports.show');
            Route::get('{exportUuid}/download', [OperationalReportController::class, 'download'])
                ->middleware('throttle:api-read')
                ->name('api.v1.reports.exports.download');
        });

        // Immutable operational document snapshots and issued PDF artifacts.
        Route::prefix('documents/snapshots')->middleware('throttle:api-read')->group(function () {
            Route::post('/', [\App\Http\Controllers\API\V1\OperationalDocumentSnapshotController::class, 'store'])
                ->middleware('throttle:api-write')
                ->name('api.v1.documents.snapshots.store');
            Route::post('{snapshotUuid}/artifact', [\App\Http\Controllers\API\V1\OperationalDocumentSnapshotController::class, 'storeArtifact'])
                ->middleware('throttle:api-write')
                ->name('api.v1.documents.snapshots.artifact');
            Route::get('{snapshotUuid}/download', [\App\Http\Controllers\API\V1\OperationalDocumentSnapshotController::class, 'download'])
                ->name('api.v1.documents.snapshots.download');
        });

        // Asynchronous report runs are isolated by network/platform scope.
        Route::prefix('networks/{networkId}/reports')->whereNumber('networkId')->middleware('operational.scope')->group(function () {
            Route::get('catalog', [ReportController::class, 'networkCatalog'])->middleware('throttle:api-read')->name('api.v1.networks.reports.catalog');
            Route::post('runs', [ReportController::class, 'storeNetworkRun'])->middleware('throttle:api-write')->name('api.v1.networks.reports.runs.store');
            Route::get('runs', [ReportController::class, 'networkRuns'])->middleware('throttle:api-read')->name('api.v1.networks.reports.runs.index');
            Route::get('runs/{reportRunId}', [ReportController::class, 'showNetworkRun'])->whereNumber('reportRunId')->middleware('throttle:api-read')->name('api.v1.networks.reports.runs.show');
        });
        Route::prefix('platform/reports')->middleware('platform.user.scope')->group(function () {
            Route::get('catalog', [ReportController::class, 'platformCatalog'])->middleware(['throttle:api-read', 'permission:billing.report'])->name('api.v1.platform.reports.catalog');
            Route::post('runs', [ReportController::class, 'storePlatformRun'])->middleware(['throttle:api-write', 'permission:billing.report'])->name('api.v1.platform.reports.runs.store');
            Route::get('runs', [ReportController::class, 'platformRuns'])->middleware(['throttle:api-read', 'permission:billing.report'])->name('api.v1.platform.reports.runs.index');
            Route::get('runs/{reportRunId}', [ReportController::class, 'showPlatformRun'])->whereNumber('reportRunId')->middleware(['throttle:api-read', 'permission:billing.report'])->name('api.v1.platform.reports.runs.show');
        });

        // Billing reports are isolated from operational reports and remain currency-aware.
        Route::prefix('platform/billing/reports')->middleware(['platform.user.scope', 'permission:billing.view'])->group(function () {
            Route::get('catalog', [BillingReportController::class, 'platformCatalog'])->middleware('throttle:api-read')->name('api.v1.platform.billing.reports.catalog');
            Route::get('{reportType}', [BillingReportController::class, 'platformShow'])->where('reportType', '[a-z_]+')->middleware('throttle:api-read')->name('api.v1.platform.billing.reports.show');
            Route::get('{reportType}/summary', [BillingReportController::class, 'platformSummary'])->where('reportType', '[a-z_]+')->middleware('throttle:api-read')->name('api.v1.platform.billing.reports.summary');
            Route::get('{reportType}/export.csv', [BillingReportController::class, 'platformExportCsv'])->where('reportType', '[a-z_]+')->middleware('throttle:api-read')->name('api.v1.platform.billing.reports.export');
        });

        // Platform Billing — Windows Platform Scope only
        // ==========================================
        Route::prefix('platform/billing')->middleware('platform.user.scope')->group(function () {
            Route::prefix('policies')->controller(BillingPolicyController::class)->group(function () {
                Route::middleware(['throttle:api-read', 'permission:billing.view'])->group(function () {
                    Route::get('trials', 'trialPolicies')->name('api.v1.platform.billing.policies.trials.index');
                    Route::get('trials/{policyId}/versions', 'trialPolicyVersions')->whereNumber('policyId')->name('api.v1.platform.billing.policies.trials.versions.index');
                    Route::get('dunning', 'dunningPolicies')->name('api.v1.platform.billing.policies.dunning.index');
                    Route::get('dunning/{policyId}/versions', 'dunningPolicyVersions')->whereNumber('policyId')->name('api.v1.platform.billing.policies.dunning.versions.index');
                    Route::get('trial-redemptions/pending', 'pendingRedemptions')->name('api.v1.platform.billing.policies.trials.pending');
                });
                Route::middleware(['throttle:api-write', 'permission:billing.create'])->group(function () {
                Route::post('accounts', [BillingFinanceController::class, 'createAccount'])->name('api.v1.platform.billing.accounts.store');
                Route::post('subscriptions', [BillingFinanceController::class, 'createSubscription'])->name('api.v1.platform.billing.subscriptions.store');
            });

            Route::middleware(['throttle:api-write', 'permission:billing.manage'])->group(function () {
                Route::post('subscriptions/{subscriptionId}/cancel', [BillingFinanceController::class, 'cancelSubscription'])
                    ->whereNumber('subscriptionId')->name('api.v1.platform.billing.subscriptions.cancel');
                    Route::post('trial-redemptions/{redemptionId}/approve', 'approveRedemption')->name('api.v1.platform.billing.policies.trials.approve');
                    Route::post('trial-redemptions/{redemptionId}/reject', 'rejectRedemption')->name('api.v1.platform.billing.policies.trials.reject');
                    Route::post('subscriptions/{subscriptionId}/trial-adjustments', 'adjustTrial')->name('api.v1.platform.billing.policies.trials.adjust');
                    Route::post('trials', 'storeTrial')->name('api.v1.platform.billing.policies.trials.store');
                    Route::post('dunning', 'storeDunning')->name('api.v1.platform.billing.policies.dunning.store');
                    Route::post('trials/{policyId}/versions', 'storeTrialVersion')->name('api.v1.platform.billing.policies.trials.versions.store');
                    Route::post('dunning/{policyId}/versions', 'storeDunningVersion')->name('api.v1.platform.billing.policies.dunning.versions.store');
                    Route::post('trial-versions/{versionId}/publish', 'publishTrial')->name('api.v1.platform.billing.policies.trials.publish');
                    Route::post('dunning-versions/{versionId}/publish', 'publishDunning')->name('api.v1.platform.billing.policies.dunning.publish');
                    Route::put('plan-versions/{versionId}', 'assignPlanVersion')->name('api.v1.platform.billing.policies.plan_versions.assign');
                    Route::put('prices/{priceId}', 'assignPrice')->name('api.v1.platform.billing.policies.prices.assign');
                });
            });

            Route::middleware(['throttle:api-write', 'permission:billing.create'])->group(function () {
                Route::post('accounts', [BillingFinanceController::class, 'createAccount'])->name('api.v1.platform.billing.accounts.store');
                Route::post('subscriptions', [BillingFinanceController::class, 'createSubscription'])->name('api.v1.platform.billing.subscriptions.store');
            });

            Route::middleware(['throttle:api-write', 'permission:billing.manage'])->group(function () {
                Route::post('subscriptions/{subscriptionId}/cancel', [BillingFinanceController::class, 'cancelSubscription'])
                    ->whereNumber('subscriptionId')->name('api.v1.platform.billing.subscriptions.cancel');
            });

            Route::middleware(['throttle:api-read', 'permission:billing.view'])->group(function () {
                Route::get('currencies', [BillingCatalogController::class, 'currencies'])->name('api.v1.platform.billing.currencies.index');
                Route::get('overview', [BillingFinanceController::class, 'overview'])->name('api.v1.platform.billing.overview');
                Route::get('reconciliation', [BillingFinanceController::class, 'reconciliation'])->name('api.v1.platform.billing.reconciliation');
                Route::get('plans', [BillingCatalogController::class, 'plans'])->name('api.v1.platform.billing.plans.index');
                Route::get('plans/{planId}/versions', [BillingCatalogController::class, 'versions'])->whereNumber('planId')->name('api.v1.platform.billing.plans.versions.index');
                Route::get('products', [BillingCatalogController::class, 'products'])->name('api.v1.platform.billing.products.index');

                Route::get('accounts', [BillingFinanceController::class, 'accounts'])->name('api.v1.platform.billing.accounts.index');
                Route::get('subscriptions', [BillingFinanceController::class, 'subscriptions'])->name('api.v1.platform.billing.subscriptions.index');
                Route::get('subscriptions/{subscriptionId}', [BillingFinanceController::class, 'subscriptionDetails'])
                    ->whereNumber('subscriptionId')->name('api.v1.platform.billing.subscriptions.show');
                Route::get('invoices', [BillingFinanceController::class, 'invoices'])->name('api.v1.platform.billing.invoices.index');
                Route::get('payments', [BillingFinanceController::class, 'payments'])->name('api.v1.platform.billing.payments.index');
                Route::get('payment-methods', [BillingFinanceController::class, 'paymentMethods'])->name('api.v1.platform.billing.payment_methods.index');
                Route::get('treasury-accounts', [BillingFinanceController::class, 'treasuryAccounts'])->name('api.v1.platform.billing.treasury_accounts.index');
                Route::get('journal-entries', [BillingFinanceController::class, 'journalEntries'])->name('api.v1.platform.billing.journal_entries.index');
                Route::get('exchange-rates', [BillingFinanceController::class, 'exchangeRates'])->name('api.v1.platform.billing.exchange_rates.index');
                Route::get('conversions', [BillingFinanceController::class, 'conversions'])->name('api.v1.platform.billing.conversions.index');
            });

            Route::middleware(['throttle:api-write', 'permission:billing.manage'])->group(function () {
                Route::post('currencies', [BillingCatalogController::class, 'storeCurrency'])->name('api.v1.platform.billing.currencies.store');
                Route::put('currencies/{currencyId}', [BillingCatalogController::class, 'updateCurrency'])
                    ->whereNumber('currencyId')->name('api.v1.platform.billing.currencies.update');
                Route::post('treasury-accounts', [BillingFinanceController::class, 'storeTreasuryAccount'])->name('api.v1.platform.billing.treasury_accounts.store');
                Route::patch('treasury-accounts/{treasuryAccountId}/status', [BillingFinanceController::class, 'setTreasuryStatus'])
                    ->whereNumber('treasuryAccountId')->name('api.v1.platform.billing.treasury_accounts.status');
                Route::post('exchange-rates', [BillingFinanceController::class, 'storeExchangeRate'])->name('api.v1.platform.billing.exchange_rates.store');
                Route::post('conversions', [BillingFinanceController::class, 'convertCurrency'])->name('api.v1.platform.billing.conversions.store');
            });

            Route::middleware(['throttle:api-write', 'permission:billing.create'])->group(function () {
                Route::post('products', [BillingCatalogController::class, 'storeProduct'])->name('api.v1.platform.billing.products.store');
                Route::post('plans', [BillingCatalogController::class, 'storePlan'])->name('api.v1.platform.billing.plans.store');
            });

            Route::middleware(['throttle:api-write', 'permission:billing.edit'])->group(function () {
                Route::post('plans/{planId}/versions', [BillingCatalogController::class, 'createDraftVersion'])
                    ->whereNumber('planId')->name('api.v1.platform.billing.plan_versions.store');
                Route::post('plan-versions/{versionId}/prices', [BillingCatalogController::class, 'addPrice'])
                    ->whereNumber('versionId')->name('api.v1.platform.billing.prices.store');
                Route::post('plan-versions/{versionId}/features', [BillingCatalogController::class, 'addFeature'])
                    ->whereNumber('versionId')->name('api.v1.platform.billing.features.store');
            });

            Route::post('plan-versions/{versionId}/publish', [BillingCatalogController::class, 'publishVersion'])
                ->whereNumber('versionId')
                ->middleware(['throttle:api-write', 'permission:billing.manage'])
                ->name('api.v1.platform.billing.plan_versions.publish');

            Route::middleware(['throttle:api-write', 'permission:billing.approve'])->group(function () {
                Route::post('payments/{paymentId}/verify', [BillingFinanceController::class, 'verifyPayment'])
                    ->whereNumber('paymentId')->name('api.v1.platform.billing.payments.verify');
                Route::post('payments/{paymentId}/reject', [BillingFinanceController::class, 'rejectPayment'])
                    ->whereNumber('paymentId')->name('api.v1.platform.billing.payments.reject');
                Route::post('invoices/{invoiceId}/credit-notes', [BillingFinanceController::class, 'issueCreditNote'])
                    ->whereNumber('invoiceId')->name('api.v1.platform.billing.invoices.credit_notes.store');
                Route::post('payments/{paymentId}/refunds', [BillingFinanceController::class, 'refundPayment'])
                    ->whereNumber('paymentId')->name('api.v1.platform.billing.payments.refunds.store');
                Route::post('invoices/{invoiceId}/void', [BillingFinanceController::class, 'voidInvoice'])
                    ->whereNumber('invoiceId')->name('api.v1.platform.billing.invoices.void');
            });
        });

        // ==========================================
        Route::prefix('networks/{networkId}/billing/reports')->middleware(['operational.scope'])->group(function () {
            Route::get('catalog', [BillingReportController::class, 'networkCatalog'])->whereNumber('networkId')->middleware('throttle:api-read')->name('api.v1.networks.billing.reports.catalog');
            Route::get('{reportType}', [BillingReportController::class, 'networkShow'])->whereNumber('networkId')->where('reportType', '[a-z_]+')->middleware('throttle:api-read')->name('api.v1.networks.billing.reports.show');
            Route::get('{reportType}/summary', [BillingReportController::class, 'networkSummary'])->whereNumber('networkId')->where('reportType', '[a-z_]+')->middleware('throttle:api-read')->name('api.v1.networks.billing.reports.summary');
            Route::get('{reportType}/export.csv', [BillingReportController::class, 'networkExportCsv'])->whereNumber('networkId')->where('reportType', '[a-z_]+')->middleware('throttle:api-read')->name('api.v1.networks.billing.reports.export');
        });

        // Network Billing Self-Service — Network Owner only
        // ==========================================
        Route::prefix('networks/{networkId}/billing')->middleware(['operational.scope'])->group(function () {
            Route::get('accounts', [BillingSelfServiceController::class, 'accounts'])
                ->whereNumber('networkId')->middleware('throttle:api-read')
                ->name('api.v1.networks.billing.accounts.index');
            Route::post('accounts', [BillingSelfServiceController::class, 'createAccount'])
                ->whereNumber('networkId')->middleware('throttle:api-write')
                ->name('api.v1.networks.billing.accounts.store');
        });

        Route::prefix('billing')->middleware('operational.scope')->group(function () {
            Route::middleware('throttle:api-read')->group(function () {
                Route::get('currencies', [BillingSelfServiceController::class, 'currencies'])
                    ->name('api.v1.billing.currencies.index');
                Route::get('accounts/{billingAccountId}/prices/{priceId}/trial-eligibility', [BillingTrialEligibilityController::class, 'show'])
                    ->whereNumber('billingAccountId')->whereNumber('priceId')
                    ->name('api.v1.billing.accounts.prices.trial_eligibility');
                Route::get('accounts/{billingAccountId}', [BillingSelfServiceController::class, 'account'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.show');
                Route::get('accounts/{billingAccountId}/entitlements', [BillingSelfServiceController::class, 'entitlements'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.entitlements');
                Route::get('accounts/{billingAccountId}/plans', [BillingSelfServiceController::class, 'plans'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.plans');
                Route::get('accounts/{billingAccountId}/subscriptions', [BillingSelfServiceController::class, 'subscriptions'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.subscriptions.index');
                Route::get('accounts/{billingAccountId}/invoices', [BillingSelfServiceController::class, 'invoices'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.invoices.index');
                Route::get('accounts/{billingAccountId}/statement', [BillingSelfServiceController::class, 'statement'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.statement');
                Route::get('accounts/{billingAccountId}/payments', [BillingSelfServiceController::class, 'payments'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.payments.index');
                Route::get('accounts/{billingAccountId}/credit-notes', [BillingSelfServiceController::class, 'creditNotes'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.credit_notes.index');
                Route::get('accounts/{billingAccountId}/refunds', [BillingSelfServiceController::class, 'refunds'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.refunds.index');
                Route::get('subscriptions/{subscriptionId}', [BillingSelfServiceController::class, 'subscription'])
                    ->whereNumber('subscriptionId')->name('api.v1.billing.subscriptions.show');
                Route::get('subscriptions/{subscriptionId}/usage', [BillingSelfServiceController::class, 'usage'])
                    ->whereNumber('subscriptionId')->name('api.v1.billing.subscriptions.usage');
                Route::get('subscriptions/{subscriptionId}/events', [BillingSelfServiceController::class, 'subscriptionEvents'])
                    ->whereNumber('subscriptionId')->name('api.v1.billing.subscriptions.events.index');
                Route::get('accounts/{billingAccountId}/payment-methods', [BillingSelfServiceController::class, 'paymentMethods'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.payment_methods');
                Route::get('invoices/{invoiceId}', [BillingSelfServiceController::class, 'invoice'])
                    ->whereNumber('invoiceId')->name('api.v1.billing.invoices.show');
            });

            Route::middleware('throttle:api-write')->group(function () {
                Route::put('accounts/{billingAccountId}', [BillingSelfServiceController::class, 'updateAccount'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.update');
                Route::post('accounts/{billingAccountId}/contacts', [BillingSelfServiceController::class, 'storeContact'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.contacts.store');
                Route::put('accounts/{billingAccountId}/contacts/{contactId}', [BillingSelfServiceController::class, 'updateContact'])
                    ->whereNumber('billingAccountId')->whereNumber('contactId')->name('api.v1.billing.accounts.contacts.update');
                Route::delete('accounts/{billingAccountId}/contacts/{contactId}', [BillingSelfServiceController::class, 'destroyContact'])
                    ->whereNumber('billingAccountId')->whereNumber('contactId')->name('api.v1.billing.accounts.contacts.destroy');
                Route::post('accounts/{billingAccountId}/subscriptions', [BillingSelfServiceController::class, 'purchase'])
                    ->whereNumber('billingAccountId')->name('api.v1.billing.accounts.subscriptions.purchase');
                Route::post('subscriptions/{subscriptionId}/cancel', [BillingSelfServiceController::class, 'cancelSubscription'])
                    ->whereNumber('subscriptionId')->name('api.v1.billing.subscriptions.cancel');
            });

            Route::post('invoices/{invoiceId}/payments', [BillingSelfServiceController::class, 'storePayment'])
                ->whereNumber('invoiceId')
                ->middleware(['throttle:api-upload', 'throttle:api-write'])
                ->name('api.v1.billing.invoices.payments.store');
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
