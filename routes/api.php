<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\NetworkController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\UserController;
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
            Route::apiResource('users', UserController::class)->except(['index', 'show']);
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
            Route::apiResource('networks', NetworkController::class)->except(['index', 'show']);
        });

    });

});
