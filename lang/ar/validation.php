<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (العربية)
    |--------------------------------------------------------------------------
    |
    | نصوص رسائل التحقق القياسية لنظام NetSales بدقة وفصاحة عربية كاملة.
    |
    */

    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما يكون :other بقيمة :value.',
    'active_url' => 'حقل :attribute ليس رابطاً صحيحاً.',
    'after' => 'يجب أن يكون تاريخ :attribute بعد :date.',
    'after_or_equal' => 'يجب أن يكون تاريخ :attribute بعد أو مساوياً لـ :date.',
    'alpha' => 'يجب أن يحتوي حقل :attribute على أحرف فقط.',
    'alpha_dash' => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام وشرطات فقط.',
    'alpha_num' => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام فقط.',
    'any_of' => 'قيمة حقل :attribute غير صالحة.',
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'ascii' => 'يجب أن يحتوي حقل :attribute على أحرف ورموز أحادية البايت فقط.',
    'before' => 'يجب أن يكون تاريخ :attribute قبل :date.',
    'before_or_equal' => 'يجب أن يكون تاريخ :attribute قبل أو مساوياً لـ :date.',
    'between' => [
        'array' => 'يجب أن يحتوي حقل :attribute على عدد عناصر بين :min و :max.',
        'file' => 'يجب أن يكون حجم ملف :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'string' => 'يجب أن يكون عدد أحرف :attribute بين :min و :max حرفاً.',
    ],
    'boolean' => 'يجب أن تكون قيمة حقل :attribute إما نعم أو لا (صحيح/خطأ).',
    'can' => 'حقل :attribute يحتوي على قيمة غير مصرح بها.',
    'confirmed' => 'تأكيد حقل :attribute غير متطابق.',
    'contains' => 'حقل :attribute يفتقر إلى القيمة المطلوبة.',
    'current_password' => 'كلمة المرور الحالية غير صحيحة.',
    'date' => 'حقل :attribute ليس تاريخاً صحيحاً.',
    'date_equals' => 'يجب أن يطابق تاريخ :attribute تاريخ :date.',
    'date_format' => 'يجب أن يطابق تنسيق :attribute الصيغة المحددة: :format.',
    'decimal' => 'يجب أن يحتوي حقل :attribute على :decimal منازل عشرية.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما يكون :other بقيمة :value.',
    'different' => 'يجب أن يختلف حقل :attribute عن حقل :other.',
    'digits' => 'يجب أن يتكون حقل :attribute من :digits أرقام.',
    'digits_between' => 'يجب أن يتكون حقل :attribute بين :min و :max رقماً.',
    'dimensions' => 'أبعاد صورة :attribute غير صالحة.',
    'distinct' => 'يحتوي حقل :attribute على قيمة مكررة.',
    'doesnt_contain' => 'يجب ألا يحتوي حقل :attribute على أي من: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي حقل :attribute بأي من: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ حقل :attribute بأي من: :values.',
    'email' => 'يجب أن يكون حقل :attribute بريداً إلكترونياً صحيحاً.',
    'encoding' => 'يجب تشفير حقل :attribute بترميز :encoding.',
    'ends_with' => 'يجب أن ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'enum' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'exists' => 'القيمة المحددة في حقل :attribute غير موجودة في النظام.',
    'extensions' => 'يجب أن يكون امتداد ملف :attribute أحد الامتدادات التالية: :values.',
    'file' => 'يجب أن يكون حقل :attribute ملفاً صالحاً.',
    'filled' => 'حقل :attribute مطلوب ولا يمكن تركه فارغاً.',
    'gt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أكثر من :value عنصراً.',
        'file' => 'يجب أن يكون حجم ملف :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
        'string' => 'يجب أن يتجاوز عدد أحرف :attribute :value حرفاً.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :value عنصراً على الأقل.',
        'file' => 'يجب أن يكون حجم ملف :attribute أكبر من أو مساوياً لـ :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من أو مساوية لـ :value.',
        'string' => 'يجب ألا يقل عدد أحرف :attribute عن :value حرفاً.',
    ],
    'hex_color' => 'يجب أن يكون حقل :attribute لوناً صالحاً بنظام Hex.',
    'image' => 'يجب أن يكون حقل :attribute صورة صالحة.',
    'in' => 'القيمة المحددة في حقل :attribute غير صالحة.',
    'in_array' => 'حقل :attribute يجب أن يكون موجوداً ضمن :other.',
    'in_array_keys' => 'مفتاح حقل :attribute يجب أن يكون موجوداً ضمن مفاتيح :other.',
    'integer' => 'يجب أن يكون حقل :attribute عدداً صحيحاً.',
    'ip' => 'يجب أن يكون حقل :attribute عنوان IP صحيحاً.',
    'ipv4' => 'يجب أن يكون حقل :attribute عنوان IPv4 صحيحاً.',
    'ipv6' => 'يجب أن يكون حقل :attribute عنوان IPv6 صحيحاً.',
    'json' => 'يجب أن يكون حقل :attribute نص JSON صالحاً.',
    'list' => 'يجب أن يكون حقل :attribute قائمة عناصر.',
    'lowercase' => 'يجب أن يكون حقل :attribute بأحرف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أقل من :value عنصراً.',
        'file' => 'يجب أن يكون حجم ملف :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من :value.',
        'string' => 'يجب أن يقل عدد أحرف :attribute عن :value حرفاً.',
    ],
    'lte' => [
        'array' => 'يجب ألا يتجاوز عدد عناصر :attribute :value عنصراً.',
        'file' => 'يجب ألا يتجاوز حجم ملف :attribute :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أقل من أو مساوية لـ :value.',
        'string' => 'يجب ألا يتجاوز عدد أحرف :attribute :value حرفاً.',
    ],
    'mac_address' => 'يجب أن يكون حقل :attribute عنوان MAC صحيحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عنصراً.',
        'file' => 'يجب ألا يتجاوز حجم ملف :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.',
        'string' => 'يجب ألا يتجاوز عدد أحرف :attribute :max حرفاً.',
    ],
    'max_digits' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max أرقام.',
    'mimes' => 'يجب أن يكون حقل :attribute ملفاً من نوع: :values.',
    'mimetypes' => 'يجب أن يكون حقل :attribute ملفاً من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عناصر على الأقل.',
        'file' => 'يجب أن يكون حجم ملف :attribute على الأقل :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة :attribute عن :min.',
        'string' => 'يجب ألا يقل عدد أحرف :attribute عن :min أحرف.',
    ],
    'min_digits' => 'يجب أن يحتوي حقل :attribute على :min أرقام على الأقل.',
    'missing' => 'يجب ألا يكون حقل :attribute موجوداً.',
    'missing_if' => 'يجب ألا يكون حقل :attribute موجوداً عندما يكون :other بقيمة :value.',
    'missing_unless' => 'يجب ألا يكون حقل :attribute موجوداً ما لم يكن :other بقيمة :values.',
    'missing_with' => 'يجب ألا يكون حقل :attribute موجوداً عند وجود :values.',
    'missing_with_all' => 'يجب ألا يكون حقل :attribute موجوداً عند وجود كل من :values.',
    'multiple_of' => 'يجب أن تكون قيمة :attribute مضاعفاً للرقم :value.',
    'not_in' => 'القيمة المحددة في حقل :attribute غير مقبولة.',
    'not_regex' => 'صيغة حقل :attribute غير مقبولة.',
    'numeric' => 'يجب أن يكون حقل :attribute رقماً.',
    'password' => [
        'letters' => 'يجب أن تحتوي كلمة المرور على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي كلمة المرور على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن تحتوي كلمة المرور على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي كلمة المرور على رمز خاص واحد على الأقل.',
        'uncompromised' => 'كلمة المرور المدخلة غير آمنة ومسربة سابقاً، يرجى اختيار كلمة مرور أخرى أكثر أماناً.',
    ],
    'present' => 'يجب أن يكون حقل :attribute موجوداً في الطلب.',
    'present_if' => 'يجب أن يكون حقل :attribute موجوداً عندما يكون :other بقيمة :value.',
    'present_unless' => 'يجب أن يكون حقل :attribute موجوداً ما لم يكن :other بقيمة :value.',
    'present_with' => 'يجب أن يكون حقل :attribute موجوداً عند وجود :values.',
    'present_with_all' => 'يجب أن يكون حقل :attribute موجوداً عند وجود كل من :values.',
    'prohibited' => 'حقل :attribute محظور وممنوع إرساله.',
    'prohibited_if' => 'حقل :attribute محظور عندما يكون :other بقيمة :value.',
    'prohibited_if_accepted' => 'حقل :attribute محظور عندما يتم قبول :other.',
    'prohibited_if_declined' => 'حقل :attribute محظور عندما يتم رفض :other.',
    'prohibited_unless' => 'حقل :attribute محظور ما لم يكن :other ضمن :values.',
    'prohibits' => 'وجود حقل :attribute يمنع إرسال حقل :other.',
    'regex' => 'صيغة حقل :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي حقل :attribute على مفاتيح لـ: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other بقيمة :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب ما لم يكن :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند إدخال :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند إدخال كل من :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم إدخال :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم إدخال أي من :values.',
    'same' => 'يجب أن يتطابق حقل :attribute مع حقل :other.',
    'size' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :size عنصراً بالضبط.',
        'file' => 'يجب أن يكون حجم ملف :attribute :size كيلوبايت بالضبط.',
        'numeric' => 'يجب أن تكون قيمة :attribute مساوية لـ :size بالضبط.',
        'string' => 'يجب أن يتكون حقل :attribute من :size حرفاً بالضبط.',
    ],
    'starts_with' => 'يجب أن يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'string' => 'يجب أن يكون حقل :attribute نصاً.',
    'timezone' => 'يجب أن يكون حقل :attribute نطاقاً زمنياً صحيحاً.',
    'unique' => 'قيمة حقل :attribute مسجلة مسبقاً في النظام.',
    'uploaded' => 'فشل تحميل ملف :attribute.',
    'uppercase' => 'يجب أن تكون أحرف حقل :attribute أحرفاً كبيرة.',
    'url' => 'يجب أن يكون حقل :attribute رابطاً صالحاً.',
    'ulid' => 'يجب أن يكون حقل :attribute معرّف ULID صالحاً.',
    'uuid' => 'يجب أن يكون حقل :attribute معرّف UUID صالحاً.',

    /*
    |--------------------------------------------------------------------------
    | Database & Form Attributes (أسماء الحقول والمدخلات)
    |--------------------------------------------------------------------------
    |
    | ترجمة شاملة لجميع الحقول والمدخلات التابعة لقاعدة البيانات واستمارات الطلب.
    |
    */

    'attributes' => [

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
        'id' => 'المعرف الرقمي',
        'user_id' => 'المستخدم',
        'user_ids' => 'المستخدمون المحددون',
        'user_uuid' => 'المعرف الفريد للمستخدم',
        'user_full_name' => 'الاسم الكامل',
        'full_name' => 'الاسم الكامل',
        'user_phone_number' => 'رقم الهاتف',
        'phone_number' => 'رقم الهاتف',
        'username' => 'اسم المستخدم',
        'user_email' => 'البريد الإلكتروني',
        'email' => 'البريد الإلكتروني',
        'user_password' => 'كلمة المرور',
        'password' => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'new_password' => 'كلمة المرور الجديدة',
        'user_password_confirmation' => 'تأكيد كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'user_is_active' => 'حالة النشاط',
        'user_is_banned' => 'حالة الحظر',
        'user_2fa_enabled' => 'التحقق بخطوتين',
        'user_profile_logo' => 'الصورة الشخصية',
        'profile_picture' => 'الصورة الشخصية',
        'user_login_attempts' => 'محاولات تسجيل الدخول',
        'last_login_at' => 'تاريخ آخر تسجيل دخول',
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
        'expiration' => 'تاريخ انتهاء الصلاحية',
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
        'pos_phone' => 'هاتف نقطة البيع',
        'pos_phone_number' => 'هاتف نقطة البيع',
        'pos_email' => 'البريد الإلكتروني لنقطة البيع',
        'pos_address' => 'عنوان نقطة البيع',
        'pos_profile_logo' => 'شعار نقطة البيع',
        'pos_logo' => 'شعار نقطة البيع',
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
        'request_note' => 'الملاحظة',

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

        'item_id' => 'العنصر',
        'quantity' => 'الكمية',
        'voucher_price' => 'سعر الكرت',
        'voucher_count' => 'عدد الكروت',
        'voucher_count' => 'عدد كروت البند',
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
        'items.*.voucher_count' => 'عدد كروت البند',
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
        // 11. BATCH OPERATIONS, QUEUES & COMMON UTILITIES
        // ========================================================
        'ids' => 'قائمة المعرفات المحددة',
        'ids.*' => 'المعرف المحدد',
        'status' => 'الحالة',
        'type' => 'النوع',
        'name' => 'الاسم',
        'key' => 'المفتاح',
        'value' => 'القيمة',
        'uuid' => 'المعرف الفريد (UUID)',
        'extra' => 'بيانات إضافية',
        'task_type' => 'نوع المهمة',
        'target_date' => 'التاريخ المستهدف',
        'token' => 'رمز المرور / التوكن',
        'tokenable' => 'الكيان المالك للرمز',
        'owner' => 'المالك',
        'abilities' => 'الصلاحيات الممنوحة',
        'queue' => 'طابور المهام (Queue)',
        'payload' => 'حمولة المهمة',
        'attempts' => 'عدد المحاولات',
        'reserved_at' => 'وقت الحجز',
        'available_at' => 'وقت الإتاحة',
        'failed_at' => 'وقت الفشل',
        'cancelled_at' => 'وقت الإلغاء',
        'finished_at' => 'وقت الانتهاء',
        'last_used_at' => 'تاريخ آخر استخدام',
        'total_jobs' => 'إجمالي المهام',
        'pending_jobs' => 'المهام المعلقة',
        'failed_jobs' => 'المهام الفاشلة',
        'failed_job_ids' => 'معرفات المهام الفاشلة',
        'options' => 'خيارات التنفيذ',
        'connection' => 'اتصال قاعدة البيانات أو الطابور',
        'exception' => 'تفاصيل الخطأ الاستثنائي',
        'created_by' => 'تم الإنشاء بواسطة',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',
        'deleted_at' => 'تاريخ الحذف',
        'search' => 'كلمة البحث',
        'per_page' => 'عدد العناصر في الصفحة',
        'page' => 'رقم الصفحة',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages (رسائل مخصصة لاستمارات الطلب)
    |--------------------------------------------------------------------------
    |
    | تخصيص دقيق ومباشر لرسائل الأخطاء لكل Form Request في النظام، ويتم تحميلها
    | آلياً عبر سمة LoadsCustomValidationMessages بمفتاح 'custom.{RequestName}'.
    |
    */

    'custom' => [

        // ========================================================
        // 1. AUTH & SECURITY REQUESTS
        // ========================================================
        'LoginRequest' => [
            'user_phone_number' => [
                'required' => 'يرجى إدخال رقم الهاتف لتسجيل الدخول.',
                'regex' => 'رقم الهاتف يجب أن يبدأ بالرقم 7 ويتكون من 9 أرقام.',
            ],
            'user_password' => [
                'required' => 'كلمة المرور مطلوبة لتسجيل الدخول.',
                'min' => 'يجب ألا تقل كلمة المرور عن 6 أحرف/أرقام.',
            ],
        ],

        'RegisterNetworkRequest' => [
            'network_name' => [
                'required' => 'يرجى إدخال اسم الشبكة.',
                'unique' => 'اسم الشبكة مسجل مسبقاً، يرجى اختيار اسم آخر.',
            ],
            'governorate_id' => [
                'required' => 'يرجى اختيار المحافظة التابعة لها الشبكة.',
                'exists' => 'المحافظة المحددة غير موجودة في النظام.',
            ],
            'directorate_id' => [
                'required' => 'يرجى اختيار المديرية التابعة لها الشبكة.',
                'exists' => 'المديرية المحددة غير موجودة.',
            ],
            'network_phone_number' => [
                'required' => 'رقم هاتف الشبكة مطلوب.',
                'regex' => 'رقم هاتف الشبكة يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.',
            ],
            'user_full_name' => [
                'required' => 'يرجى إدخال الاسم الكامل لمدير الحساب.',
            ],
            'user_phone_number' => [
                'required' => 'رقم هاتف المدير مطلوب.',
                'regex' => 'رقم الهاتف يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.',
                'unique' => 'رقم الهاتف مسجل مسبقاً لحساب آخر.',
            ],
            'user_password' => [
                'required' => 'كلمة المرور مطلوبة.',
                'min' => 'يجب ألا تقل كلمة المرور عن 8 أحرف.',
            ],
            'device_uuid' => [
                'required' => 'المعرف الفريد للجهاز مطلوب لتسجيل الحساب.',
            ],
        ],

        'RegisterPosRequest' => [
            'pos_name' => [
                'required' => 'يرجى إدخال اسم نقطة البيع.',
                'unique' => 'اسم نقطة البيع مسجل مسبقاً، يرجى اختيار اسم آخر.',
            ],
            'governorate_id' => [
                'required' => 'يرجى اختيار المحافظة التابعة لها نقطة البيع.',
                'exists' => 'المحافظة المحددة غير موجودة.',
            ],
            'directorate_id' => [
                'required' => 'يرجى اختيار المديرية التابعة لها نقطة البيع.',
                'exists' => 'المديرية المحددة غير موجودة.',
            ],
            'pos_phone_number' => [
                'required' => 'رقم هاتف نقطة البيع مطلوب.',
            ],
            'user_full_name' => [
                'required' => 'يرجى إدخال الاسم الكامل لمسؤول الحساب.',
            ],
            'user_phone_number' => [
                'required' => 'رقم هاتف مسؤول نقطة البيع مطلوب.',
                'unique' => 'رقم الهاتف مسجل مسبقاً لحساب آخر.',
            ],
            'user_password' => [
                'required' => 'كلمة المرور مطلوبة.',
                'min' => 'يجب ألا تقل كلمة المرور عن 8 أحرف.',
            ],
            'device_uuid' => [
                'required' => 'المعرف الفريد للجهاز مطلوب لتسجيل نقطة البيع.',
            ],
        ],

        'RequestPasswordResetRequest' => [
            'user_phone_number' => [
                'required' => 'يرجى إدخال رقم الهاتف لإعادة تعيين كلمة المرور.',
                'regex' => 'رقم الهاتف يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.',
            ],
        ],

        'ResetPasswordRequest' => [
            'user_phone_number' => [
                'required' => 'رقم الهاتف مطلوب.',
                'regex' => 'رقم الهاتف يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.',
            ],
            'otp' => [
                'required' => 'رمز التحقق مطلوب لإعادة التعيين.',
                'digits' => 'يجب أن يتكون رمز التحقق من 6 أرقام.',
            ],
            'password' => [
                'required' => 'يرجى إدخال كلمة المرور الجديدة.',
                'min' => 'يجب ألا تقل كلمة المرور عن 6 أحرف.',
                'confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            ],
        ],

        'UpdatePasswordRequest' => [
            'current_password' => [
                'required' => 'يرجى إدخال كلمة المرور الحالية.',
            ],
            'new_password' => [
                'required' => 'يرجى إدخال كلمة المرور الجديدة.',
                'min' => 'يجب ألا تقل كلمة المرور الجديدة عن 6 أحرف.',
                'confirmed' => 'تأكيد كلمة المرور الجديدة غير متطابق.',
            ],
        ],

        'VerifyOtpRequest' => [
            'user_phone_number' => [
                'required' => 'رقم الهاتف مطلوب للتحقق من رمز OTP.',
                'regex' => 'رقم الهاتف يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.',
            ],
            'otp' => [
                'required' => 'يرجى إدخال رمز التحقق المستلم.',
            ],
        ],

        'VerifyResetOtpRequest' => [
            'user_phone_number' => [
                'required' => 'رقم الهاتف مطلوب للتحقق من الرمز.',
                'regex' => 'رقم الهاتف يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.',
            ],
            'otp' => [
                'required' => 'رمز التحقق مطلوب.',
            ],
        ],

        'GenerateOtpRequest' => [
            'user_phone_number' => [
                'required' => 'رقم الهاتف مطلوب لإرسال رمز التحقق.',
                'regex' => 'رقم الهاتف يجب أن يبدأ بـ 7 ويتكون من 9 أرقام.',
            ],
            'otp_type' => [
                'required' => 'يرجى تحديد الغرض من طلب رمز التحقق.',
            ],
        ],

        'ToggleTwoFactorRequest' => [
            'enable' => [
                'required' => 'يرجى تحديد حالة التفعيل المطلوبة للتحقق بخطوتين.',
            ],
        ],

        'VerifyTwoFactorRequest' => [
            'otp' => [
                'required' => 'رمز التحقق بخطوتين مطلوب.',
            ],
        ],

        // ========================================================
        // 2. USER & SECURITY MANAGEMENT
        // ========================================================
        'StoreUserRequest' => [
            'role_id' => [
                'required' => 'يرجى اختيار دور المستخدم من النظام.',
                'exists' => 'الدور المحدد للمستخدم غير موجود.',
            ],
            'user_full_name' => [
                'required' => 'يرجى إدخال الاسم الكامل للمستخدم.',
                'max' => 'يجب ألا يتجاوز الاسم الكامل 100 حرف.',
            ],
            'user_phone_number' => [
                'required' => 'رقم هاتف المستخدم مطلوب.',
                'regex' => 'صيغة رقم الهاتف غير صالحة، يرجى إدخال رقم صحيح.',
                'unique' => 'رقم الهاتف مسجل مسبقاً لمستخدم آخر.',
            ],
            'user_password' => [
                'required' => 'كلمة المرور مطلوبة.',
                'min' => 'يجب ألا تقل كلمة المرور عن 8 أحرف.',
            ],
            'user_email' => [
                'email' => 'صيغة البريد الإلكتروني غير صحيحة.',
                'unique' => 'البريد الإلكتروني مسجل مسبقاً لمستخدم آخر.',
            ],
        ],

        'UpdateUserRequest' => [
            'user_phone_number' => [
                'unique' => 'رقم الهاتف مسجل مسبقاً لمستخدم آخر.',
            ],
            'user_email' => [
                'unique' => 'البريد الإلكتروني مسجل مسبقاً لمستخدم آخر.',
            ],
        ],

        'UpdateProfilePictureRequest' => [
            'profile_picture' => [
                'required' => 'يرجى اختيار صورة صالحة للتحميل.',
                'image' => 'يجب أن يكون الملف المحمل صورة صالحة.',
            ],
        ],

        'SyncPermissionsRequest' => [
            'permissions' => [
                'required' => 'يرجى تحديد مصفوفة الصلاحيات المراد مزامنتها.',
            ],
        ],

        'StoreUserDeviceRequest' => [
            'device_uuid' => [
                'required' => 'المعرف الفريد للجهاز مطلوب.',
            ],
        ],

        'StoreSecurityBanRequest' => [
            'user_id' => [
                'required' => 'يرجى تحديد المستخدم المراد تطبيق الحظر عليه.',
                'exists' => 'المستخدم المراد حظره غير موجود في النظام.',
            ],
            'ban_type' => [
                'required' => 'نوع الحظر مطلوب.',
            ],
            'ban_reason' => [
                'required' => 'يرجى تدوين سبب الحظر الأمني.',
            ],
            'end_at' => [
                'after' => 'تاريخ انتهاء الحظر يجب أن يكون بعد تاريخ البدء.',
            ],
        ],

        'UpdateSecurityBanRequest' => [
            'end_at' => [
                'after' => 'تاريخ انتهاء الحظر يجب أن يكون بعد تاريخ البدء.',
            ],
        ],

        // ========================================================
        // 3. GEOGRAPHY, NETWORKS & POS
        // ========================================================
        'StoreGovernorateRequest' => [
            'governorate_name' => [
                'required' => 'يرجى إدخال اسم المحافظة.',
                'unique' => 'اسم المحافظة مسجل مسبقاً في النظام.',
            ],
        ],

        'UpdateGovernorateRequest' => [
            'governorate_name' => [
                'unique' => 'اسم المحافظة مسجل مسبقاً في النظام.',
            ],
        ],

        'StoreDirectorateRequest' => [
            'governorate_id' => [
                'required' => 'يرجى تحديد المحافظة التابعة لها المديرية.',
                'exists' => 'المحافظة المحددة غير موجودة.',
            ],
            'directorate_name' => [
                'required' => 'يرجى إدخال اسم المديرية.',
                'unique' => 'اسم المديرية مسجل مسبقاً ضمن هذه المحافظة.',
            ],
        ],

        'UpdateDirectorateRequest' => [
            'directorate_name' => [
                'unique' => 'اسم المديرية مسجل مسبقاً ضمن هذه المحافظة.',
            ],
        ],

        'StoreNetworkRequest' => [
            'directorate_id' => [
                'required' => 'يرجى تحديد المديرية التابعة لها الشبكة.',
                'exists' => 'المديرية المحددة غير موجودة في النظام.',
            ],
            'network_name' => [
                'required' => 'يرجى إدخال اسم الشبكة.',
                'unique' => 'اسم الشبكة مسجل مسبقاً، يرجى اختيار اسم آخر.',
            ],
            'network_phone' => [
                'required' => 'هاتف التواصل مع الشبكة مطلوب.',
                'regex' => 'صيغة هاتف الشبكة غير صحيحة.',
            ],
        ],

        'UpdateNetworkRequest' => [
            'network_name' => [
                'unique' => 'اسم الشبكة مسجل مسبقاً في النظام.',
            ],
        ],

        'StorePosRequest' => [
            'directorate_id' => [
                'required' => 'يرجى تحديد المديرية التابعة لها نقطة البيع.',
                'exists' => 'المديرية المحددة غير موجودة.',
            ],
            'pos_name' => [
                'required' => 'يرجى إدخال اسم نقطة البيع.',
                'unique' => 'اسم نقطة البيع مسجل مسبقاً، يرجى اختيار اسم فريد.',
            ],
            'pos_phone_number' => [
                'required' => 'رقم هاتف نقطة البيع مطلوب.',
                'regex' => 'صيغة هاتف نقطة البيع غير صحيحة.',
            ],
        ],

        'UpdatePosRequest' => [
            'pos_name' => [
                'unique' => 'اسم نقطة البيع مسجل مسبقاً في النظام.',
            ],
        ],

        'StoreNetworkPosRequest' => [
            'network_id' => [
                'required' => 'الشبكة مطلوبة لربط نقطة البيع.',
                'exists' => 'الشبكة المحددة غير موجودة.',
            ],
            'pos_id' => [
                'required' => 'نقطة البيع مطلوبة للربط بالشبكة.',
                'exists' => 'نقطة البيع المحددة غير موجودة.',
                'unique' => 'نقطة البيع مرتبطة بهذه الشبكة بالفعل.',
            ],
        ],

        // ========================================================
        // 4. FINANCIALS, WALLETS & BANK ACCOUNTS
        // ========================================================
        'StoreNetworkWalletRequest' => [
            'network_id' => [
                'required' => 'يرجى تحديد الشبكة التابعة لها المحفظة.',
                'unique' => 'يوجد محفظة مسجلة مسبقاً لهذه الشبكة.',
            ],
            'wallet_balance' => [
                'min' => 'رصيد المحفظة لا يمكن أن يكون سالباً.',
            ],
        ],

        'StorePosWalletRequest' => [
            'network_id' => [
                'required' => 'يرجى تحديد الشبكة التابعة لها محفظة نقطة البيع.',
            ],
            'pos_id' => [
                'required' => 'يرجى تحديد نقطة البيع التابعة لها المحفظة.',
                'unique' => 'يوجد محفظة مسجلة مسبقاً لنقطة البيع هذه ضمن الشبكة المحددة.',
            ],
            'wallet_balance' => [
                'min' => 'رصيد المحفظة لا يمكن أن يكون سالباً.',
            ],
        ],

        'DepositNetworkWalletRequest' => [
            'amount' => [
                'required' => 'يرجى إدخال مبلغ الإيداع.',
                'min' => 'يجب أن يكون مبلغ الإيداع أكبر من الصفر.',
            ],
            'statement' => [
                'required' => 'يرجى تدوين بيان حركة الإيداع.',
            ],
        ],

        'WithdrawNetworkWalletRequest' => [
            'amount' => [
                'required' => 'يرجى إدخال مبلغ السحب.',
                'min' => 'يجب أن يكون مبلغ السحب أكبر من الصفر.',
            ],
            'statement' => [
                'required' => 'يرجى تدوين بيان حركة السحب.',
            ],
        ],

        'DepositPosWalletRequest' => [
            'amount' => [
                'required' => 'يرجى إدخال مبلغ الإيداع.',
                'min' => 'يجب أن يكون مبلغ الإيداع أكبر من الصفر.',
            ],
            'statement' => [
                'required' => 'يرجى تدوين بيان حركة الإيداع.',
            ],
        ],

        'WithdrawPosWalletRequest' => [
            'amount' => [
                'required' => 'يرجى إدخال مبلغ السحب.',
                'min' => 'يجب أن يكون مبلغ السحب أكبر من الصفر.',
            ],
            'statement' => [
                'required' => 'يرجى تدوين بيان حركة السحب.',
            ],
        ],

        'TransferNetworkToPosWalletRequest' => [
            'network_id' => [
                'required' => 'الشبكة المصدرة للتحويل مطلوبة.',
            ],
            'pos_id' => [
                'required' => 'نقطة البيع المستلمة للرصيد مطلوبة.',
            ],
            'amount' => [
                'required' => 'مبلغ التحويل مطلوب.',
                'min' => 'يجب أن يكون مبلغ التحويل أكبر من الصفر.',
            ],
        ],

        'TransferPosToNetworkWalletRequest' => [
            'network_id' => [
                'required' => 'الشبكة المستلمة للتحويل مطلوبة.',
            ],
            'pos_id' => [
                'required' => 'نقطة البيع المصدرة للتحويل مطلوبة.',
            ],
            'amount' => [
                'required' => 'مبلغ التحويل مطلوب.',
                'min' => 'يجب أن يكون مبلغ التحويل أكبر من الصفر.',
            ],
        ],

        'StoreBankAccountRequest' => [
            'bank_name' => [
                'required' => 'يرجى إدخال اسم البنك أو المصرف.',
            ],
            'account_holder_name' => [
                'required' => 'يرجى إدخال اسم صاحب الحساب البنكي.',
            ],
            'account_number' => [
                'required' => 'يرجى إدخال رقم الحساب البنكي.',
                'unique' => 'رقم الحساب البنكي مسجل مسبقاً في النظام.',
            ],
            'account_currency' => [
                'required' => 'يرجى تحديد عملة الحساب البنكي.',
            ],
        ],

        'UpdateBankAccountRequest' => [
            'account_number' => [
                'unique' => 'رقم الحساب البنكي مسجل مسبقاً في النظام.',
            ],
        ],

        // ========================================================
        // 5. VOUCHER PACKAGES & DISCOUNTS
        // ========================================================
        'StoreVoucherPackageRequest' => [
            'network_id' => [
                'required' => 'يرجى تحديد الشبكة المالكة للباقة.',
                'exists' => 'الشبكة المحددة غير موجودة.',
            ],
            'package_name' => [
                'required' => 'يرجى إدخال اسم باقة الكروت.',
                'unique' => 'اسم الباقة مسجل مسبقاً ضمن هذه الشبكة.',
            ],
            'package_voucher_price' => [
                'required' => 'سعر كرت الباقة مطلوب.',
                'min' => 'سعر كرت الباقة يجب ألا يقل عن 0.',
            ],
            'package_default_cash_discount' => [
                'required' => 'نسبة الخصم النقدي الافتراضية للباقة مطلوبة.',
                'between' => 'نسبة الخصم النقدي يجب أن تكون نسبة مئوية محصورة بين 0 و 100%.',
            ],
            'package_default_credit_discount' => [
                'required' => 'نسبة الخصم الآجل الافتراضية للباقة مطلوبة.',
                'between' => 'نسبة الخصم الآجل يجب أن تكون نسبة مئوية محصورة بين 0 و 100%.',
            ],
        ],

        'UpdateVoucherPackageRequest' => [
            'package_default_cash_discount' => [
                'between' => 'نسبة الخصم النقدي يجب أن تكون نسبة مئوية محصورة بين 0 و 100%.',
            ],
            'package_default_credit_discount' => [
                'between' => 'نسبة الخصم الآجل يجب أن تكون نسبة مئوية محصورة بين 0 و 100%.',
            ],
        ],

        'StoreVoucherPackageDiscountRequest' => [
            'package_id' => [
                'required' => 'يرجى تحديد الباقة لتطبيق الخصم المخصص.',
            ],
            'pos_id' => [
                'required' => 'يرجى تحديد نقطة البيع الممنوحة للخصم.',
                'unique' => 'يوجد خصم مخصص مسجل مسبقاً لنقطة البيع هذه على نفس الباقة.',
            ],
            'discount_cash' => [
                'required' => 'نسبة الخصم النقدي المخصص مطلوبة.',
                'between' => 'نسبة الخصم النقدي يجب أن تكون نسبة مئوية محصورة بين 0 و 100%.',
            ],
            'discount_credit' => [
                'required' => 'نسبة الخصم الآجل المخصص مطلوبة.',
                'between' => 'نسبة الخصم الآجل يجب أن تكون نسبة مئوية محصورة بين 0 و 100%.',
            ],
        ],

        'UpdateVoucherPackageDiscountRequest' => [
            'discount_cash' => [
                'between' => 'نسبة الخصم النقدي يجب أن تكون نسبة مئوية محصورة بين 0 و 100%.',
            ],
            'discount_credit' => [
                'between' => 'نسبة الخصم الآجل يجب أن تكون نسبة مئوية محصورة بين 0 و 100%.',
            ],
        ],

        // ========================================================
        // 6. REQUESTS, SALE INVOICES & EXPENSES
        // ========================================================
        'StoreRequestRequest' => [
            'network_id' => [
                'required' => 'يرجى تحديد الشبكة الموجه إليها الطلب.',
            ],
            'pos_id' => [
                'required' => 'يرجى تحديد نقطة البيع المصدرة للطلب.',
            ],
            'items' => [
                'array' => 'قائمة البنود يجب أن تكون في مصفوفة صالحة.',
            ],
        ],

        'StoreRequestItemRequest' => [
            'package_id' => [
                'required' => 'يرجى تحديد باقة الكروت المطلوبة.',
            ],
            'voucher_count' => [
                'required' => 'كمية الكروت المطلوبة إلزامية.',
                'min' => 'يجب أن تكون الكمية كرت واحد على الأقل.',
            ],
        ],

        'StoreSaleInvoiceRequest' => [
            'network_id' => [
                'required' => 'الشبكة المصدرة للفاتورة مطلوبة.',
            ],
            'pos_id' => [
                'required' => 'نقطة البيع الصادرة لها الفاتورة مطلوبة.',
            ],
            'items' => [
                'required' => 'يجب إدراج بند واحد على الأقل في فاتورة المبيعات.',
                'min' => 'يجب أن تحتوي الفاتورة على بند مبيعات واحد على الأقل.',
            ],
            'paid_amount' => [
                'min' => 'المبلغ المدفوع لا يمكن أن يكون سالباً.',
            ],
        ],

        'UpdateSaleInvoiceRequest' => [
            'invoice_sale_type' => [
                'required' => 'نوع مبيعات الفاتورة (نقدي/آجل) مطلوب.',
            ],
        ],

        'StoreSaleInvoiceItemRequest' => [
            'package_id' => [
                'required' => 'باقة الكروت المباعة مطلوبة.',
            ],
            'voucher_price' => [
                'required' => 'سعر الكرت المباع مطلوب.',
                'min' => 'سعر الكرت لا يمكن أن يكون سالباً.',
            ],
            'voucher_count' => [
                'required' => 'عدد الكروت المباعة مطلوب.',
                'min' => 'يجب بيع كرت واحد على الأقل.',
            ],
            'item_discount' => [
                'between' => 'نسبة خصم العنصر يجب أن تكون نسبة مئوية بين 0 و 100%.',
            ],
        ],

        'StoreExpenseRequest' => [
            'network_id' => [
                'required' => 'الشبكة المسجل عليها المصروف مطلوبة.',
            ],
            'expense_date' => [
                'date' => 'تاريخ تسجيل المصروف غير صحيح.',
            ],
            'items' => [
                'required' => 'يجب إدراج بند واحد على الأقل للمصروف.',
                'min' => 'يجب أن يحتوي المصروف على بند واحد على الأقل.',
            ],
        ],

        'StoreExpenseItemRequest' => [
            'item_statement' => [
                'required' => 'بيان بند المصروف مطلوب.',
            ],
            'item_amount' => [
                'required' => 'مبلغ بند المصروف مطلوب.',
                'min' => 'مبلغ البند يجب أن يكون أكبر من الصفر.',
            ],
        ],

        'StoreExpenseAttachmentRequest' => [
            'attachment' => [
                'required' => 'ملف المرفق مطلوب.',
                'file' => 'المرفق يجب أن يكون ملفاً صالحاً.',
            ],
        ],

        // ========================================================
        // 7. SUBSCRIPTION PLANS & FEATURES
        // ========================================================
        'StoreSubscriptionPlanRequest' => [
            'plan_name' => [
                'required' => 'يرجى إدخال اسم خطة الاشتراك.',
                'unique' => 'اسم خطة الاشتراك مسجل مسبقاً.',
            ],
            'plan_price' => [
                'required' => 'سعر خطة الاشتراك مطلوب.',
                'min' => 'سعر الخطة لا يمكن أن يكون سالباً.',
            ],
            'plan_discount' => [
                'between' => 'نسبة خصم خطة الاشتراك يجب أن تكون محصورة بين 0 و 100%.',
            ],
            'plan_validity' => [
                'required' => 'مدة صلاحية الخطة بالأيام مطلوبة.',
                'min' => 'مدة الخطة يجب أن تكون يوماً واحداً على الأقل.',
            ],
        ],

        'UpdateSubscriptionPlanRequest' => [
            'plan_discount' => [
                'between' => 'نسبة خصم خطة الاشتراك يجب أن تكون محصورة بين 0 و 100%.',
            ],
        ],

        'StoreSubscriptionPlanRequestRequest' => [
            'network_id' => [
                'required' => 'الشبكة الطالبة للاشتراك مطلوبة.',
            ],
            'plan_id' => [
                'required' => 'خطة الاشتراك المراد الترقية إليها مطلوبة.',
            ],
            'request_payment_type' => [
                'required' => 'طريقة دفع طلب الاشتراك مطلوبة.',
            ],
        ],

        'StoreProviderSubscriptionRequest' => [
            'network_id' => [
                'required' => 'الشبكة المشتركة مطلوبة.',
            ],
            'plan_id' => [
                'required' => 'خطة الاشتراك مطلوبة.',
            ],
            'subscription_start_date' => [
                'required' => 'تاريخ بدء الاشتراك مطلوب.',
            ],
            'subscription_end_date' => [
                'required' => 'تاريخ انتهاء الاشتراك مطلوب.',
                'after_or_equal' => 'تاريخ انتهاء الاشتراك يجب أن يكون مساوياً أو بعد تاريخ البدء.',
            ],
        ],

        'ExtendSubscriptionRequest' => [
            'additional_days' => [
                'required' => 'عدد الأيام الإضافية لتمديد الاشتراك مطلوب.',
                'min' => 'يجب تمديد الاشتراك بيوم واحد على الأقل.',
            ],
        ],

        'StoreFeatureRequest' => [
            'feature_name' => [
                'required' => 'اسم الميزة مطلوب.',
                'unique' => 'اسم الميزة مسجل مسبقاً في النظام.',
            ],
            'feature_value_type' => [
                'required' => 'نوع قيمة الميزة مطلوب.',
            ],
        ],

        'UpdateFeatureRequest' => [
            'feature_name' => [
                'unique' => 'اسم الميزة مسجل مسبقاً في النظام.',
            ],
        ],

        'StoreSubscriptionPlanFeatureRequest' => [
            'feature_id' => [
                'required' => 'الميزة مطلوبة.',
                'unique' => 'هذه الميزة مضافة مسبقاً لنفس خطة الاشتراك.',
            ],
            'plan_feature_value' => [
                'required' => 'قيمة الميزة المحددة للخطة مطلوبة.',
            ],
        ],

        // ========================================================
        // 8. RBAC, TICKETS, NOTIFICATIONS & SETTINGS
        // ========================================================
        'StoreRoleRequest' => [
            'role_key' => [
                'required' => 'مفتاح الدور البرمجي مطلوب.',
                'unique' => 'مفتاح الدور مسجل مسبقاً في النظام.',
                'regex' => 'مفتاح الدور يجب أن يحتوي على أحرف صغيرة وأرقام وشرطة سفلية فقط.',
            ],
            'role_name' => [
                'required' => 'اسم الدور مطلوب.',
            ],
        ],

        'UpdateRoleRequest' => [
            'role_key' => [
                'unique' => 'مفتاح الدور مسجل مسبقاً في النظام.',
            ],
        ],

        'StorePermissionRequest' => [
            'permission_key' => [
                'required' => 'مفتاح الصلاحية مطلوب.',
                'unique' => 'مفتاح الصلاحية مسجل مسبقاً في النظام.',
            ],
            'permission_name' => [
                'required' => 'اسم الصلاحية مطلوب.',
            ],
            'module_id' => [
                'required' => 'الوحدة النمطية التابعة لها الصلاحية مطلوبة.',
            ],
            'action_type_id' => [
                'required' => 'نوع العملية التابعة لها الصلاحية مطلوب.',
            ],
        ],

        'UpdatePermissionRequest' => [
            'permission_key' => [
                'unique' => 'مفتاح الصلاحية مسجل مسبقاً في النظام.',
            ],
        ],

        'StoreRolePermissionRequest' => [
            'permission_id' => [
                'unique' => 'هذه الصلاحية مرتبطة بهذا الدور مسبقاً.',
            ],
        ],

        'StoreUserPermissionRequest' => [
            'permission_id' => [
                'unique' => 'هذه الصلاحية ممنوحة لهذا المستخدم مسبقاً.',
            ],
        ],

        'StoreModuleRequest' => [
            'module_key' => [
                'required' => 'مفتاح الوحدة النمطية مطلوب.',
                'unique' => 'مفتاح الوحدة النمطية مسجل مسبقاً.',
            ],
            'module_name' => [
                'required' => 'اسم الوحدة النمطية مطلوب.',
            ],
        ],

        'StoreActionTypeRequest' => [
            'action_type_key' => [
                'required' => 'مفتاح نوع العملية مطلوب.',
                'unique' => 'مفتاح نوع العملية مسجل مسبقاً.',
            ],
            'action_type_name' => [
                'required' => 'اسم نوع العملية مطلوب.',
            ],
        ],

        'StoreSupportTicketRequest' => [
            'network_id' => [
                'required' => 'الشبكة الموجهة إليها التذكرة مطلوبة.',
            ],
            'ticket_subject' => [
                'required' => 'يرجى إدخال موضوع تذكرة الدعم.',
            ],
        ],

        'StoreSupportTicketMessageRequest' => [
            'message_body' => [
                'required' => 'يرجى كتابة نص الرسالة.',
            ],
        ],

        'StoreNotificationRequest' => [
            'user_id' => [
                'required' => 'المستخدم المستهدف بالإشعار مطلوب.',
            ],
            'notification_type' => [
                'required' => 'نوع الإشعار مطلوب.',
            ],
            'notification_title' => [
                'required' => 'عنوان الإشعار مطلوب.',
            ],
            'notification_message' => [
                'required' => 'نص الإشعار مطلوب.',
            ],
        ],

        'SendAdminNotificationRequest' => [
            'audience' => [
                'required' => 'يرجى تحديد الجمهور المستهدف بالإشعار.',
            ],
            'channels' => [
                'required' => 'يرجى اختيار قناة إرسال واحدة على الأقل.',
                'min' => 'يجب تحديد قناة إرسال واحدة على الأقل.',
            ],
            'title' => [
                'required' => 'عنوان الإشعار مطلوب.',
                'max' => 'يجب ألا يتجاوز عنوان الإشعار 200 حرف.',
            ],
            'body' => [
                'required' => 'محتوى نص الإشعار مطلوب.',
                'max' => 'يجب ألا يتجاوز نص الإشعار 5000 حرف.',
            ],
        ],

        'SendUserDirectNotificationRequest' => [
            'user_id' => [
                'required' => 'المستخدم المستهدف بالإشعار مطلوب.',
                'exists' => 'المستخدم المحدد غير موجود في النظام.',
            ],
            'title' => [
                'required' => 'عنوان الإشعار مطلوب.',
            ],
            'body' => [
                'required' => 'نص الإشعار مطلوب.',
            ],
        ],

        'UpdateFcmTokenRequest' => [
            'device_uuid' => [
                'required' => 'المعرف الفريد للجهاز مطلوب لتحديث رمز الإشعارات.',
            ],
            'fcm_token' => [
                'required' => 'رمز إشعارات FCM مطلوب.',
                'min' => 'رمز إشعارات FCM غير صالح (قصير جداً).',
            ],
        ],

        'StoreSystemSettingRequest' => [
            'setting_category' => [
                'required' => 'تصنيف الإعداد مطلوب.',
            ],
            'setting_key' => [
                'required' => 'مفتاح الإعداد مطلوب.',
                'unique' => 'مفتاح الإعداد مسجل مسبقاً في النظام.',
            ],
            'setting_value_type' => [
                'required' => 'نوع قيمة الإعداد مطلوب.',
            ],
        ],

        'UpdateSystemSettingRequest' => [
            'setting_key' => [
                'unique' => 'مفتاح الإعداد مسجل مسبقاً في النظام.',
            ],
        ],

        'BatchUpdateSystemSettingRequest' => [
            'settings' => [
                'required' => 'قائمة الإعدادات المراد تحديثها مطلوبة.',
                'min' => 'يجب إرسال إعداد واحد على الأقل للتحديث.',
            ],
        ],

        'BatchIdsRequest' => [
            'ids' => [
                'required' => 'يرجى تحديد عنصر واحد على الأقل لتنفيذ العملية المجمعة.',
                'min' => 'يجب تحديد معرف واحد على الأقل.',
            ],
        ],

        'BatchStatusRequest' => [
            'ids' => [
                'required' => 'يرجى تحديد عنصر واحد على الأقل لتحديث حالته.',
                'min' => 'يجب تحديد معرف واحد على الأقل.',
            ],
            'status' => [
                'required' => 'يرجى تحديد الحالة الجديدة المطلوبة.',
            ],
        ],

        'RejectRequest' => [
            'rejection_reason' => [
                'required' => 'يرجى تدوين سبب الرفض بالتفصيل.',
                'min' => 'سبب الرفض يجب ألا يقل عن 3 أحرف.',
            ],
        ],

        'UpdateBackupConfigRequest' => [
            'schedule_time' => [
                'required' => 'وقت الجدولة اليومي للنسخ الاحتياطي مطلوب.',
            ],
            'retention_count' => [
                'required' => 'عدد النسخ المحتفظ بها مطلوب.',
                'min' => 'يجب الاحتفاظ بنسخة احتياطية واحدة على الأقل.',
            ],
        ],

        'DriveTokenRequest' => [
            'code' => [
                'required' => 'رمز التفويض من Google Drive مطلوب لإتمام الربط.',
            ],
        ],

        'TestNetworkSmsRequest' => [
            'phone_number' => [
                'required' => 'رقم الهاتف المراد إرسال رسالة الاختبار إليه مطلوب.',
            ],
            'message' => [
                'required' => 'نص رسالة الاختبار التجريبية مطلوب.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Values (ترجمة قيم محددة للحقول)
    |--------------------------------------------------------------------------
    |
    | ترجمة القيم المستخدمة في الشروط الشرطية مثل required_if وغيرها.
    |
    */

    'values' => [
        'status' => [
            'ACTIVE' => 'نشط',
            'INACTIVE' => 'غير نشط',
            'SUSPENDED' => 'معلق',
            'BANNED' => 'محظور',
            'PENDING' => 'قيد الانتظار',
            'APPROVED' => 'معتمد',
            'REJECTED' => 'مرفوض',
            'CANCELLED' => 'ملغي',
            'COMPLETED' => 'مكتمل',
            'FAILED' => 'فاشل',
        ],
        'request_payment_type' => [
            'CASH' => 'نقداً',
            'BANK' => 'تحويل بنكي',
            'WALLET' => 'خصم من المحفظة',
            'CREDIT' => 'آجل',
        ],
        'invoice_sale_type' => [
            'CASH' => 'نقدي',
            'CREDIT' => 'آجل',
        ],
        'audience' => [
            'type' => [
                'all' => 'الكل',
                'role' => 'دور محدد',
                'network' => 'شبكة محددة',
                'users' => 'مستخدمون محددون',
            ],
        ],
    ],

];