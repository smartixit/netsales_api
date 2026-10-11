<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (العربية)
    |--------------------------------------------------------------------------
    |
    | نصوص رسائل التحقق الافتراضية للنظام بدقة وفصاحة عربية كاملة.
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
    'boolean' => 'يجب أن تكون قيمة حقل :attribute إما نعم أو لا (true/false).',
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
    'file' => 'يجب أن يكون حقل :attribute ملفاً.',
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
        'uncompromised' => 'كلمة المرور المدخلة غير آمنة ومسربة سابقاً، يرجى اختيار كلمة مرور أخرى.',
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
    | Database Attributes (أسماء حقول قاعدة بيانات NetSales)
    |--------------------------------------------------------------------------
    |
    | ترجمة شاملة لجميع الحقول والمدخلات التابعة لجداول قاعدة بيانات NetSales بالكامل.
    |
    */

    'attributes' => [

        // ========================================================
        // 1. RBAC & PERMISSIONS (الوحدات، الإجراءات، الصلاحيات)
        // ========================================================
        'module_id' => 'معرف الوحدة',
        'module_key' => 'مفتاح الوحدة',
        'module_name' => 'اسم الوحدة',
        'module_icon' => 'أيقونة الوحدة',
        'module_description' => 'وصف الوحدة',

        'action_type_id' => 'نوع الإجراء',
        'action_type_key' => 'مفتاح نوع الإجراء',
        'action_type_name' => 'اسم نوع الإجراء',

        'role_id' => 'الدور',
        'role_key' => 'مفتاح الدور',
        'role_name' => 'اسم الدور',
        'role_description' => 'وصف الدور',
        'is_system' => 'إعداد نظام',

        'permission_id' => 'الصلاحية',
        'permission_key' => 'مفتاح الصلاحية',
        'permission_name' => 'اسم الصلاحية',
        'permission_description' => 'وصف الصلاحية',

        'role_permission_id' => 'صلاحية الدور',
        'user_permission_id' => 'صلاحية المستخدم',

        // ========================================================
        // 2. GEOGRAPHY & CENTERS (الجغرافيا ومراكز الخدمة)
        // ========================================================
        'governorate_id' => 'المحافظة',
        'governorate_name' => 'اسم المحافظة',

        'directorate_id' => 'المديرية',
        'directorate_name' => 'اسم المديرية',

        'center_id' => 'المركز',
        'center_code' => 'كود المركز',
        'center_name' => 'اسم المركز',
        'center_phone' => 'هاتف المركز',
        'center_address' => 'عنوان المركز',
        'center_logo' => 'شعار المركز',
        'center_profile_picture' => 'شعار أو صورة المركز',

        // ========================================================
        // 3. USERS, SESSIONS & SECURITY (المستخدمون، الأجهزة، والأمان)
        // ========================================================
        'user_id' => 'المستخدم',
        'user_phone_number' => 'رقم الهاتف',
        'user_email' => 'البريد الإلكتروني',
        'user_password' => 'كلمة المرور',
        'user_password_confirmation' => 'تأكيد كلمة المرور',
        'user_profile_picture' => 'الصورة الشخصية',
        'user_2fa_status' => 'حالة التحقق بخطوتين',
        'last_login_at' => 'تاريخ آخر تسجيل دخول',

        'session_id' => 'معرف الجلسة',
        'ip_address' => 'عنوان IP',
        'user_agent' => 'معلومات المتصفح/الجهاز',
        'login_at' => 'وقت تسجيل الدخول',
        'last_activity_at' => 'وقت آخر نشاط',
        'expires_at' => 'تاريخ الانتهاء',

        'device_id' => 'معرف الجهاز',
        'device_uuid' => 'المعرف الفريد للجهاز',
        'device_name' => 'اسم الجهاز',
        'device_type' => 'نوع الجهاز',
        'is_primary' => 'جهاز أساسي',
        'last_used_at' => 'تاريخ آخر استخدام',

        'ban_id' => 'معرف الحظر',
        'banned_by' => 'المسؤول الحاظر',
        'ban_reason' => 'سبب الحظر',
        'ban_start_at' => 'تاريخ بدء الحظر',
        'ban_end_at' => 'تاريخ انتهاء الحظر',

        'otp_id' => 'معرف التحقق الثنائي',
        'code_hash' => 'تشفير رمز التحقق',
        'code' => 'رمز التحقق (OTP)',
        'attempts' => 'عدد المحاولات',
        'is_used' => 'حالة الاستخدام',

        // ========================================================
        // 4. ACADEMIC STRUCTURE (الهيكل الأكاديمي: صفوف، أقسام، مواد)
        // ========================================================
        'class_id' => 'الصف الدراسي',
        'class_key' => 'مفتاح الصف الدراسي',
        'class_name' => 'اسم الصف الدراسي',

        'section_id' => 'القسم الدراسي',
        'section_key' => 'مفتاح القسم الدراسي',
        'section_name_ar' => 'اسم القسم بالعربية',
        'section_name_en' => 'اسم القسم بالإنجليزية',

        'subject_id' => 'المادة الدراسية',
        'subject_key' => 'مفتاح المادة الدراسية',
        'subject_name_ar' => 'اسم المادة بالعربية',
        'subject_name_en' => 'اسم المادة بالإنجليزية',

        'class_subject_id' => 'ربط المادة بالصف',
        'min_score' => 'الدرجة الصغرى (درجة النجاح)',
        'max_score' => 'الدرجة الكبرى',
        'display_order' => 'ترتيب العرض',

        // ========================================================
        // 5. GRADING SCALES & RESULT RULES (السلالم وقواعد النجاح)
        // ========================================================
        'grading_scale_id' => 'سلم التقديرات',
        'grading_scale_key' => 'مفتاح سلم التقديرات',
        'grading_scale_name' => 'اسم سلم التقديرات',
        'grading_scale_description' => 'وصف سلم التقديرات',

        'grading_scale_item_id' => 'عنصر سلم التقديرات',
        'grade_key' => 'رمز التقدير',
        'grade_name_ar' => 'اسم التقدير بالعربية',
        'grade_name_en' => 'اسم التقدير بالإنجليزية',
        'min_percentage' => 'الحد الأدنى للنسبة المئوية',
        'max_percentage' => 'الحد الأقصى للنسبة المئوية',
        'is_passing' => 'حالة النجاح للتقدير',

        'result_rule_id' => 'قاعدة احتساب النتيجة',
        'rule_key' => 'مفتاح قاعدة النتيجة',
        'rule_name' => 'اسم قاعدة النتيجة',
        'rule_description' => 'وصف قاعدة النتيجة',
        'minimum_overall_percentage' => 'الحد الأدنى للنسبة الإجمالية للنجاح',
        'minimum_subject_percentage' => 'الحد الأدنى لنسبة كل مادة للنجاح',
        'require_all_subjects_pass' => 'اشتراط النجاح في كافة المواد',
        'allowed_failed_subjects' => 'عدد مواد الرسوب المسموح بها',

        // ========================================================
        // 6. RESULT TEMPLATES (قوالب كشوفات النتائج والـ PDF)
        // ========================================================
        'template_id' => 'قالب النتيجة',
        'template_key' => 'مفتاح القالب',
        'template_version' => 'إصدار القالب',
        'template_name' => 'اسم القالب',
        'template_description' => 'وصف القالب',
        'template_header_image' => 'صورة ترويسة القالب',
        'template_footer_image' => 'صورة تذييل القالب',
        'page_size' => 'حجم الصفحة',
        'orientation' => 'اتجاه الصفحة',
        'template_configuration' => 'إعدادات وتنسيقات القالب',

        // ========================================================
        // 7. RESULTS & SUBJECT SCORES (النتائج ودرجات الطلاب)
        // ========================================================
        'result_id' => 'النتيجة',
        'result_uuid' => 'المعرف الفريد للنتيجة',
        'student_seat_number' => 'رقم الجلوس',
        'student_name' => 'اسم الطالب',
        'academic_year' => 'العام الدراسي',
        'total_score' => 'المجموع الكلي للطالب',
        'total_max_score' => 'المجموع الكلي النهائي',
        'percentage' => 'النسبة المئوية',
        'final_grade' => 'التقدير العام',
        'final_status' => 'الحالة النهائية للنتيجة',
        'created_by' => 'أنشئت بواسطة',

        'result_subject_id' => 'درجة مادة الطالب',
        'score' => 'درجة المادة',
        'grade' => 'تقدير المادة',
        'is_passed' => 'حالة اجتياز المادة',

        // ========================================================
        // 8. NOTIFICATIONS & SETTINGS (الإشعارات والإعدادات)
        // ========================================================
        'notification_id' => 'الإشعار',
        'notification_type' => 'نوع الإشعار',
        'title' => 'عنوان الإشعار',
        'message' => 'نص الإشعار',
        'is_read' => 'حالة القراءة',
        'read_at' => 'تاريخ القراءة',

        'category_id' => 'تصنيف الإعدادات',
        'category_key' => 'مفتاح التصنيف',
        'category_name' => 'اسم التصنيف',
        'category_description' => 'وصف التصنيف',

        'setting_id' => 'الإعداد',
        'setting_key' => 'مفتاح الإعداد',
        'setting_name' => 'اسم الإعداد',
        'setting_description' => 'وصف الإعداد',
        'setting_type' => 'نوع بيانات الإعداد',
        'default_value' => 'القيمة الافتراضية',
        'is_required' => 'حقل إلزامي',
        'is_public' => 'إعداد عام',

        'setting_value_id' => 'قيمة الإعداد العامة',
        'center_setting_value_id' => 'قيمة إعداد المركز',
        'value' => 'القيمة المخصصة',

        // ========================================================
        // 9. SYSTEM LOGS & AUDIT (سجلات التدقيق والمراقبة)
        // ========================================================
        'log_id' => 'سجل التدقيق',
        'module' => 'الوحدة المعنية',
        'action' => 'العملية المنفذة',
        'entity_type' => 'نوع الكيان المستهدف',
        'entity_id' => 'معرف الكيان المستهدف',
        'old_data' => 'البيانات السابقة',
        'new_data' => 'البيانات الجديدة',

        // ========================================================
        // 10. COMMON QUERY / FILTER ATTRIBUTES (المرشحات المشتركة)
        // ========================================================
        'status' => 'الحالة',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',
        'deleted_at' => 'تاريخ الحذف',
        'search' => 'كلمة البحث',
        'per_page' => 'عدد العناصر في الصفحة',
        'page' => 'رقم الصفحة',
        'all' => 'جلب الكل',

        'center_phone_number' => 'هاتف المركز',
        'center_description' => 'وصف المركز',
        'profile_picture' => 'الصورة الشخصية',
        'current_password' => 'كلمة المرور الحالية',
        'new_password' => 'كلمة المرور الجديدة',
        'password' => 'كلمة المرور',
        'otp' => 'رمز التحقق',
        'enable' => 'تفعيل',
        'permissions' => 'الصلاحيات',
        'permissions.*' => 'الصلاحية',
        'items' => 'العناصر',
        'items.*' => 'العنصر',
        'description' => 'الوصف',
        'subjects' => 'المواد',
        'subjects.*' => 'المادة',
        'ids' => 'المعرفات',
        'ids.*' => 'المعرف',
        'seat_number' => 'رقم الجلوس',
        'notification_title' => 'عنوان الإشعار',
        'notification_message' => 'تفاصيل الإشعار',
        'qr_token' => 'رمز التحقق الرقمي',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Messages (رسائل مخصصة لاستمارات الطلب)
    |--------------------------------------------------------------------------
    |
    | تخصيص دقيق لرسائل الأخطاء لكل Form Request في النظام.
    |
    */

    'custom' => [

        // ========================================================
        // DIRECT ATTRIBUTE RULES (تطبيق آلي مباشر على كافة الحقول)
        // ========================================================
        'module_key' => [
            'required' => 'مفتاح الوحدة مطلوب.',
            'unique' => 'مفتاح الوحدة مسجل مسبقاً، يرجى اختيار مفتاح فريد.',
            'max' => 'يجب ألا يتجاوز مفتاح الوحدة 50 حرفاً.',
        ],
        'module_name' => [
            'required' => 'اسم الوحدة مطلوب.',
            'max' => 'يجب ألا يتجاوز اسم الوحدة 50 حرفاً.',
        ],
        'action_type_key' => [
            'required' => 'مفتاح نوع الإجراء مطلوب.',
            'unique' => 'مفتاح نوع الإجراء مسجل مسبقاً.',
            'enum' => 'مفتاح نوع الإجراء غير صالح؛ يجب أن يكون أحد الأنواع المعتمدة في النظام.',
            'max' => 'يجب ألا يتجاوز مفتاح نوع الإجراء 50 حرفاً.',
        ],
        'action_type_name' => [
            'required' => 'اسم نوع الإجراء مطلوب.',
            'max' => 'يجب ألا يتجاوز اسم نوع الإجراء 50 حرفاً.',
        ],
        'permission_key' => [
            'unique' => 'مفتاح الصلاحية مسجل مسبقاً، يرجى اختيار مفتاح فريد.',
            'max' => 'يجب ألا يتجاوز مفتاح الصلاحية 50 حرفاً.',
        ],
        'permission_name' => [
            'required' => 'اسم الصلاحية مطلوب.',
            'max' => 'يجب ألا يتجاوز اسم الصلاحية 50 حرفاً.',
        ],
        'module_id' => [
            'required' => 'الوحدة التابعة للصلاحية مطلوبة.',
            'exists' => 'الوحدة المحددة غير موجودة في النظام.',
            'unique' => 'هذه الصلاحية مسجلة مسبقاً لنفس الوحدة ونفس نوع الإجراء.',
        ],
        'action_type_id' => [
            'required' => 'نوع الإجراء مطلوب.',
            'exists' => 'نوع الإجراء المحدد غير موجود في النظام.',
        ],
        'center_code' => [
            'required' => 'كود المركز مطلوب.',
            'unique' => 'كود المركز مسجل مسبقاً لمركز آخر.',
            'regex' => 'صيغة كود المركز غير صالحة، يجب أن تكون بصيغة CTR-00{id}-random.',
        ],
    ],

];
