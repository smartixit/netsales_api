<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PermissionSeeder extends Seeder
{
    /**
     * Build the canonical permission catalog from backend capabilities.
     *
     * Every entry represents one independently grantable operation. Compound
     * permissions such as "manage" are intentionally not used where the API
     * exposes separate operations.
     */
    public function run(): void
    {
        $matrix = [
            'auth' => [
                'view_sessions' => 'عرض جلسات المستخدمين',
                'ban_user' => 'حظر مستخدم',
                'ban_device' => 'حظر جهاز',
                'unban_user' => 'رفع حظر مستخدم أو جهاز',
                'terminate_session' => 'إنهاء جلسة مستخدم',
            ],
            'users' => [
                'view' => 'عرض المستخدمين',
                'create' => 'إنشاء مستخدم',
                'edit' => 'تعديل بيانات مستخدم',
                'delete' => 'حذف أو تعطيل مستخدم',
                'export' => 'تصدير بيانات المستخدمين',
                'manage_permissions' => 'إسناد الصلاحيات المباشرة للمستخدم',
            ],
            'roles' => [
                'view' => 'عرض الأدوار ومصفوفة الصلاحيات',
            ],
            'geography' => [
                'view' => 'عرض الدليل الجغرافي',
                'create' => 'إضافة محافظة أو مديرية',
                'edit' => 'تعديل محافظة أو مديرية',
                'delete' => 'حذف محافظة أو مديرية',
                'restore' => 'استعادة محافظة أو مديرية',
                'force_delete' => 'حذف محافظة أو مديرية نهائياً',
            ],
            'networks' => [
                'view' => 'عرض الشبكات',
                'create' => 'إنشاء شبكة',
                'edit' => 'تعديل بيانات الشبكة',
                'delete' => 'حذف شبكة',
                'restore' => 'استعادة شبكة',
                'force_delete' => 'حذف شبكة نهائياً',
                'approve' => 'اعتماد تسجيل شبكة',
                'reject' => 'رفض تسجيل شبكة',
                'report' => 'عرض تقارير الشبكات',
                'export' => 'تصدير بيانات الشبكات',
                'create_pos_link' => 'إنشاء ارتباط بين شبكة ونقطة بيع',
                'update_pos_link' => 'تعديل ارتباط شبكة بنقطة بيع',
                'delete_pos_link' => 'إزالة ارتباط شبكة بنقطة بيع',
                'toggle_pos_link' => 'تغيير حالة ارتباط شبكة بنقطة بيع',
                'create_link_challenge' => 'إنشاء تحدي ربط نقطة بيع',
                'revoke_link_challenge' => 'إلغاء تحدي ربط نقطة بيع',
            ],
            'network_wallets' => [
                'view' => 'عرض محافظ الشبكات وحركاتها',
                'create' => 'إنشاء محفظة شبكة',
                'edit' => 'تعديل بيانات محفظة شبكة',
                'deposit' => 'إيداع رصيد في محفظة الشبكة',
                'withdraw' => 'سحب رصيد من محفظة الشبكة',
                'report' => 'عرض كشف حساب محفظة الشبكة',
                'export' => 'تصدير حركات محفظة الشبكة',
            ],
            'pos' => [
                'view' => 'عرض نقاط البيع',
                'create' => 'إنشاء نقطة بيع',
                'edit' => 'تعديل بيانات نقطة البيع',
                'delete' => 'حذف نقطة بيع',
                'restore' => 'استعادة نقطة بيع',
                'force_delete' => 'حذف نقطة بيع نهائياً',
                'approve' => 'اعتماد نقطة بيع',
                'reject' => 'رفض نقطة بيع',
                'report' => 'عرض تقارير نقاط البيع',
                'export' => 'تصدير بيانات نقاط البيع',
                'preview_link_challenge' => 'معاينة طلب ربط نقطة بيع',
                'confirm_link_challenge' => 'تأكيد طلب ربط نقطة بيع',
            ],
            'pos_wallets' => [
                'view' => 'عرض محافظ نقاط البيع وحركاتها',
                'create' => 'إنشاء محفظة نقطة بيع',
                'edit' => 'تعديل بيانات محفظة نقطة بيع',
                'deposit' => 'إيداع رصيد في محفظة نقطة البيع',
                'withdraw' => 'سحب رصيد من محفظة نقطة البيع',
                'transfer_from_network' => 'تحويل رصيد من الشبكة إلى نقطة البيع',
                'transfer_to_network' => 'تحويل رصيد من نقطة البيع إلى الشبكة',
                'manage_credit_limit' => 'تعديل حد ائتمان نقطة البيع',
                'report' => 'عرض كشف حساب محفظة نقطة البيع',
                'export' => 'تصدير حركات محافظ نقاط البيع',
            ],
            'voucher_packages' => [
                'view' => 'عرض باقات الكروت',
                'create' => 'إنشاء باقة كروت',
                'edit' => 'تعديل باقة كروت',
                'delete' => 'حذف باقة كروت',
                'restore' => 'استعادة باقة كروت',
                'force_delete' => 'حذف باقة كروت نهائياً',
                'export' => 'تصدير باقات الكروت',
            ],
            'requests' => [
                'view' => 'عرض طلبات الكروت والتعبئة',
                'create' => 'إنشاء طلب كروت أو تعبئة',
                'edit' => 'تعديل طلب معلق',
                'cancel' => 'إلغاء طلب قائم',
                'approve' => 'اعتماد طلب كروت',
                'reject' => 'رفض طلب كروت',
                'delete' => 'حذف طلب',
                'restore' => 'استعادة طلب',
                'force_delete' => 'حذف طلب نهائياً',
                'report' => 'عرض تقارير الطلبات',
                'export' => 'تصدير سجلات الطلبات',
            ],
            'sales' => [
                'view' => 'عرض فواتير المبيعات',
                'create' => 'إنشاء فاتورة مبيعات',
                'edit' => 'تعديل نوع فاتورة مبيعات',
                'delete' => 'حذف فاتورة مبيعات',
                'restore' => 'استعادة فاتورة مبيعات',
                'force_delete' => 'حذف فاتورة مبيعات نهائياً',
                'report' => 'عرض تقارير المبيعات',
                'export' => 'تصدير فواتير المبيعات',
            ],
            'expenses' => [
                'view' => 'عرض المصروفات',
                'create' => 'إنشاء سند مصروف',
                'edit' => 'تعديل سند مصروف وبنوده',
                'delete' => 'حذف سند أو بند مصروف',
                'restore' => 'استعادة سند مصروف',
                'force_delete' => 'حذف سند مصروف نهائياً',
                'report' => 'عرض تقارير المصروفات',
                'export' => 'تصدير سجلات المصروفات',
            ],
            'billing' => [
                'view' => 'عرض بيانات فوترة المنصة',
                'report' => 'عرض تقارير فوترة المنصة',
                'export' => 'تصدير تقارير فوترة المنصة',
                'create_account' => 'إنشاء حساب فوترة',
                'create_subscription' => 'إنشاء اشتراك',
                'create_product' => 'إنشاء منتج فوترة',
                'create_plan' => 'إنشاء خطة اشتراك',
                'create_plan_version' => 'إنشاء إصدار مسودة لخطة',
                'add_plan_price' => 'إضافة سعر إلى إصدار خطة',
                'add_plan_feature' => 'إضافة ميزة إلى إصدار خطة',
                'publish_plan_version' => 'نشر إصدار خطة اشتراك',
                'cancel_subscription' => 'إلغاء اشتراك',
                'create_trial_policy' => 'إنشاء سياسة تجربة مجانية',
                'create_dunning_policy' => 'إنشاء سياسة تحصيل متأخرات',
                'create_trial_version' => 'إنشاء إصدار سياسة تجربة',
                'create_dunning_version' => 'إنشاء إصدار سياسة تحصيل',
                'publish_trial_policy' => 'نشر إصدار سياسة تجربة',
                'publish_dunning_policy' => 'نشر إصدار سياسة تحصيل',
                'approve_trial_redemption' => 'اعتماد طلب استرداد تجربة',
                'reject_trial_redemption' => 'رفض طلب استرداد تجربة',
                'adjust_trial' => 'تعديل مدة تجربة اشتراك',
                'assign_plan_version' => 'تعيين إصدار خطة لاشتراك',
                'assign_price' => 'تعيين سعر لاشتراك',
                'create_currency' => 'إضافة عملة للنظام المالي',
                'update_currency' => 'تعديل بيانات عملة',
                'create_treasury_account' => 'إنشاء حساب خزينة',
                'update_treasury_status' => 'تغيير حالة حساب خزينة',
                'create_exchange_rate' => 'إضافة سعر صرف',
                'convert_currency' => 'تنفيذ تحويل بين العملات',
                'verify_payment' => 'التحقق من دفعة واردة',
                'reject_payment' => 'رفض دفعة واردة',
                'issue_credit_note' => 'إصدار إشعار دائن',
                'refund_payment' => 'استرداد دفعة مالية',
                'void_invoice' => 'إلغاء فاتورة فوترة',
            ],
            'support' => [
                'view' => 'عرض تذاكر الدعم المصرح بها',
                'create' => 'إنشاء تذكرة دعم',
                'reply' => 'إرسال رد في تذكرة دعم',
                'edit' => 'تعديل بيانات تذكرة دعم',
                'assign' => 'إسناد تذكرة دعم إلى موظف',
                'delete' => 'حذف تذكرة دعم',
                'restore' => 'استعادة تذكرة دعم',
                'force_delete' => 'حذف تذكرة دعم نهائياً',
            ],
            'notifications' => [
                'view' => 'عرض إشعارات المستخدم الشخصية',
                'view_all' => 'عرض سجل جميع إشعارات المنصة',
                'create' => 'إنشاء إشعار إداري',
                'edit' => 'تعديل إشعار إداري',
                'delete' => 'حذف إشعار إداري',
                'restore' => 'استعادة إشعار إداري',
                'force_delete' => 'حذف إشعار إداري نهائياً',
                'send_broadcast' => 'إرسال إشعار جماعي',
                'send_to_user' => 'إرسال إشعار مباشر لمستخدم',
            ],
            'system_logs' => [
                'view' => 'عرض سجلات تدقيق النظام',
            ],
            'settings' => [
                'view' => 'عرض إعدادات النظام',
                'create' => 'إنشاء إعداد نظام',
                'edit' => 'تعديل إعدادات النظام',
                'delete' => 'حذف إعداد نظام',
                'restore' => 'استعادة إعداد نظام',
                'force_delete' => 'حذف إعداد نظام نهائياً',
                'clear_cache' => 'مسح ذاكرة إعدادات النظام المؤقتة',
            ],
            'backups' => [
                'view' => 'عرض حالة وسجلات النسخ الاحتياطي',
                'connect_drive' => 'ربط خدمة Google Drive',
                'disconnect_drive' => 'فصل خدمة Google Drive',
                'update_config' => 'تعديل إعدادات النسخ الاحتياطي',
                'run' => 'تشغيل نسخة احتياطية يدوية',
            ],
            'sms_settings' => [
                'view' => 'عرض إعدادات الرسائل النصية',
                'update_global' => 'تعديل إعدادات SMS العامة',
                'update_network' => 'تعديل إعدادات SMS لشبكة',
                'test_network' => 'اختبار بوابة SMS لشبكة',
            ],
            'scheduled_tasks' => [
                'view' => 'عرض سجلات المهام المجدولة',
                'run_catchup' => 'تشغيل معالجة المهام المتأخرة',
            ],
        ];

        $modules = DB::table('modules')
            ->where('status', true)
            ->pluck('module_id', 'module_key');
        $actions = DB::table('action_types')
            ->where('status', true)
            ->pluck('action_type_id', 'action_type_key');

        $missingModules = array_values(array_diff(array_keys($matrix), $modules->keys()->all()));
        if ($missingModules !== []) {
            throw new RuntimeException(
                'Permission seeding is blocked; missing active modules: '.implode(', ', $missingModules)
            );
        }

        $expectedKeys = [];
        $now = now('Asia/Aden');

        foreach ($matrix as $moduleKey => $actionMap) {
            foreach ($actionMap as $actionKey => $permissionName) {
                if (! isset($actions[$actionKey])) {
                    throw new RuntimeException(
                        "Permission seeding is blocked; missing active action type [{$actionKey}] required by [{$moduleKey}]."
                    );
                }

                $permissionKey = "{$moduleKey}.{$actionKey}";
                if (strlen($permissionKey) > 50) {
                    throw new RuntimeException("Permission key [{$permissionKey}] exceeds the database limit.");
                }

                $expectedKeys[] = $permissionKey;

                DB::table('permissions')->updateOrInsert(
                    [
                        'module_id' => $modules[$moduleKey],
                        'action_type_id' => $actions[$actionKey],
                    ],
                    [
                        'permission_key' => $permissionKey,
                        'permission_name' => $permissionName,
                        'permission_desc' => "صلاحية {$permissionName} ضمن وحدة {$moduleKey}",
                        'status' => true,
                        'deleted_at' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        // Retire stale compound permissions without hard-deleting audit history.
        // Explicitly detach all grants first so soft-deleted definitions cannot
        // remain attached to a role or a user's direct permission set.
        $stalePermissionIds = DB::table('permissions')
            ->whereNotIn('permission_key', $expectedKeys)
            ->pluck('permission_id')
            ->all();

        if ($stalePermissionIds !== []) {
            DB::table('role_permissions')->whereIn('permission_id', $stalePermissionIds)->delete();
            DB::table('user_permissions')->whereIn('permission_id', $stalePermissionIds)->delete();

            DB::table('permissions')->whereIn('permission_id', $stalePermissionIds)->update([
                'status' => false,
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
