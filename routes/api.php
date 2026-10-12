<?php

use App\Http\Controllers\Api\V1\AcademicController;
use App\Http\Controllers\Api\V1\ActionTypeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CenterController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\GeographyController;
use App\Http\Controllers\Api\V1\GradingScaleController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ResultController;
use App\Http\Controllers\Api\V1\ResultRuleController;
use App\Http\Controllers\Api\V1\ResultTemplateController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SecurityController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NATIJATY API Routes — Version 1
|--------------------------------------------------------------------------
|
| جميع مسارات واجهة التطبيق البرمجية لنظام نتيجتي مبوبة ومحمية بنظام
| (Throttles).
|
*/

Route::prefix('v1')->group(function () {

    // ==========================================
    // المصادقة والجلسات (Authentication & Profile)
    // ==========================================
    Route::prefix('auth')->group(function () {

        Route::middleware('throttle:auth')->group(function () {
            Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');
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
    // البيانات المرجعية العامة (Public Reference Read Routes)
    // ==========================================
    Route::middleware('throttle:api-public')->group(function () {
        // Roles & Permissions Reference
        Route::get('roles', [RoleController::class, 'index'])->name('api.v1.roles.index');
        Route::get('roles/{id}', [RoleController::class, 'show'])->whereNumber('id')->name('api.v1.roles.show');
        Route::get('permissions', [PermissionController::class, 'index'])->name('api.v1.permissions.index');
        Route::get('permissions/{id}', [PermissionController::class, 'show'])->whereNumber('id')->name('api.v1.permissions.show');
        Route::get('permissions/modules', [PermissionController::class, 'modules'])->name('api.v1.permissions.modules');
        Route::get('permission-modules', [PermissionController::class, 'modules'])->name('api.v1.permission_modules');

        // RBAC Modules & Action Types Reference
        Route::get('modules', [ModuleController::class, 'index'])->name('api.v1.modules.index');
        Route::get('modules/{id}', [ModuleController::class, 'show'])->whereNumber('id')->name('api.v1.modules.show');
        Route::get('action-types', [ActionTypeController::class, 'index'])->name('api.v1.action_types.index');
        Route::get('action-types/{id}', [ActionTypeController::class, 'show'])->whereNumber('id')->name('api.v1.action_types.show');

        // Geography Reference
        Route::get('governorates', [GeographyController::class, 'governorates'])->name('api.v1.geography.governorates');
        Route::get('governorates/{id}', [GeographyController::class, 'governorate'])->whereNumber('id')->name('api.v1.geography.governorate');
        Route::get('directorates', [GeographyController::class, 'directorates'])->name('api.v1.geography.directorates');
        Route::get('directorates/{id}', [GeographyController::class, 'directorate'])->whereNumber('id')->name('api.v1.geography.directorate');

        // Settings, Security Public Keys & Results Public Reference
        Route::get('settings/public', [SettingController::class, 'publicSettings'])->name('api.v1.settings.public');
        Route::get('security/public-keys', [SecurityController::class, 'getPublicKeys'])->name('api.v1.security.public_keys');
        Route::post('results/lookup', [ResultController::class, 'lookup'])->name('api.v1.results.lookup');
        Route::post('results/verify-qr', [ResultController::class, 'verifyQr'])->name('api.v1.results.verify_qr');
        Route::get('results/verify/{uuid}', [ResultController::class, 'verify'])->name('api.v1.results.verify');

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
        // 2. إدارة الأدوار والصلاحيات (Roles & Permissions Mutations)
        // ==========================================
        Route::middleware('throttle:api-write')->group(function () {
            Route::get('roles/trashed', [RoleController::class, 'trashed'])->name('api.v1.roles.trashed');
            Route::post('roles/batch-delete', [RoleController::class, 'batchDelete'])->name('api.v1.roles.batch_delete');
            Route::post('roles/batch-restore', [RoleController::class, 'batchRestore'])->name('api.v1.roles.batch_restore');
            Route::post('roles/batch-force-delete', [RoleController::class, 'batchForceDelete'])->name('api.v1.roles.batch_force_delete');
            Route::post('roles/{id}/restore', [RoleController::class, 'restore'])->name('api.v1.roles.restore');
            Route::delete('roles/{id}/force', [RoleController::class, 'forceDelete'])->name('api.v1.roles.force_delete');
            Route::post('roles/{id}/permissions', [RoleController::class, 'syncPermissions'])->name('api.v1.roles.sync_permissions');
            Route::apiResource('roles', RoleController::class)->except(['index', 'show']);

            Route::get('permissions/trashed', [PermissionController::class, 'trashed'])->name('api.v1.permissions.trashed');
            Route::post('permissions/batch-delete', [PermissionController::class, 'batchDelete'])->name('api.v1.permissions.batch_delete');
            Route::post('permissions/batch-restore', [PermissionController::class, 'batchRestore'])->name('api.v1.permissions.batch_restore');
            Route::post('permissions/batch-force-delete', [PermissionController::class, 'batchForceDelete'])->name('api.v1.permissions.batch_force_delete');
            Route::post('permissions/{id}/restore', [PermissionController::class, 'restore'])->name('api.v1.permissions.restore');
            Route::delete('permissions/{id}/force', [PermissionController::class, 'forceDelete'])->name('api.v1.permissions.force_delete');
            Route::apiResource('permissions', PermissionController::class)->except(['index', 'show']);
        });

        // ==========================================
        // 3. إدارة الأمان والجلسات والحظر (Security & Sessions)
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

        // Top-level Security aliases for seamless backward compatibility
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
        // 4. إدارة الوحدات وأنواع الإجراءات (RBAC Modules & Action Types)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('modules/trashed', [ModuleController::class, 'trashed'])->name('api.v1.modules.trashed');
            Route::get('modules/{moduleId}/permissions', [PermissionController::class, 'byModule'])->name('api.v1.modules.permissions');
            Route::get('action-types/trashed', [ActionTypeController::class, 'trashed'])->name('api.v1.action_types.trashed');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('modules/batch-delete', [ModuleController::class, 'batchDelete'])->name('api.v1.modules.batch_delete');
            Route::post('modules/batch-restore', [ModuleController::class, 'batchRestore'])->name('api.v1.modules.batch_restore');
            Route::post('modules/batch-force-delete', [ModuleController::class, 'batchForceDelete'])->name('api.v1.modules.batch_force_delete');
            Route::post('modules/{id}/restore', [ModuleController::class, 'restore'])->name('api.v1.modules.restore');
            Route::delete('modules/{id}/force', [ModuleController::class, 'forceDelete'])->name('api.v1.modules.force_delete');
            Route::apiResource('modules', ModuleController::class)->except(['index', 'show']);

            Route::post('action-types/batch-delete', [ActionTypeController::class, 'batchDelete'])->name('api.v1.action_types.batch_delete');
            Route::post('action-types/batch-restore', [ActionTypeController::class, 'batchRestore'])->name('api.v1.action_types.batch_restore');
            Route::post('action-types/batch-force-delete', [ActionTypeController::class, 'batchForceDelete'])->name('api.v1.action_types.batch_force_delete');
            Route::post('action-types/{id}/restore', [ActionTypeController::class, 'restore'])->name('api.v1.action_types.restore');
            Route::delete('action-types/{id}/force', [ActionTypeController::class, 'forceDelete'])->name('api.v1.action_types.force_delete');
            Route::apiResource('action-types', ActionTypeController::class)->except(['index', 'show']);
        });

        // ==========================================
        // 5. إدارة مراكز الخدمة والطباعة (Centers Management)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('centers', [CenterController::class, 'index'])->name('api.v1.centers.index');
            Route::get('centers/trashed', [CenterController::class, 'trashed'])->name('api.v1.centers.trashed');
            Route::get('centers/{id}', [CenterController::class, 'show'])->whereNumber('id')->name('api.v1.centers.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('centers/batch-delete', [CenterController::class, 'batchDelete'])->name('api.v1.centers.batch_delete');
            Route::post('centers/batch-restore', [CenterController::class, 'batchRestore'])->name('api.v1.centers.batch_restore');
            Route::post('centers/batch-force-delete', [CenterController::class, 'batchForceDelete'])->name('api.v1.centers.batch_force_delete');
            Route::post('centers/{id}/restore', [CenterController::class, 'restore'])->name('api.v1.centers.restore');
            Route::delete('centers/{id}/force', [CenterController::class, 'forceDelete'])->name('api.v1.centers.force_delete');
            Route::post('centers/{id}/approve', [CenterController::class, 'approve'])->whereNumber('id')->name('api.v1.centers.approve');
            Route::post('centers/{id}/reject', [CenterController::class, 'reject'])->whereNumber('id')->name('api.v1.centers.reject');
            Route::apiResource('centers', CenterController::class)->except(['index', 'show']);
        });

        // ==========================================
        // 6. الهيكل الأكاديمي والصفوف والمناهج (Academic Structure)
        // ==========================================
        Route::prefix('academic')->group(function () {
            Route::middleware('throttle:api-read')->group(function () {
                // Classes & Sections
                Route::get('classes', [AcademicController::class, 'classes'])->name('api.v1.academic.classes');
                Route::get('classes/{id}', [AcademicController::class, 'class'])->whereNumber('id')->name('api.v1.academic.class');
                Route::get('sections', [AcademicController::class, 'sections'])->name('api.v1.academic.sections');
                Route::get('sections/{id}', [AcademicController::class, 'section'])->whereNumber('id')->name('api.v1.academic.section');

                // Subjects
                Route::get('subjects', [AcademicController::class, 'subjects'])->name('api.v1.academic.subjects');
                Route::get('subjects/{id}', [AcademicController::class, 'subject'])->whereNumber('id')->name('api.v1.academic.subject');
                Route::get('subjects/trashed', [AcademicController::class, 'trashedSubjects'])->name('api.v1.academic.subjects.trashed');

                // Class Subjects (Curriculum)
                Route::get('class-subjects', [AcademicController::class, 'classSubjects'])->name('api.v1.academic.class_subjects');
                Route::get('class-subjects/trashed', [AcademicController::class, 'trashedClassSubjects'])->name('api.v1.academic.class_subjects.trashed');
                Route::get('class-subjects/{id}', [AcademicController::class, 'classSubject'])->whereNumber('id')->name('api.v1.academic.class_subject');
                Route::get('classes/{classId}/sections/{sectionId}/subjects', [AcademicController::class, 'byClassAndSection'])->name('api.v1.academic.by_class_and_section');
            });

            Route::middleware('throttle:api-write')->group(function () {
                // Subjects CRUD & Soft Delete Mutations
                Route::post('subjects', [AcademicController::class, 'storeSubject'])->name('api.v1.academic.subjects.store');
                Route::put('subjects/{id}', [AcademicController::class, 'updateSubject'])->name('api.v1.academic.subjects.update');
                Route::delete('subjects/{id}', [AcademicController::class, 'destroySubject'])->name('api.v1.academic.subjects.destroy');
                Route::post('subjects/batch-delete', [AcademicController::class, 'batchDeleteSubjects'])->name('api.v1.academic.subjects.batch_delete');
                Route::post('subjects/batch-restore', [AcademicController::class, 'batchRestoreSubjects'])->name('api.v1.academic.subjects.batch_restore');
                Route::post('subjects/batch-force-delete', [AcademicController::class, 'batchForceDeleteSubjects'])->name('api.v1.academic.subjects.batch_force_delete');
                Route::post('subjects/{id}/restore', [AcademicController::class, 'restoreSubject'])->name('api.v1.academic.subjects.restore');
                Route::delete('subjects/{id}/force', [AcademicController::class, 'forceDeleteSubject'])->name('api.v1.academic.subjects.force_delete');

                // Class Subjects Mutations & Batch
                Route::post('class-subjects', [AcademicController::class, 'storeClassSubject'])->name('api.v1.academic.class_subjects.store');
                Route::put('class-subjects/{id}', [AcademicController::class, 'updateClassSubject'])->name('api.v1.academic.class_subjects.update');
                Route::delete('class-subjects/{id}', [AcademicController::class, 'destroyClassSubject'])->name('api.v1.academic.class_subjects.destroy');
                Route::post('class-subjects/batch-delete', [AcademicController::class, 'batchDeleteClassSubjects'])->name('api.v1.academic.class_subjects.batch_delete');
                Route::post('class-subjects/batch-restore', [AcademicController::class, 'batchRestoreClassSubjects'])->name('api.v1.academic.class_subjects.batch_restore');
                Route::post('class-subjects/batch-force-delete', [AcademicController::class, 'batchForceDeleteClassSubjects'])->name('api.v1.academic.class_subjects.batch_force_delete');
                Route::post('class-subjects/{id}/restore', [AcademicController::class, 'restoreClassSubject'])->name('api.v1.academic.class_subjects.restore');
                Route::delete('class-subjects/{id}/force', [AcademicController::class, 'forceDeleteClassSubject'])->name('api.v1.academic.class_subjects.force_delete');
            });
        });

        // ==========================================
        // 7. مقاييس التقديرات وقواعد وقوالب النتائج (Grading & Result Rules)
        // ==========================================
        Route::prefix('grading')->group(function () {
            Route::middleware('throttle:api-read')->group(function () {
                Route::get('scales', [GradingScaleController::class, 'index'])->name('api.v1.grading.scales.index');
                Route::get('scales/trashed', [GradingScaleController::class, 'trashed'])->name('api.v1.grading.scales.trashed');
                Route::get('scales/{id}', [GradingScaleController::class, 'show'])->whereNumber('id')->name('api.v1.grading.scales.show');

                Route::get('rules', [ResultRuleController::class, 'index'])->name('api.v1.grading.rules.index');
                Route::get('rules/trashed', [ResultRuleController::class, 'trashed'])->name('api.v1.grading.rules.trashed');
                Route::get('rules/{id}', [ResultRuleController::class, 'show'])->whereNumber('id')->name('api.v1.grading.rules.show');

                Route::get('templates', [ResultTemplateController::class, 'index'])->name('api.v1.grading.templates.index');
                Route::get('templates/trashed', [ResultTemplateController::class, 'trashed'])->name('api.v1.grading.templates.trashed');
                Route::get('templates/{id}', [ResultTemplateController::class, 'show'])->whereNumber('id')->name('api.v1.grading.templates.show');
            });

            Route::middleware('throttle:api-write')->group(function () {
                // Scales CRUD & Batch
                Route::post('scales', [GradingScaleController::class, 'store'])->name('api.v1.grading.scales.store');
                Route::put('scales/{id}', [GradingScaleController::class, 'update'])->name('api.v1.grading.scales.update');
                Route::delete('scales/{id}', [GradingScaleController::class, 'destroy'])->name('api.v1.grading.scales.destroy');
                Route::post('scales/batch-delete', [GradingScaleController::class, 'batchDelete'])->name('api.v1.grading.scales.batch_delete');
                Route::post('scales/batch-restore', [GradingScaleController::class, 'batchRestore'])->name('api.v1.grading.scales.batch_restore');
                Route::post('scales/batch-force-delete', [GradingScaleController::class, 'batchForceDelete'])->name('api.v1.grading.scales.batch_force_delete');
                Route::post('scales/{id}/restore', [GradingScaleController::class, 'restore'])->name('api.v1.grading.scales.restore');
                Route::delete('scales/{id}/force', [GradingScaleController::class, 'forceDelete'])->name('api.v1.grading.scales.force_delete');

                // Scale Items
                Route::post('scales/{scaleId}/items', [GradingScaleController::class, 'storeItem'])->name('api.v1.grading.scales.items.store');
                Route::put('scales/items/{itemId}', [GradingScaleController::class, 'updateItem'])->name('api.v1.grading.scales.items.update');
                Route::delete('scales/items/{itemId}', [GradingScaleController::class, 'destroyItem'])->name('api.v1.grading.scales.items.destroy');
                Route::post('scales/items/{itemId}/restore', [GradingScaleController::class, 'restoreItem'])->name('api.v1.grading.scales.items.restore');
                Route::delete('scales/items/{itemId}/force', [GradingScaleController::class, 'forceDeleteItem'])->name('api.v1.grading.scales.items.force_delete');
                Route::post('scales/items/batch-delete', [GradingScaleController::class, 'batchDeleteItems'])->name('api.v1.grading.scales.items.batch_delete');
                Route::post('scales/items/batch-restore', [GradingScaleController::class, 'batchRestoreItems'])->name('api.v1.grading.scales.items.batch_restore');
                Route::post('scales/items/batch-force-delete', [GradingScaleController::class, 'batchForceDeleteItems'])->name('api.v1.grading.scales.items.batch_force_delete');

                // Rules CRUD & Batch
                Route::post('rules', [ResultRuleController::class, 'store'])->name('api.v1.grading.rules.store');
                Route::put('rules/{id}', [ResultRuleController::class, 'update'])->name('api.v1.grading.rules.update');
                Route::delete('rules/{id}', [ResultRuleController::class, 'destroy'])->name('api.v1.grading.rules.destroy');
                Route::post('rules/batch-delete', [ResultRuleController::class, 'batchDelete'])->name('api.v1.grading.rules.batch_delete');
                Route::post('rules/batch-restore', [ResultRuleController::class, 'batchRestore'])->name('api.v1.grading.rules.batch_restore');
                Route::post('rules/batch-force-delete', [ResultRuleController::class, 'batchForceDelete'])->name('api.v1.grading.rules.batch_force_delete');
                Route::post('rules/{id}/restore', [ResultRuleController::class, 'restore'])->name('api.v1.grading.rules.restore');
                Route::delete('rules/{id}/force', [ResultRuleController::class, 'forceDelete'])->name('api.v1.grading.rules.force_delete');

                // Templates CRUD & Batch
                Route::post('templates', [ResultTemplateController::class, 'store'])->name('api.v1.grading.templates.store');
                Route::put('templates/{id}', [ResultTemplateController::class, 'update'])->name('api.v1.grading.templates.update');
                Route::delete('templates/{id}', [ResultTemplateController::class, 'destroy'])->name('api.v1.grading.templates.destroy');
                Route::post('templates/batch-delete', [ResultTemplateController::class, 'batchDelete'])->name('api.v1.grading.templates.batch_delete');
                Route::post('templates/batch-restore', [ResultTemplateController::class, 'batchRestore'])->name('api.v1.grading.templates.batch_restore');
                Route::post('templates/batch-force-delete', [ResultTemplateController::class, 'batchForceDelete'])->name('api.v1.grading.templates.batch_force_delete');
                Route::post('templates/{id}/restore', [ResultTemplateController::class, 'restore'])->name('api.v1.grading.templates.restore');
                Route::delete('templates/{id}/force', [ResultTemplateController::class, 'forceDelete'])->name('api.v1.grading.templates.force_delete');
            });
        });

        // Top-level aliases for grading-scales, result-rules, and result-templates
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('grading-scales', [GradingScaleController::class, 'index']);
            Route::get('grading-scales/trashed', [GradingScaleController::class, 'trashed']);
            Route::get('grading-scales/{id}', [GradingScaleController::class, 'show'])->whereNumber('id');

            Route::get('result-rules', [ResultRuleController::class, 'index']);
            Route::get('result-rules/trashed', [ResultRuleController::class, 'trashed']);
            Route::get('result-rules/{id}', [ResultRuleController::class, 'show'])->whereNumber('id');

            Route::get('result-templates', [ResultTemplateController::class, 'index']);
            Route::get('result-templates/trashed', [ResultTemplateController::class, 'trashed']);
            Route::get('result-templates/{id}', [ResultTemplateController::class, 'show'])->whereNumber('id');
        });

        Route::middleware('throttle:api-write')->group(function () {
            // Scales
            Route::post('grading-scales', [GradingScaleController::class, 'store']);
            Route::put('grading-scales/{id}', [GradingScaleController::class, 'update']);
            Route::delete('grading-scales/{id}', [GradingScaleController::class, 'destroy']);
            Route::post('grading-scales/batch-delete', [GradingScaleController::class, 'batchDelete']);
            Route::post('grading-scales/batch-restore', [GradingScaleController::class, 'batchRestore']);
            Route::post('grading-scales/batch-force-delete', [GradingScaleController::class, 'batchForceDelete']);
            Route::post('grading-scales/{id}/restore', [GradingScaleController::class, 'restore']);
            Route::delete('grading-scales/{id}/force', [GradingScaleController::class, 'forceDelete']);
            Route::post('grading-scales/{scaleId}/items', [GradingScaleController::class, 'storeItem']);
            Route::put('grading-scales/items/{itemId}', [GradingScaleController::class, 'updateItem']);
            Route::delete('grading-scales/items/{itemId}', [GradingScaleController::class, 'destroyItem']);
            Route::post('grading-scales/items/{itemId}/restore', [GradingScaleController::class, 'restoreItem']);
            Route::delete('grading-scales/items/{itemId}/force', [GradingScaleController::class, 'forceDeleteItem']);
            Route::post('grading-scales/items/batch-delete', [GradingScaleController::class, 'batchDeleteItems']);
            Route::post('grading-scales/items/batch-restore', [GradingScaleController::class, 'batchRestoreItems']);
            Route::post('grading-scales/items/batch-force-delete', [GradingScaleController::class, 'batchForceDeleteItems']);

            // Rules
            Route::post('result-rules', [ResultRuleController::class, 'store']);
            Route::put('result-rules/{id}', [ResultRuleController::class, 'update']);
            Route::delete('result-rules/{id}', [ResultRuleController::class, 'destroy']);
            Route::post('result-rules/batch-delete', [ResultRuleController::class, 'batchDelete']);
            Route::post('result-rules/batch-restore', [ResultRuleController::class, 'batchRestore']);
            Route::post('result-rules/batch-force-delete', [ResultRuleController::class, 'batchForceDelete']);
            Route::post('result-rules/{id}/restore', [ResultRuleController::class, 'restore']);
            Route::delete('result-rules/{id}/force', [ResultRuleController::class, 'forceDelete']);

            // Templates
            Route::post('result-templates', [ResultTemplateController::class, 'store']);
            Route::put('result-templates/{id}', [ResultTemplateController::class, 'update']);
            Route::delete('result-templates/{id}', [ResultTemplateController::class, 'destroy']);
            Route::post('result-templates/batch-delete', [ResultTemplateController::class, 'batchDelete']);
            Route::post('result-templates/batch-restore', [ResultTemplateController::class, 'batchRestore']);
            Route::post('result-templates/batch-force-delete', [ResultTemplateController::class, 'batchForceDelete']);
            Route::post('result-templates/{id}/restore', [ResultTemplateController::class, 'restore']);
            Route::delete('result-templates/{id}/force', [ResultTemplateController::class, 'forceDelete']);
        });

        // ==========================================
        // 5. إدارة النتائج (Results Management)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('results', [ResultController::class, 'index'])->name('api.v1.results.index');
            Route::get('results/trashed', [ResultController::class, 'trashed'])->name('api.v1.results.trashed');
            Route::get('results/{id}', [ResultController::class, 'show'])->whereNumber('id')->name('api.v1.results.show');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('results', [ResultController::class, 'store'])->name('api.v1.results.store');
            Route::put('results/{id}', [ResultController::class, 'update'])->whereNumber('id')->name('api.v1.results.update');
            Route::delete('results/{id}', [ResultController::class, 'destroy'])->whereNumber('id')->name('api.v1.results.destroy');
            Route::post('results/{id}/restore', [ResultController::class, 'restore'])->whereNumber('id')->name('api.v1.results.restore');
            Route::delete('results/{id}/force', [ResultController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.results.force_delete');
            Route::post('results/batch-delete', [ResultController::class, 'batchDelete'])->name('api.v1.results.batch_delete');
            Route::post('results/batch-restore', [ResultController::class, 'batchRestore'])->name('api.v1.results.batch_restore');
            Route::post('results/batch-force-delete', [ResultController::class, 'batchForceDelete'])->name('api.v1.results.batch_force_delete');
        });

        // ==========================================
        // 6. إدارة الإشعارات (Notifications Management)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
            Route::get('notifications/trashed', [NotificationController::class, 'trashed'])->name('api.v1.notifications.trashed');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::post('notifications', [NotificationController::class, 'store'])->name('api.v1.notifications.store');
            Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->whereNumber('id')->name('api.v1.notifications.mark_read');
            Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.v1.notifications.read_all');
            Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->whereNumber('id')->name('api.v1.notifications.destroy');
            Route::post('notifications/{id}/restore', [NotificationController::class, 'restore'])->whereNumber('id')->name('api.v1.notifications.restore');
            Route::delete('notifications/{id}/force', [NotificationController::class, 'forceDelete'])->whereNumber('id')->name('api.v1.notifications.force_delete');
            Route::post('notifications/batch-delete', [NotificationController::class, 'batchDelete'])->name('api.v1.notifications.batch_delete');
            Route::post('notifications/batch-restore', [NotificationController::class, 'batchRestore'])->name('api.v1.notifications.batch_restore');
            Route::post('notifications/batch-force-delete', [NotificationController::class, 'batchForceDelete'])->name('api.v1.notifications.batch_force_delete');
        });

        // ==========================================
        // 7. إدارة الإعدادات (Settings Management)
        // ==========================================
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('settings', [SettingController::class, 'index'])->name('api.v1.settings.index');
            Route::get('settings/categories', [SettingController::class, 'categories'])->name('api.v1.settings.categories');
        });

        Route::middleware('throttle:api-write')->group(function () {
            Route::put('settings/{id}/global', [SettingController::class, 'updateGlobal'])->whereNumber('id')->name('api.v1.settings.update_global');
            Route::put('settings/{id}/center', [SettingController::class, 'updateCenter'])->whereNumber('id')->name('api.v1.settings.update_center');
            Route::delete('settings/{id}/global', [SettingController::class, 'resetGlobal'])->whereNumber('id')->name('api.v1.settings.reset_global');
            Route::delete('settings/{id}/center', [SettingController::class, 'resetCenter'])->whereNumber('id')->name('api.v1.settings.reset_center');
        });

    });

});
