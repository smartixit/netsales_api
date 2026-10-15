<?php

// Script to generate the comprehensive, perfectly organized lang/ar/validation.php

$base = require __DIR__ . '/../lang/ar/validation.php';

// Prepare standard rules
$standardKeys = [];
foreach ($base as $k => $v) {
    if (!in_array($k, ['attributes', 'custom', 'values'])) {
        $standardKeys[$k] = $v;
    }
}

// Let's inspect all current attributes and create a beautifully categorized and complete attributes array
$newAttributes = [
    // ========================================================
    // 1. RBAC, MODULES & ACTION TYPES
    // ========================================================
    'role_id' => 'الدور',
    'role_key' => 'مفتاح الدور',
    'role_name' => 'اسم الدور',
    'role_desc' => 'وصف الدور',
    'is_system' => 'إعداد نظام',
    'permissions' => 'الصلاحيات المحددة',
    'permissions.*' => 'الصلاحية',

    'module_id' => 'الوحدة النمطية',
    'module_key' => 'مفتاح الوحدة النمطية',
    'module_name' => 'اسم الوحدة النمطية',
    'module_icon' => 'أيقونة الوحدة',
    'module_desc' => 'وصف الوحدة النمطية',

    'action_type_id' => 'نوع العملية',
    'action_type_key' => 'مفتاح نوع العملية',
    'action_type_name' => 'اسم نوع العملية',
    'action_type_desc' => 'وصف نوع العملية',

    'permission_id' => 'الصلاحية',
    'permission_key' => 'مفتاح الصلاحية',
    'permission_name' => 'اسم الصلاحية',
    'permission_desc' => 'وصف الصلاحية',

    'role_permission_id' => 'معرف صلاحية الدور',
    'user_permission_id' => 'معرف صلاحية المستخدم',

    // ========================================================
    // 2. USERS, DEVICES, SESSIONS & SECURITY
    // ========================================================
    'user_id' => 'المستخدم',
    'user_uuid' => 'المعرف الفريد للمستخدم',
    'user_full_name' => 'الاسم الكامل',
    'user_phone_number' => 'رقم الهاتف',
    'user_email' => 'البريد الإلكتروني',
    'user_password' => 'كلمة المرور',
    'user_password_confirmation' => 'تأكيد كلمة المرور',
    'user_2fa_enabled' => 'التحقق بخطوتين',
    'user_profile_logo' => 'الصورة الشخصية',
    'user_login_attempts' => 'محاولات تسجيل الدخول',
    'last_login_at' => 'تاريخ آخر تسجيل دخول',
    'profile_picture' => 'الصورة الشخصية',
    'current_password' => 'كلمة المرور الحالية',
    'new_password' => 'كلمة المرور الجديدة',
    'password' => 'كلمة المرور',
    'enable' => 'حالة التفعيل',
    'enabled' => 'مفعل',
    'is_enabled' => 'حالة التفعيل',
    'is_primary' => 'جهاز رئيسي',

    'device_id' => 'الجهاز',
    'device_uuid' => 'المعرف الفريد للجهاز',
    'device_name' => 'اسم الجهاز',
    'device_type' => 'نوع الجهاز',
    'device_os' => 'نظام تشغيل الجهاز',
    'device_ip' => 'عنوان IP للجهاز',
    'push_token' => 'رمز الإشعارات للجهاز',
    'fcm_token' => 'رمز إشعارات Firebase (FCM)',
    'last_activity_at' => 'تاريخ آخر نشاط',
    'last_activity' => 'آخر نشاط للجهاز',

    'session_id' => 'الجلسة',
    'session_token' => 'رمز الجلسة',
    'token_hash' => 'تشفير رمز الجلسة',
    'login_at' => 'وقت تسجيل الدخول',
    'logged_at' => 'وقت التسجيل',
    'expires_at' => 'تاريخ الانتهاء',
    'token_expires_at' => 'تاريخ انتهاء صلاحية الرمز',
    'revoked_at' => 'تاريخ إبطال الجلسة',

    'ban_id' => 'الحظر الأمني',
    'banned_by' => 'المسؤول الحاظر',
    'ban_type' => 'نوع الحظر',
    'ban_reason' => 'سبب الحظر',
    'start_at' => 'تاريخ بدء الحظر',
    'end_at' => 'تاريخ انتهاء الحظر',
    'ban_start_date' => 'تاريخ بدء الحظر',
    'ban_end_date' => 'تاريخ انتهاء الحظر',
    'is_permanent' => 'حظر دائم',

    'otp' => 'رمز التحقق (OTP)',
    'otp_id' => 'رمز التحقق',
    'otp_code' => 'رمز التحقق (OTP)',
    'otp_type' => 'نوع رمز التحقق',
    'otp_failure_attempts' => 'عدد محاولات OTP الخاطئة',
    'is_used' => 'حالة الاستخدام',
    'used_at' => 'تاريخ الاستخدام',

    'log_id' => 'سجل النظام',
    'log_level' => 'مستوى السجل',
    'log_action' => 'العملية المسجلة',
    'log_message' => 'رسالة السجل',
    'log_context' => 'بيانات السياق',
    'module' => 'الوحدة النمطية',
    'action' => 'الإجراء',
    'entity_type' => 'نوع الكيان المرتبط',
    'entity_id' => 'معرف الكيان المرتبط',
    'old_values' => 'البيانات السابقة',
    'new_values' => 'البيانات الجديدة',
    'ip_address' => 'عنوان IP',
    'user_agent' => 'معلومات المتصفح/الجهاز',

    // ========================================================
    // 3. GEOGRAPHY
    // ========================================================
    'governorate_id' => 'المحافظة',
    'governorate_name' => 'اسم المحافظة',

    'directorate_id' => 'المديرية',
    'directorate_name' => 'اسم المديرية',

    // ========================================================
    // 4. NETWORKS & POS
    // ========================================================
    'network_id' => 'الشبكة',
    'network_uuid' => 'المعرف الفريد للشبكة',
    'network_holder_name' => 'اسم مدير الشبكة',
    'network_name' => 'اسم الشبكة',
    'network_phone' => 'هاتف الشبكة',
    'network_phone_number' => 'هاتف الشبكة',
    'network_email' => 'البريد الإلكتروني للشبكة',
    'network_address' => 'عنوان الشبكة',
    'network_profile_logo' => 'شعار الشبكة',
    'network_logo' => 'شعار الشبكة',
    'network_currency' => 'عملة الشبكة',
    'approved_by' => 'تم الاعتماد بواسطة',
    'rejected_by' => 'تم الرفض بواسطة',
    'approved_at' => 'تاريخ الاعتماد',
    'rejected_at' => 'تاريخ الرفض',

    'pos_id' => 'نقطة البيع',
    'pos_uuid' => 'المعرف الفريد لنقطة البيع',
    'pos_name' => 'اسم نقطة البيع',
    'pos_holder_name' => 'اسم مدير نقطة البيع',
    'pos_phone' => 'هاتف نقطة البيع',
    'pos_phone_number' => 'هاتف نقطة البيع',
    'pos_email' => 'البريد الإلكتروني لنقطة البيع',
    'pos_address' => 'عنوان نقطة البيع',
    'pos_profile_logo' => 'شعار نقطة البيع',
    'pos_lat' => 'خط العرض',
    'pos_lng' => 'خط الطول',

    'network_pos_id' => 'ارتباط نقطة البيع بالشبكة',
    'assigned_by' => 'تم التعيين بواسطة',

    // ========================================================
    // 5. FINANCIALS, WALLETS & BANK ACCOUNTS
    // ========================================================
    'wallet_id' => 'المحفظة',
    'wallet_uuid' => 'المعرف الفريد للمحفظة',
    'wallet_balance' => 'رصيد المحفظة',
    'balance' => 'الرصيد',
    'credit_limit' => 'الحد الائتماني',
    'currency' => 'العملة',

    'transaction_type' => 'نوع الحركة المالية',
    'amount' => 'المبلغ',
    'previous_balance' => 'الرصيد السابق',
    'current_balance' => 'الرصيد الحالي',
    'reference_type' => 'نوع المرجع المالي',
    'reference_id' => 'رقم المرجع المالي',
    'statement' => 'بيان العملية المالية',
    'note' => 'الملاحظة',

    'log_operation' => 'نوع العملية',
    'log_operation_type' => 'نوع حركة المحفظة',
    'log_amount' => 'مبلغ العملية',
    'log_statement' => 'بيان حركة المحفظة',
    'log_date' => 'تاريخ تسجيل العملية',

    'account_id' => 'الحساب البنكي',
    'bank_name' => 'اسم البنك',
    'account_name' => 'اسم صاحب الحساب',
    'account_holder_name' => 'اسم صاحب الحساب البنكي',
    'account_number' => 'رقم الحساب البنكي',
    'account_type' => 'نوع الحساب البنكي',
    'account_currency' => 'عملة الحساب البنكي',
    'account_balance' => 'رصيد الحساب البنكي',
    'bank_logo' => 'شعار البنك',
    'iban' => 'رقم الآيبان (IBAN)',
    'swift_code' => 'رمز السويفت (SWIFT)',

    // ========================================================
    // 6. VOUCHER PACKAGES & DISCOUNTS
    // ========================================================
    'package_id' => 'باقة الكروت',
    'package_name' => 'اسم الباقة',
    'package_color' => 'لون تمييز الباقة',
    'package_price' => 'سعر الباقة',
    'package_balance' => 'رصيد الباقة',
    'package_uptime' => 'زمن تشغيل الباقة',
    'package_validity' => 'صلاحية الباقة',
    'package_voucher_count' => 'عدد كروت الباقة',
    'package_voucher_price' => 'سعر كرت الباقة',
    'package_default_cash_discount' => 'نسبة الخصم النقدي الافتراضية',
    'package_default_credit_discount' => 'نسبة الخصم الآجل الافتراضية',
    'package_validity_days' => 'مدة الصلاحية بالأيام',
    'package_hours' => 'عدد الساعات المتاحة',
    'package_speed' => 'سرعة الباقة',
    'package_quota_mb' => 'حجم البيانات (ميجابايت)',

    'discount_id' => 'الخصم المخصص',
    'discount_cash' => 'نسبة الخصم النقدي',
    'discount_credit' => 'نسبة الخصم الآجل',

    // ========================================================
    // 7. REQUESTS, SALE INVOICES & EXPENSES
    // ========================================================
    'request_id' => 'الطلب',
    'requested_by' => 'مقدم الطلب',
    'request_uuid' => 'المعرف الفريد للطلب',
    'request_type' => 'نوع الطلب',
    'request_date' => 'تاريخ الطلب',
    'total_amount' => 'المبلغ الإجمالي',
    'payment_type' => 'طريقة الدفع',
    'transaction_reference' => 'رقم الحوالة / السند',
    'transaction_receipt' => 'صورة سند التحويل',
    'rejection_reason' => 'سبب الرفض',
    'reason' => 'سبب العملية',
    'reviewed_by' => 'تم التدقيق بواسطة',
    'review_date' => 'تاريخ التدقيق',
    'request_note' => 'ملاحظة الطلب',

    'item_id' => 'العنصر',
    'quantity' => 'الكمية',
    'voucher_price' => 'سعر الكرت',
    'voucher_count' => 'عدد الكروت',
    'item_voucher_count' => 'عدد كروت البند',
    'unit_price' => 'سعر الوحدة',
    'item_discount' => 'نسبة خصم العنصر',
    'item_total_amount' => 'إجمالي مبلغ البند',
    'total_price' => 'السعر الإجمالي للعنصر',

    'invoice_id' => 'فاتورة المبيعات',
    'invoice_uuid' => 'المعرف الفريد للفاتورة',
    'invoice_type' => 'نوع الفاتورة',
    'invoice_sale_type' => 'نوع مبيعات الفاتورة (نقدي/آجل)',
    'invoice_payment_type' => 'طريقة سداد الفاتورة',
    'invoice_payment_status' => 'حالة سداد الفاتورة',
    'payment_status' => 'حالة السداد',
    'invoice_total_amount' => 'المبلغ الإجمالي للفاتورة',
    'invoice_date' => 'تاريخ الفاتورة',
    'total_discount' => 'إجمالي الخصم',
    'final_amount' => 'المبلغ النهائي الصافي',
    'paid_amount' => 'المبلغ المدفوع',
    'remaining_amount' => 'المبلغ المتبقي',
    'notes' => 'الملاحظات',

    'expense_id' => 'سند المصروف',
    'expense_uuid' => 'المعرف الفريد للمصروف',
    'expense_title' => 'عنوان المصروف',
    'expense_total_amount' => 'إجمالي مبلغ المصروف',
    'expense_date' => 'تاريخ المصروف',
    'item_title' => 'بيان البند',
    'item_statement' => 'بيان بند المصروف',
    'item_amount' => 'مبلغ بند المصروف',

    'attachment' => 'ملف المرفق',
    'attachment_id' => 'المرفق',
    'attachment_name' => 'اسم المرفق',
    'attachment_path' => 'مسار المرفق',
    'attachment_mime' => 'نوع المرفق',
    'attachment_size' => 'حجم المرفق',
    'file_path' => 'مسار الملف',
    'file_name' => 'اسم الملف',
    'file_type' => 'نوع الملف',
    'file_size' => 'حجم الملف',

    'items' => 'قائمة البنود',
    'items.*.package_id' => 'باقة الكروت للبند',
    'items.*.item_voucher_count' => 'عدد كروت البند',
    'items.*.voucher_price' => 'سعر الكرت للبند',
    'items.*.voucher_count' => 'عدد الكروت للبند',
    'items.*.item_discount' => 'نسبة الخصم للبند',
    'items.*.item_statement' => 'بيان البند',
    'items.*.item_amount' => 'مبلغ البند',
    'attachments' => 'المرفقات',
    'attachments.*' => 'ملف المرفق',

    // ========================================================
    // 8. SUBSCRIPTION PLANS & FEATURES
    // ========================================================
    'plan_id' => 'خطة الاشتراك',
    'plan_uuid' => 'المعرف الفريد للخطة',
    'plan_name' => 'اسم الخطة',
    'plan_price' => 'سعر الخطة',
    'plan_discount' => 'نسبة خصم الخطة',
    'plan_validity' => 'مدة صلاحية الخطة (بالأيام)',
    'plan_duration_days' => 'مدة الخطة بالأيام',
    'plan_desc' => 'وصف الخطة',
    'is_free' => 'خطة مجانية',
    'is_popular' => 'خطة شائعة',
    'is_custom' => 'خطة مخصصة',
    'additional_days' => 'عدد الأيام الإضافية',

    'request_total_amount' => 'إجمالي مبلغ طلب الاشتراك',
    'request_payment_type' => 'طريقة دفع طلب الاشتراك',

    'subscription_id' => 'اشتراك المزود',
    'subscription_start_date' => 'تاريخ بدء الاشتراك',
    'subscription_end_date' => 'تاريخ نهاية الاشتراك',

    'feature_id' => 'الميزة',
    'feature_name' => 'اسم الميزة',
    'feature_value_type' => 'نوع قيمة الميزة',
    'feature_value' => 'قيمة الميزة',
    'feature_desc' => 'وصف الميزة',

    'subscription_plan_feature_id' => 'ميزة خطة الاشتراك',
    'plan_feature_value' => 'قيمة الميزة في الخطة',
    'features' => 'ميزات الخطة',
    'features.*.feature_id' => 'الميزة',
    'features.*.plan_feature_value' => 'قيمة الميزة',
    'features.*.status' => 'حالة الميزة في الخطة',

    'consumption_id' => 'سجل الاستهلاك',
    'consumption_value' => 'قيمة الاستهلاك',
    'last_consumption_at' => 'تاريخ آخر استهلاك',

    // ========================================================
    // 9. TICKETS, NOTIFICATIONS & SMS SETTINGS
    // ========================================================
    'ticket_id' => 'تذكرة الدعم',
    'ticket_uuid' => 'المعرف الفريد للتذكرة',
    'ticket_subject' => 'موضوع التذكرة',

    'message_id' => 'رسالة التذكرة',
    'sent_by' => 'المرسل',
    'message_body' => 'نص الرسالة',
    'message' => 'نص الرسالة',
    'sent_at' => 'وقت الإرسال',
    'read_at' => 'وقت القراءة',
    'is_read' => 'حالة القراءة',

    'notification_id' => 'الإشعار',
    'notification_type' => 'نوع الإشعار',
    'notification_title' => 'عنوان الإشعار',
    'notification_message' => 'نص الإشعار',
    'notification_image' => 'صورة الإشعار',
    'title' => 'عنوان الإشعار',
    'body' => 'محتوى الإشعار',
    'image' => 'صورة الإشعار',
    'category' => 'تصنيف الإشعار',
    'channels' => 'قنوات الإرسال',
    'channels.*' => 'قناة الإرسال',
    'audience' => 'الجمهور المستهدف',
    'audience.type' => 'نوع الجمهور المستهدف',
    'audience.role_id' => 'الدور المستهدف',
    'audience.network_id' => 'الشبكة المستهدفة',
    'audience.user_ids' => 'المستخدمون المستهدفون',
    'audience.user_ids.*' => 'المستخدم المستهدف',

    'phone_number' => 'رقم الهاتف',
    'notify_receipt' => 'إشعار سند الاستلام',
    'notify_payment' => 'إشعار سند الصرف',
    'notify_pos_withdraw' => 'إشعار سحب رصيد نقطة البيع',
    'notify_expense' => 'إشعار المصروفات',
    'notify_sale_invoice' => 'إشعار فاتورة المبيعات',
    'notify_voucher_request' => 'إشعار طلب الكروت',
    'notify_low_balance' => 'إشعار انخفاض الرصيد',
    'notify_daily_summary' => 'إشعار التقرير اليومي',
    'notify_monthly_summary' => 'إشعار التقرير الشهري',
    'notify_subscription_expiry' => 'إشعار قرب انتهاء الاشتراك',
    'use_custom_gateway' => 'استخدام بوابة SMS مخصصة',
    'server_type' => 'نوع خادم الرسائل',
    'base_url' => 'رابط بوابة الرسائل (Base URL)',
    'auth_type' => 'نوع المصادقة في البوابة',
    'api_token' => 'رمز مصادقة الـ API',
    'refresh_token' => 'رمز التحديث (Refresh Token)',
    'sim_number' => 'رقم شريحة الإرسال (SIM)',
    'skip_phone_validation' => 'تخطي التحقق من صحة الرقم',
    'device_active_within' => 'المهلة الزمنية لاعتبار الجهاز نشطاً (بالدقائق)',

    // ========================================================
    // 10. SYSTEM SETTINGS & BACKUPS
    // ========================================================
    'setting_id' => 'إعداد النظام',
    'setting_category' => 'تصنيف الإعداد',
    'setting_key' => 'مفتاح الإعداد',
    'setting_value_type' => 'نوع قيمة الإعداد',
    'setting_value' => 'قيمة الإعداد',
    'setting_desc' => 'وصف الإعداد',
    'settings' => 'قائمة الإعدادات',
    'settings.*.key' => 'مفتاح الإعداد',
    'settings.*.value' => 'قيمة الإعداد',

    'backup_job_id' => 'مهمة النسخ الاحتياطي',
    'job_uuid' => 'المعرف الفريد لمهمة النسخ',
    'backup_type' => 'نوع النسخ الاحتياطي',
    'stage' => 'مرحلة التنفيذ',
    'file_size_bytes' => 'حجم ملف النسخة بالبايت',
    'archive_sha256' => 'البصمة الرقمية للنسخة (SHA-256)',
    'drive_file_id' => 'معرف الملف على Google Drive',
    'drive_web_view_link' => 'رابط معاينة الملف على Google Drive',
    'drive_folder_id' => 'معرف مجلد التخزين على Google Drive',
    'manifest_summary' => 'ملخص محتويات الأرشيف',
    'error_message' => 'رسالة الخطأ',
    'started_at' => 'وقت بدء التنفيذ',
    'duration_ms' => 'مدة التنفيذ (بالمللي ثانية)',
    'schedule_time' => 'وقت الجدولة اليومي',
    'retention_count' => 'عدد النسخ الاحتياطية المحتفظ بها',
    'include_storage' => 'تضمين مجلد التخزين العام (Storage)',
    'failure_sms' => 'إرسال تنبيه SMS عند فشل النسخ',
    'client_id' => 'معرف العميل (Client ID)',
    'client_secret' => 'المفتاح السري للعميل (Client Secret)',
    'folder_name' => 'اسم مجلد Google Drive',
    'code' => 'رمز التفويض (OAuth Authorization Code)',
    'state' => 'معامل الحالة الأمني (OAuth State)',

    // ========================================================
    // 11. BATCH OPERATIONS & COMMON UTILITIES
    // ========================================================
    'ids' => 'قائمة المعرفات المحددة',
    'ids.*' => 'المعرف المحدد',
    'status' => 'الحالة',
    'type' => 'النوع',
    'token' => 'رمز المرور / التوكن',
    'created_by' => 'تم الإنشاء بواسطة',
    'created_at' => 'تاريخ الإنشاء',
    'updated_at' => 'تاريخ التحديث',
    'deleted_at' => 'تاريخ الحذف',
    'search' => 'كلمة البحث',
    'per_page' => 'عدد العناصر في الصفحة',
    'page' => 'رقم الصفحة',
];

echo "New attributes count: " . count($newAttributes) . "\n";
