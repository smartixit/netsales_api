<?php

namespace Database\\Seeders;

use Illuminate\\Database\\Seeder;
use Illuminate\\Support\\Facades\\DB;

class ActionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            'view' => 'عرض',
            'report' => 'استعراض تقرير',
            'export' => 'تصدير',
            'create' => 'إنشاء',
            'update' => 'تحديث',
            'delete' => 'حذف',
            'restore' => 'استعادة',
            'force_delete' => 'حذف نهائي',
            'approve' => 'اعتماد',
            'reject' => 'رفض',
            'cancel' => 'إلغاء',
            'preview_modification' => 'معاينة تعديل',
            'invite_pos' => 'دعوة نقطة بيع',
            'resolve_link_target' => 'مطابقة نقطة البيع',
            'search_linkable_pos' => 'البحث عن نقاط بيع قابلة للربط',
            'preview_invitation' => 'معاينة دعوة',
            'confirm_invitation' => 'تأكيد دعوة',
            'view_invitation_status' => 'عرض حالة الدعوة',
            'revoke_invitation' => 'إلغاء دعوة',
            'toggle_status' => 'تغيير حالة التفعيل',
            'deposit' => 'إيداع',
            'withdraw' => 'سحب',
            'transfer_from_network' => 'تحويل من الشبكة إلى النقطة',
            'transfer_to_network' => 'تحويل من النقطة إلى الشبكة',
            'manage_credit_limit' => 'تعديل السقف الائتماني',
            'create_wallet' => 'إنشاء محفظة',
            'update_wallet_status' => 'تغيير حالة المحفظة',
            'manage_lifecycle' => 'إدارة دورة حياة المحفظة',
            'add_member' => 'إضافة عضو',
            'change_member_role' => 'تغيير دور عضو',
            'change_member_status' => 'تغيير حالة عضو',
            'remove_member' => 'إزالة عضو',
            'view_members' => 'عرض الأعضاء',
            'update_profile' => 'تعديل الملف الشخصي',
            'change_status' => 'تغيير حالة الحساب',
            'assign_permissions' => 'إسناد الصلاحيات المباشرة',
            'terminate_session' => 'إنهاء جلسة',
            'ban_user' => 'حظر مستخدم',
            'ban_device' => 'حظر جهاز',
            'lift_ban' => 'رفع حظر',
            'add_item' => 'إضافة بند',
            'delete_item' => 'حذف بند',
            'delete_attachment' => 'حذف مرفق',
            'reply' => 'إرسال رد',
            'mark_read' => 'تمييز كمقروء',
            'send' => 'إرسال',
            'close' => 'إغلاق',
            'create_account' => 'إنشاء حساب فوترة',
            'create_subscription' => 'إنشاء اشتراك',
            'create_product' => 'إنشاء منتج',
            'create_plan' => 'إنشاء خطة',
            'create_draft_version' => 'إنشاء إصدار مسودة',
            'add_price' => 'إضافة سعر',
            'add_feature' => 'إضافة ميزة',
            'publish_plan_version' => 'نشر إصدار خطة',
            'manage_currency' => 'إدارة عملة',
            'manage_treasury_accounts' => 'إدارة حسابات الخزينة',
            'manage_exchange_rates' => 'إدارة أسعار الصرف',
            'convert_currency' => 'تحويل عملة',
            'verify_payment' => 'التحقق من دفعة',
            'reject_payment' => 'رفض دفعة',
            'issue_credit_note' => 'إصدار إشعار دائن',
            'refund_payment' => 'استرداد دفعة',
            'void_invoice' => 'إبطال فاتورة',
            'cancel_subscription' => 'إلغاء اشتراك',
            'create_trial_policy' => 'إنشاء سياسة تجربة',
            'create_dunning_policy' => 'إنشاء سياسة تحصيل متأخر',
            'create_trial_version' => 'إنشاء إصدار سياسة تجربة',
            'create_dunning_version' => 'إنشاء إصدار سياسة تحصيل',
            'publish_trial_version' => 'نشر إصدار سياسة تجربة',
            'publish_dunning_version' => 'نشر إصدار سياسة تحصيل',
            'assign_plan_version' => 'إسناد إصدار خطة',
            'assign_price_policy' => 'إسناد سياسة سعر',
            'approve_trial_redemption' => 'اعتماد استحقاق تجربة',
            'reject_trial_redemption' => 'رفض استحقاق تجربة',
            'adjust_trial' => 'تعديل مدة تجربة',
            'configure_drive' => 'ربط تخزين سحابي',
            'disconnect_drive' => 'فصل تخزين سحابي',
            'update_configuration' => 'تحديث الإعدادات',
            'run_backup' => 'تنفيذ نسخة احتياطية',
            'update_global' => 'تحديث إعداد عام',
            'update_network' => 'تحديث إعداد شبكة',
            'test_network' => 'اختبار إعداد شبكة',
            'catchup' => 'تشغيل استدراك المهام',
            'update_many' => 'تحديث مجموعة إعدادات',
            'clear_cache' => 'تفريغ الذاكرة المؤقتة',
            'view_sessions' => 'عرض الجلسات والأجهزة',
        ];

        foreach ($actions as $key => $name) {
            DB::table('action_types')->updateOrInsert(
                ['action_type_key' => $key],
                [
                    'action_type_name' => $name,
                    'status' => true,
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ]
            );
        }

        DB::table('action_types')
            ->whereNotIn('action_type_key', array_keys($actions))
            ->update([
                'status' => false,
                'updated_at' => now('Asia/Aden'),
            ]);
    }
}
