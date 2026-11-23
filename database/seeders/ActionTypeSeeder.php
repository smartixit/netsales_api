<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActionTypeSeeder extends Seeder
{
    /**
     * Seed atomic authorization actions used by the permission catalog.
     *
     * Action types are deliberately operation-specific. The permissions table
     * has a unique (module_id, action_type_id) constraint, so each action type
     * can identify one independently grantable capability per module.
     */
    public function run(): void
    {
        $actions = [
            'view' => 'عرض',
            'create' => 'إنشاء',
            'edit' => 'تعديل',
            'delete' => 'حذف',
            'restore' => 'استعادة',
            'force_delete' => 'حذف نهائي',
            'report' => 'عرض التقارير',
            'export' => 'تصدير',
            'approve' => 'اعتماد',
            'reject' => 'رفض',

            'view_sessions' => 'عرض الجلسات',
            'ban_user' => 'حظر مستخدم',
            'ban_device' => 'حظر جهاز',
            'unban_user' => 'رفع الحظر',
            'terminate_session' => 'إنهاء جلسة',
            'manage_permissions' => 'إسناد صلاحيات المستخدم',

            'create_pos_link' => 'إنشاء ربط نقطة بيع',
            'update_pos_link' => 'تعديل ربط نقطة بيع',
            'delete_pos_link' => 'إزالة ربط نقطة بيع',
            'toggle_pos_link' => 'تغيير حالة ربط نقطة بيع',
            'create_link_challenge' => 'إنشاء تحدي ربط',
            'revoke_link_challenge' => 'إلغاء تحدي ربط',
            'preview_link_challenge' => 'معاينة تحدي الربط',
            'confirm_link_challenge' => 'تأكيد تحدي الربط',

            'deposit' => 'إيداع رصيد',
            'withdraw' => 'سحب رصيد',
            'manage_credit_limit' => 'تعديل حد الائتمان',
            'transfer_from_network' => 'تحويل من الشبكة إلى النقطة',
            'transfer_to_network' => 'تحويل من النقطة إلى الشبكة',
            'cancel' => 'إلغاء طلب',

            'create_account' => 'إنشاء حساب فوترة',
            'create_subscription' => 'إنشاء اشتراك',
            'create_product' => 'إنشاء منتج فوترة',
            'create_plan' => 'إنشاء خطة اشتراك',
            'create_plan_version' => 'إنشاء إصدار خطة',
            'add_plan_price' => 'إضافة سعر للخطة',
            'add_plan_feature' => 'إضافة ميزة للخطة',
            'publish_plan_version' => 'نشر إصدار خطة',
            'cancel_subscription' => 'إلغاء اشتراك',
            'create_trial_policy' => 'إنشاء سياسة تجربة',
            'create_dunning_policy' => 'إنشاء سياسة تحصيل',
            'create_trial_version' => 'إنشاء إصدار سياسة تجربة',
            'create_dunning_version' => 'إنشاء إصدار سياسة تحصيل',
            'publish_trial_policy' => 'نشر سياسة تجربة',
            'publish_dunning_policy' => 'نشر سياسة تحصيل',
            'approve_trial_redemption' => 'اعتماد استرداد تجربة',
            'reject_trial_redemption' => 'رفض استرداد تجربة',
            'adjust_trial' => 'تعديل مدة تجربة',
            'assign_plan_version' => 'تعيين إصدار خطة',
            'assign_price' => 'تعيين سعر',
            'create_currency' => 'إضافة عملة',
            'update_currency' => 'تعديل عملة',
            'create_treasury_account' => 'إنشاء حساب خزينة',
            'update_treasury_status' => 'تعديل حالة حساب خزينة',
            'create_exchange_rate' => 'إضافة سعر صرف',
            'convert_currency' => 'تنفيذ تحويل عملة',
            'verify_payment' => 'التحقق من دفعة',
            'reject_payment' => 'رفض دفعة',
            'issue_credit_note' => 'إصدار إشعار دائن',
            'refund_payment' => 'تنفيذ استرداد مالي',
            'void_invoice' => 'إلغاء فاتورة',

            'reply' => 'الرد على تذكرة',
            'assign' => 'إسناد تذكرة',
            'view_all' => 'عرض جميع الإشعارات',
            'send_broadcast' => 'إرسال إشعار جماعي',
            'send_to_user' => 'إرسال إشعار لمستخدم',
            'clear_cache' => 'مسح الذاكرة المؤقتة',

            'connect_drive' => 'ربط Google Drive',
            'disconnect_drive' => 'فصل Google Drive',
            'update_config' => 'تعديل إعدادات النسخ',
            'run' => 'تشغيل نسخة احتياطية',

            'update_global' => 'تعديل إعداد SMS العام',
            'update_network' => 'تعديل إعداد SMS للشبكة',
            'test_network' => 'اختبار بوابة SMS للشبكة',
            'run_catchup' => 'تشغيل الاستدراك المجدول',
        ];

        $now = now('Asia/Aden');
        foreach ($actions as $key => $label) {
            DB::table('action_types')->updateOrInsert(
                ['action_type_key' => $key],
                [
                    'action_type_name' => $label,
                    'status' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
