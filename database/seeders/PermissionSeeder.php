<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PermissionSeeder extends Seeder
{
    /**
     * A permission is one atomic capability, not a CRUD bundle.
     *
     * Scopes:
     * - PLATFORM: global platform administration.
     * - NETWORK: a single active Network membership or its owner.
     * - POS: a single active POS membership or its owner.
     * - SHARED: a read/operational capability that may be assigned to more than
     *   one role scope; the backend must still resolve the exact tenant context.
     */
    public function run(): void
    {
        $modules = DB::table('modules')
            ->where('status', true)
            ->pluck('module_id', 'module_key');

        $actions = DB::table('action_types')
            ->where('status', true)
            ->pluck('action_type_id', 'action_type_key');

        /**
         * Module => action => [Arabic label, permission scope].
         *
         * A unique action key is used within each module. Different business
         * operations deliberately have different action keys, even where both
         * mutate the same aggregate.
         *
         * @var array<string, array<string, array{0: string, 1: string}>> $matrix
         */
        $matrix = [
            'auth' => [
                'view_sessions' => ['عرض جلسات وأجهزة المستخدمين', 'PLATFORM'],
                'terminate_session' => ['إنهاء جلسة مستخدم محددة', 'PLATFORM'],
                'ban_user' => ['حظر حساب مستخدم', 'PLATFORM'],
                'ban_device' => ['حظر جهاز مستخدم', 'PLATFORM'],
                'lift_ban' => ['رفع حظر أمني محدد', 'PLATFORM'],
            ],
            'users' => [
                'view' => ['عرض حسابات مستخدمي المنصة', 'PLATFORM'],
                'create' => ['إنشاء حساب مستخدم للمنصة', 'PLATFORM'],
                'update' => ['تعديل بيانات حساب مستخدم', 'PLATFORM'],
                'change_status' => ['تغيير حالة حساب مستخدم', 'PLATFORM'],
                'update_profile' => ['تحديث صورة حساب مستخدم', 'PLATFORM'],
                'delete' => ['حذف حساب مستخدم مؤقتاً', 'PLATFORM'],
                'restore' => ['استعادة حساب مستخدم', 'PLATFORM'],
                'force_delete' => ['حذف حساب مستخدم نهائياً', 'PLATFORM'],
                'bulk_delete' => ['حذف مجموعة حسابات مستخدمين مؤقتاً', 'PLATFORM'],
                'bulk_restore' => ['استعادة مجموعة حسابات مستخدمين', 'PLATFORM'],
                'bulk_force_delete' => ['حذف مجموعة حسابات مستخدمين نهائياً', 'PLATFORM'],
            ],
            'roles' => [
                'view' => ['عرض أدوار المنصة', 'PLATFORM'],
                'assign_permissions' => ['إسناد صلاحيات مباشرة لمستخدم منصة', 'PLATFORM'],
            ],
            'geography' => [
                'view' => ['عرض المحافظات والمديريات', 'PLATFORM'],
                'create' => ['إضافة محافظة أو مديرية', 'PLATFORM'],
                'update' => ['تعديل محافظة أو مديرية', 'PLATFORM'],
                'delete' => ['حذف محافظة أو مديرية مؤقتاً', 'PLATFORM'],
                'restore' => ['استعادة محافظة أو مديرية', 'PLATFORM'],
                'force_delete' => ['حذف محافظة أو مديرية نهائياً', 'PLATFORM'],
                'bulk_delete' => ['حذف مجموعة محافظ أو مديريات مؤقتاً', 'PLATFORM'],
                'bulk_restore' => ['استعادة مجموعة محافظ أو مديريات', 'PLATFORM'],
                'bulk_force_delete' => ['حذف مجموعة محافظ أو مديريات نهائياً', 'PLATFORM'],
            ],
            'networks' => [
                'view' => ['عرض الشبكات ضمن النطاق المصرح', 'SHARED'],
                'create' => ['إنشاء سجل شبكة إدارياً', 'PLATFORM'],
                'update' => ['تعديل بيانات الشبكة', 'NETWORK'],
                'delete' => ['حذف الشبكة مؤقتاً', 'NETWORK'],
                'restore' => ['استعادة شبكة محذوفة', 'PLATFORM'],
                'force_delete' => ['حذف الشبكة نهائياً', 'PLATFORM'],
                'bulk_delete' => ['حذف مجموعة شبكات مؤقتاً', 'PLATFORM'],
                'bulk_restore' => ['استعادة مجموعة شبكات', 'PLATFORM'],
                'bulk_force_delete' => ['حذف مجموعة شبكات نهائياً', 'PLATFORM'],
                'approve' => ['اعتماد تسجيل شبكة', 'PLATFORM'],
                'reject' => ['رفض تسجيل شبكة', 'PLATFORM'],
            ],
            'network_members' => [
                'view_members' => ['عرض أعضاء الشبكة', 'NETWORK'],
                'add_member' => ['إضافة موظف إلى الشبكة', 'NETWORK'],
                'change_member_role' => ['تغيير دور موظف الشبكة', 'NETWORK'],
                'change_member_status' => ['تغيير حالة عضوية موظف الشبكة', 'NETWORK'],
                'remove_member' => ['إزالة موظف من الشبكة', 'NETWORK'],
            ],
            'network_pos' => [
                'view' => ['عرض روابط الشبكات ونقاط البيع', 'SHARED'],
                'create' => ['إنشاء ارتباط تجاري بين شبكة ونقطة بيع', 'NETWORK'],
                'update' => ['تعديل ارتباط تجاري قائم', 'NETWORK'],
                'delete' => ['حذف ارتباط تجاري', 'NETWORK'],
                'bulk_delete' => ['حذف مجموعة ارتباطات تجارية', 'NETWORK'],
                'bulk_toggle_status' => ['تغيير حالة مجموعة ارتباطات تجارية', 'NETWORK'],
                'toggle_status' => ['تفعيل أو تعطيل ارتباط تجاري', 'NETWORK'],
                'search_linkable_pos' => ['البحث عن نقاط بيع قابلة للدعوة', 'NETWORK'],
                'resolve_link_target' => ['مطابقة نقطة بيع لدعوتها', 'NETWORK'],
                'invite_pos' => ['إنشاء دعوة ربط لنقطة بيع محددة', 'NETWORK'],
                'preview_invitation' => ['معاينة دعوة ربط واردة', 'POS'],
                'confirm_invitation' => ['تأكيد دعوة ربط واردة', 'POS'],
                'view_invitation_status' => ['عرض حالة دعوة ربط', 'SHARED'],
                'revoke_invitation' => ['إلغاء دعوة ربط صادرة', 'NETWORK'],
            ],
            'network_wallets' => [
                'view' => ['عرض محافظ الشبكات ضمن النطاق', 'SHARED'],
                'report' => ['عرض تقرير محفظة الشبكة', 'SHARED'],
                'export' => ['تصدير كشف محفظة الشبكة', 'SHARED'],
                'create_wallet' => ['إنشاء محفظة شبكة', 'NETWORK'],
                'deposit' => ['إيداع مبلغ في محفظة الشبكة', 'NETWORK'],
                'withdraw' => ['سحب مبلغ من محفظة الشبكة', 'NETWORK'],
                'update_wallet_status' => ['تغيير حالة محفظة الشبكة', 'NETWORK'],
                'view_trashed' => ['عرض محافظ الشبكات المحذوفة', 'PLATFORM'],
                'delete_wallet' => ['حذف محفظة شبكة', 'PLATFORM'],
                'restore_wallet' => ['استعادة محفظة شبكة', 'PLATFORM'],
                'force_delete_wallet' => ['حذف محفظة شبكة نهائياً', 'PLATFORM'],
                'bulk_delete_wallets' => ['حذف مجموعة محافظ شبكات', 'PLATFORM'],
                'bulk_restore_wallets' => ['استعادة مجموعة محافظ شبكات', 'PLATFORM'],
                'bulk_force_delete_wallets' => ['حذف مجموعة محافظ شبكات نهائياً', 'PLATFORM'],
            ],
            'pos' => [
                'view' => ['عرض نقاط البيع ضمن النطاق المصرح', 'SHARED'],
                'create' => ['إنشاء سجل نقطة بيع إدارياً', 'PLATFORM'],
                'update' => ['تعديل بيانات نقطة البيع', 'POS'],
                'delete' => ['حذف نقطة بيع مؤقتاً', 'PLATFORM'],
                'restore' => ['استعادة نقطة بيع محذوفة', 'PLATFORM'],
                'force_delete' => ['حذف نقطة بيع نهائياً', 'PLATFORM'],
                'bulk_delete' => ['حذف مجموعة نقاط بيع مؤقتاً', 'PLATFORM'],
                'bulk_restore' => ['استعادة مجموعة نقاط بيع', 'PLATFORM'],
                'bulk_force_delete' => ['حذف مجموعة نقاط بيع نهائياً', 'PLATFORM'],
                'approve' => ['اعتماد تسجيل نقطة بيع', 'PLATFORM'],
                'reject' => ['رفض تسجيل نقطة بيع', 'PLATFORM'],
            ],
            'pos_members' => [
                'view_members' => ['عرض أعضاء نقطة البيع', 'POS'],
                'add_member' => ['إضافة موظف إلى نقطة البيع', 'POS'],
                'change_member_role' => ['تغيير دور موظف نقطة البيع', 'POS'],
                'change_member_status' => ['تغيير حالة عضوية موظف نقطة البيع', 'POS'],
                'remove_member' => ['إزالة موظف من نقطة البيع', 'POS'],
            ],
            'pos_wallets' => [
                'view' => ['عرض محافظ نقاط البيع ضمن النطاق', 'SHARED'],
                'report' => ['عرض تقرير محفظة نقطة البيع', 'SHARED'],
                'export' => ['تصدير كشف محفظة نقطة البيع', 'SHARED'],
                'create_wallet' => ['إنشاء محفظة نقطة بيع', 'NETWORK'],
                'deposit' => ['إيداع مبلغ في محفظة نقطة البيع', 'NETWORK'],
                'withdraw' => ['سحب مبلغ من محفظة نقطة البيع', 'NETWORK'],
                'transfer_from_network' => ['تحويل رصيد من الشبكة إلى نقطة البيع', 'NETWORK'],
                'transfer_to_network' => ['تحويل رصيد من نقطة البيع إلى الشبكة', 'NETWORK'],
                'manage_credit_limit' => ['تعديل السقف الائتماني لنقطة البيع', 'NETWORK'],
                'update_wallet_status' => ['تغيير حالة محفظة نقطة البيع', 'NETWORK'],
                'view_trashed' => ['عرض محافظ نقاط البيع المحذوفة', 'PLATFORM'],
                'delete_wallet' => ['حذف محفظة نقطة بيع', 'PLATFORM'],
                'restore_wallet' => ['استعادة محفظة نقطة بيع', 'PLATFORM'],
                'force_delete_wallet' => ['حذف محفظة نقطة بيع نهائياً', 'PLATFORM'],
                'bulk_delete_wallets' => ['حذف مجموعة محافظ نقاط بيع', 'PLATFORM'],
                'bulk_restore_wallets' => ['استعادة مجموعة محافظ نقاط بيع', 'PLATFORM'],
                'bulk_force_delete_wallets' => ['حذف مجموعة محافظ نقاط بيع نهائياً', 'PLATFORM'],
            ],
            'voucher_packages' => [
                'view' => ['عرض باقات الشبكات المرتبطة', 'SHARED'],
                'create' => ['إنشاء باقة للشبكة', 'NETWORK'],
                'update' => ['تعديل بيانات الباقة', 'NETWORK'],
                'delete' => ['حذف باقة مؤقتاً', 'NETWORK'],
                'restore' => ['استعادة باقة محذوفة', 'NETWORK'],
                'force_delete' => ['حذف باقة نهائياً', 'NETWORK'],
                'bulk_delete' => ['حذف مجموعة باقات مؤقتاً', 'NETWORK'],
                'bulk_restore' => ['استعادة مجموعة باقات', 'NETWORK'],
                'bulk_force_delete' => ['حذف مجموعة باقات نهائياً', 'NETWORK'],
                'toggle_status' => ['تفعيل أو تعطيل باقة', 'NETWORK'],
            ],
            'package_discounts' => [
                'view' => ['عرض الخصومات التعاقدية المسموح بها', 'SHARED'],
                'create' => ['إنشاء خصم تعاقدي لنقطة بيع', 'NETWORK'],
                'update' => ['تعديل خصم تعاقدي', 'NETWORK'],
                'delete' => ['حذف خصم تعاقدي مؤقتاً', 'NETWORK'],
                'restore' => ['استعادة خصم تعاقدي', 'NETWORK'],
                'force_delete' => ['حذف خصم تعاقدي نهائياً', 'NETWORK'],
                'bulk_delete' => ['حذف مجموعة خصومات تعاقدية', 'NETWORK'],
                'bulk_restore' => ['استعادة مجموعة خصومات تعاقدية', 'NETWORK'],
                'bulk_force_delete' => ['حذف مجموعة خصومات تعاقدية نهائياً', 'NETWORK'],
                'toggle_status' => ['تفعيل أو تعطيل خصم تعاقدي', 'NETWORK'],
            ],
            'requests' => [
                'view' => ['عرض طلبات التوريد', 'SHARED'],
                'create' => ['إنشاء طلب توريد لنقطة بيع', 'POS'],
                'quote' => ['احتساب عرض سعر لطلب توريد جديد', 'POS'],
                'update' => ['تعديل طلب توريد معلق', 'POS'],
                'cancel' => ['إلغاء طلب توريد مقدم', 'POS'],
                'delete' => ['حذف طلب توريد مؤقتاً', 'SHARED'],
                'restore' => ['استعادة طلب توريد', 'SHARED'],
                'force_delete' => ['حذف طلب توريد نهائياً', 'SHARED'],
                'bulk_delete' => ['حذف مجموعة طلبات توريد', 'SHARED'],
                'bulk_restore' => ['استعادة مجموعة طلبات توريد', 'SHARED'],
                'bulk_force_delete' => ['حذف مجموعة طلبات توريد نهائياً', 'SHARED'],
                'preview_modification' => ['معاينة تعديل طلب قبل اعتماده', 'NETWORK'],
                'approve' => ['اعتماد طلب توريد وإنشاء فاتورته', 'NETWORK'],
                'reject' => ['رفض طلب توريد', 'NETWORK'],
                'report' => ['عرض تقرير طلبات التوريد', 'SHARED'],
                'export' => ['تصدير تقرير طلبات التوريد', 'SHARED'],
            ],
            'sales' => [
                'view' => ['عرض فواتير المبيعات', 'SHARED'],
                'create' => ['إنشاء فاتورة بيع مباشرة للشبكة', 'NETWORK'],
                'update' => ['تعديل نوع فاتورة بيع', 'NETWORK'],
                'delete' => ['حذف فاتورة بيع مؤقتاً', 'NETWORK'],
                'force_delete' => ['حذف فاتورة بيع نهائياً', 'NETWORK'],
                'bulk_delete' => ['حذف مجموعة فواتير مبيعات مؤقتاً', 'NETWORK'],
                'bulk_force_delete' => ['حذف مجموعة فواتير مبيعات نهائياً', 'NETWORK'],
                'report' => ['عرض تقارير المبيعات', 'SHARED'],
                'export' => ['تصدير تقارير وفواتير المبيعات', 'SHARED'],
            ],
            'expenses' => [
                'view' => ['عرض المصروفات التشغيلية ضمن النطاق المالي المصرح', 'SHARED'],
                'create' => ['تسجيل مصروف تشغيلي', 'NETWORK'],
                'update' => ['تعديل مصروف تشغيلي', 'NETWORK'],
                'delete' => ['حذف مصروف تشغيلي مؤقتاً', 'NETWORK'],
                'restore' => ['استعادة مصروف تشغيلي', 'NETWORK'],
                'force_delete' => ['حذف مصروف تشغيلي نهائياً', 'NETWORK'],
                'add_item' => ['إضافة بند إلى مصروف', 'NETWORK'],
                'delete_item' => ['حذف بند من مصروف', 'NETWORK'],
                'delete_attachment' => ['حذف مرفق مصروف', 'NETWORK'],
                'bulk_delete' => ['حذف مجموعة مصروفات', 'NETWORK'],
                'bulk_restore' => ['استعادة مجموعة مصروفات', 'NETWORK'],
                'bulk_force_delete' => ['حذف مجموعة مصروفات نهائياً', 'NETWORK'],
                'report' => ['عرض تقارير مصروفات الشبكة', 'SHARED'],
                'export' => ['تصدير تقارير مصروفات الشبكة', 'SHARED'],
            ],
            'billing' => [
                'view' => ['عرض بيانات فوترة المنصة', 'PLATFORM'],
                'report' => ['عرض تقارير فوترة المنصة', 'PLATFORM'],
                'export' => ['تصدير تقارير فوترة المنصة', 'PLATFORM'],
                'create_account' => ['إنشاء حساب فوترة', 'PLATFORM'],
                'create_subscription' => ['إنشاء اشتراك منصة', 'PLATFORM'],
                'create_product' => ['إنشاء منتج فوترة', 'PLATFORM'],
                'create_plan' => ['إنشاء خطة اشتراك', 'PLATFORM'],
                'create_draft_version' => ['إنشاء إصدار خطة مسودة', 'PLATFORM'],
                'add_price' => ['إضافة سعر لإصدار خطة', 'PLATFORM'],
                'add_feature' => ['إضافة ميزة لإصدار خطة', 'PLATFORM'],
                'publish_plan_version' => ['نشر إصدار خطة', 'PLATFORM'],
                'manage_currency' => ['إضافة أو تحديث عملة منصة', 'PLATFORM'],
                'manage_treasury_accounts' => ['إدارة حسابات خزينة المنصة', 'PLATFORM'],
                'manage_exchange_rates' => ['إدارة أسعار صرف العملات', 'PLATFORM'],
                'convert_currency' => ['تنفيذ تحويل عملة', 'PLATFORM'],
                'verify_payment' => ['اعتماد دفعة اشتراك', 'PLATFORM'],
                'reject_payment' => ['رفض دفعة اشتراك', 'PLATFORM'],
                'issue_credit_note' => ['إصدار إشعار دائن لفاتورة', 'PLATFORM'],
                'refund_payment' => ['تنفيذ استرداد دفعة', 'PLATFORM'],
                'void_invoice' => ['إبطال فاتورة فوترة', 'PLATFORM'],
                'cancel_subscription' => ['إلغاء اشتراك منصة', 'PLATFORM'],
                'create_trial_policy' => ['إنشاء سياسة تجربة مجانية', 'PLATFORM'],
                'create_dunning_policy' => ['إنشاء سياسة متابعة التحصيل', 'PLATFORM'],
                'create_trial_version' => ['إنشاء إصدار سياسة تجربة', 'PLATFORM'],
                'create_dunning_version' => ['إنشاء إصدار سياسة تحصيل', 'PLATFORM'],
                'publish_trial_version' => ['نشر إصدار سياسة تجربة', 'PLATFORM'],
                'publish_dunning_version' => ['نشر إصدار سياسة تحصيل', 'PLATFORM'],
                'assign_plan_version' => ['تعيين إصدار سياسة لخطة', 'PLATFORM'],
                'assign_price_policy' => ['تعيين سياسة لسعر', 'PLATFORM'],
                'approve_trial_redemption' => ['اعتماد طلب استحقاق تجربة', 'PLATFORM'],
                'reject_trial_redemption' => ['رفض طلب استحقاق تجربة', 'PLATFORM'],
                'adjust_trial' => ['تعديل مدة تجربة اشتراك', 'PLATFORM'],
            ],
            'support_tickets' => [
                'view' => ['عرض تذاكر الدعم ضمن النطاق المصرح', 'SHARED'],
                'create' => ['إنشاء تذكرة دعم', 'SHARED'],
                'update' => ['تعديل عنوان تذكرة دعم', 'SHARED'],
                'reassign' => ['إعادة إسناد تذكرة دعم بين المستأجرين', 'SHARED'],
                'view_trashed' => ['عرض تذاكر الدعم المحذوفة', 'PLATFORM'],
                'delete' => ['حذف تذكرة دعم مؤقتاً', 'PLATFORM'],
                'restore' => ['استعادة تذكرة دعم', 'PLATFORM'],
                'force_delete' => ['حذف تذكرة دعم نهائياً', 'PLATFORM'],
                'bulk_delete' => ['حذف مجموعة تذاكر دعم', 'PLATFORM'],
                'bulk_restore' => ['استعادة مجموعة تذاكر دعم', 'PLATFORM'],
                'bulk_force_delete' => ['حذف مجموعة تذاكر دعم نهائياً', 'PLATFORM'],
                'reply' => ['إرسال رد في تذكرة دعم', 'SHARED'],
                'mark_read' => ['تمييز رسائل تذكرة كمقروءة', 'SHARED'],
                'close' => ['إغلاق تذكرة دعم', 'PLATFORM'],
            ],
            'notifications' => [
                'view' => ['عرض سجل الإشعارات الإدارية', 'PLATFORM'],
                'view_trashed' => ['عرض الإشعارات الإدارية المحذوفة', 'PLATFORM'],
                'update' => ['تعديل إشعار إداري', 'PLATFORM'],
                'send' => ['إرسال إشعار إداري موجه', 'PLATFORM'],
                'delete' => ['حذف إشعار إداري مؤقتاً', 'PLATFORM'],
                'restore' => ['استعادة إشعار إداري', 'PLATFORM'],
                'force_delete' => ['حذف إشعار إداري نهائياً', 'PLATFORM'],
                'bulk_delete' => ['حذف مجموعة إشعارات إدارية', 'PLATFORM'],
                'bulk_restore' => ['استعادة مجموعة إشعارات إدارية', 'PLATFORM'],
                'bulk_force_delete' => ['حذف مجموعة إشعارات إدارية نهائياً', 'PLATFORM'],
            ],
            'system_logs' => [
                'view' => ['عرض سجلات النظام والتدقيق', 'PLATFORM'],
            ],
            'settings' => [
                'view' => ['عرض إعدادات النظام', 'PLATFORM'],
                'create' => ['إنشاء إعداد نظام', 'PLATFORM'],
                'update' => ['تحديث إعداد نظام', 'PLATFORM'],
                'update_many' => ['تحديث مجموعة إعدادات', 'PLATFORM'],
                'delete' => ['حذف إعداد نظام مؤقتاً', 'PLATFORM'],
                'restore' => ['استعادة إعداد نظام', 'PLATFORM'],
                'force_delete' => ['حذف إعداد نظام نهائياً', 'PLATFORM'],
                'bulk_delete' => ['حذف مجموعة إعدادات', 'PLATFORM'],
                'bulk_restore' => ['استعادة مجموعة إعدادات', 'PLATFORM'],
                'bulk_force_delete' => ['حذف مجموعة إعدادات نهائياً', 'PLATFORM'],
                'clear_cache' => ['تفريغ ذاكرة إعدادات النظام', 'PLATFORM'],
            ],
            'backups' => [
                'view' => ['عرض النسخ الاحتياطية وإعداداتها', 'PLATFORM'],
                'configure_drive' => ['ربط حساب التخزين السحابي', 'PLATFORM'],
                'disconnect_drive' => ['فصل حساب التخزين السحابي', 'PLATFORM'],
                'update_configuration' => ['تحديث إعدادات النسخ الاحتياطي', 'PLATFORM'],
                'run_backup' => ['تنفيذ نسخة احتياطية يدوية', 'PLATFORM'],
            ],
            'sms_settings' => [
                'view' => ['عرض إعدادات وبوابات الرسائل', 'PLATFORM'],
                'update_global' => ['تحديث إعداد الرسائل العام', 'PLATFORM'],
                'update_network' => ['تحديث إعداد رسائل شبكة', 'PLATFORM'],
                'test_network' => ['اختبار بوابة رسائل شبكة', 'PLATFORM'],
            ],
            'scheduled_tasks' => [
                'view' => ['عرض سجلات المهام المجدولة', 'PLATFORM'],
                'catchup' => ['تشغيل استدراك المهام المجدولة', 'PLATFORM'],
            ],
        ];

        $canonicalKeys = [];

        foreach ($matrix as $moduleKey => $permissionMap) {
            if (! isset($modules[$moduleKey])) {
                throw new RuntimeException("Required permission module [{$moduleKey}] is missing or disabled.");
            }

            $moduleId = (int) $modules[$moduleKey];

            foreach ($permissionMap as $actionKey => [$permissionName, $scope]) {
                if (! isset($actions[$actionKey])) {
                    throw new RuntimeException("Required action type [{$actionKey}] is missing or disabled.");
                }

                if (! in_array($scope, ['PLATFORM', 'NETWORK', 'POS', 'SHARED'], true)) {
                    throw new RuntimeException("Invalid permission scope [{$scope}] for [{$moduleKey}.{$actionKey}].");
                }

                $permissionKey = "{$moduleKey}.{$actionKey}";
                $canonicalKeys[] = $permissionKey;

                DB::table('permissions')->updateOrInsert(
                    ['permission_key' => $permissionKey],
                    [
                        'module_id' => $moduleId,
                        'action_type_id' => (int) $actions[$actionKey],
                        'permission_name' => $permissionName,
                        'permission_desc' => "يسمح بتنفيذ وظيفة {$permissionName} فقط ضمن نطاق {$scope}.",
                        'scope' => $scope,
                        'status' => true,
                        'updated_at' => now('Asia/Aden'),
                        'created_at' => now('Asia/Aden'),
                    ]
                );
            }
        }

        DB::table('permissions')
            ->whereNotIn('permission_key', $canonicalKeys)
            ->update([
                'status' => false,
                'updated_at' => now('Asia/Aden'),
            ]);
    }
}
