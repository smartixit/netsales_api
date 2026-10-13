# قواعد الهندسة البرمجية والكود النظيف الموحد (Strict Architecture & Clean Code Rules)

تعتبر هذه الوثيقة المرجع الأساسي والملزم لجميع عمليات التطوير والتعديل البرمجي داخل المشروع. يجب الالتزام الصارم بها دون أي استثناء أو خرق:

---

## 1. منع استخدام `$request->validated()` نهائياً (Zero Validated Call Policy)
- **القاعدة:** يُحظر حظرًا باتًا ومطلقًا كتابة أو استدعاء `$request->validated()` أو `$request->validated('field')` في أي ملف داخل المشروع (Controllers, Services, Jobs, Repositories).
- **التطبيق الصحيح:**
  - الاعتماد بنسبة 100% على ملفات `FormRequest` المخصصة لكل طلب.
  - قراءة الخصائص والحقول المحققة مباشرة من كائن الطلب:
    - `$request->user_phone_number`
    - `$request->network_name`
    - `$request->getIds()`
    - `$request->file('avatar')`
  - تمرير كائن الطلب المحقن بكامل نوعه (Typed FormRequest) مباشرة إلى دالة الخدمة المناسبة.

---

## 2. المتحكم النحيف الصارم (Strict Lean Controller)
- **الدور الحصري:** يقتصر دور المتحكم (Controller) فقط وحصراً على:
  1. استقبال كائن الـ `FormRequest` المحقق من إطار العمل.
  2. تمريره مباشرة إلى طبقة الخدمة المعنية (`Service Layer`).
  3. تغليف النتيجة المعادة واستجابات الأخطاء عبر `ApiResponseTrait` وموارد الـ API (`JsonResource`).
- **المحظورات الصارمة داخل الكونترولر:**
  - **يُمنع منعاً باتاً** فتح أو إغلاق معاملات قاعدة البيانات `DB::transaction`.
  - **يُمنع منعاً باتاً** كتابة أي استعلام مباشر لقاعدة البيانات أو استدعاء النماذج (`User::`, `where`, `find`, `findOrFail`).
  - **يُمنع منعاً باتاً** كتابة أي منطق أعمال، شروط تحقق إضافية، أو معالجات حسابية/مالية.

---

## 3. تنظيم الاستيرادات الصريحة ومنع المسارات الكاملة (Clean Imports via `use`)
- **القاعدة:** يُمنع تضمين مسارات الملفات والكلاسات كاملة (Inline FQCN) داخل جسم الكود أو تواقيع الدوال (مثل `\App\Models\User` أو `\Illuminate\Support\Facades\DB`).
- **التطبيق الصحيح:**
  - يجب استيراد كافة الكلاسات، النماذج، الواجهات (Interfaces)، الخدمات، الدوال الاستثنائية، والحالات (Enums) في أعلى الملف عبر عبارات `use` صريحة ومنظمة أبجدياً أو نوعياً.

---

## 4. طبقة الخدمة (Service Layer)
- **المسؤولية:** تمثل طبقة منطق الأعمال والعمليات المعقدة:
  - هي **المكان الوحيد المخول والمصرح له** بفتح وإغلاق معاملات قاعدة البيانات لضمان تكامل العمليات:
    ```php
    return DB::transaction(function () { ... });
    ```
  - التنسيق بين المستودعات المختلفة (`Repositories`).
  - معالجة وتخزين الملفات والوسائط واستبدالها عبر `FileUploadService`.
  - توزيع وإطلاق وظائف الطوابير (`Queue Jobs`) مثل إرسال الرسائل النصية القصيرة (`SendSmsNotificationJob`) والبريد الإلكتروني (`SendEmailJob`).
  - **منع ازدواجية السجلات:** عدم تسجيل أحداث `SystemLoggerService` يدوياً لعمليات الـ CRUD والحذف المجمع والاستعادة والاعتماد التي ينفذها المستودع، لأن `BaseRepository` يتكفل بتسجيلها تلقائياً.

---

## 5. طبقة المستودعات (Repository Layer)
- **المسؤولية:** المسؤولة الحصرية والوحيدة عن تنفيذ استعلامات وعمليات قاعدة البيانات:
  - كافة المستودعات ترث من [`BaseRepository`](file:///d:/Smartix/NetSales/netsales_api/app/Repositories/BaseRepository.php) وتطبق واجهة فرعية ترث من [`BaseRepositoryInterface`](file:///d:/Smartix/NetSales/netsales_api/app/Interfaces/BaseRepositoryInterface.php).
  - العمليات المجمعة تعتمد المسميات القياسية:
    - `bulkDelete(array $ids): int`
    - `bulkRestore(array $ids): int`
    - `bulkForceDelete(array $ids): int`
  - المستودع هو المسؤول عن الرقابة والتدقيق الأمني الذاتي (`recordAddition`, `recordModification`, `recordDeletion`, `recordRestore`, `recordForceDeletion`, `recordBulkDeletion`, `recordBulkRestore`, `recordBulkForceDelete`).
