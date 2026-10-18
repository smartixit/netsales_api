# قواعد الهندسة البرمجية والكود النظيف الموحد (Strict Architecture & Clean Code Rules)

تعتبر هذه الوثيقة المرجع الأساسي والملزم لجميع عمليات التطوير والتعديل البرمجي داخل مشروع الواجهة الخلفية لنظام NetSales. يجب الالتزام الصارم بها في كافة الملفات والمراحل دون الحاجة لأي تذكير:

---

## 1. منع استخدام `$request->validated()` نهائياً (Zero Validated Call Policy)
- **القاعدة:** يُحظر حظرًا باتًا ومطلقًا كتابة أو استدعاء `$request->validated()` أو `$request->validated('field')` في أي ملف داخل المشروع (Controllers, Services, Jobs, Repositories).
- **التطبيق الصحيح:**
  - الاعتماد بنسبة 100% على ملفات `FormRequest` المخصصة لكل طلب مع توفير دوال قراءة مساعدة صريحة ومحمية عند الحاجة (`getIds()`, `getNetworkId()`, `getStatus()`).
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

## 3. المعالجة الموحدة للاستثناءات في الكونترولر (Uniform Try-Catch Architecture)
- **القاعدة:** يجب تغليف **كافة دوال المتحكمات بلا أي استثناء** (بما في ذلك `index`, `show`, `store`, `update`, `destroy`, `trashed`, `restore`, `forceDelete`, `batchDelete`, `batchRestore`, `batchForceDelete`, `approve`, `reject` وكافة الدوال المخصصة) بكتل استثناء موحدة:
  ```php
  try {
      // استدعاء طبقة الخدمة وتنسيق الاستجابة
      return $this->successResponse(...);
  } catch (ValidationException $e) {
      return $this->validationErrorResponse($e);
  } catch (Exception $e) {
      return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
  }
  ```
- **الهدف:** ضمان عدم تسرب أي خطأ غير معالج 500 للمستخدم، وتوحيد نسق الردود البرمجية عبر `ApiResponseTrait`.

---

## 4. تنظيم الاستيرادات الصريحة ومنع المسارات الكاملة (Clean Imports via `use`)
- **القاعدة:** يُمنع تضمين مسارات الملفات والكلاسات كاملة (Inline FQCN) داخل جسم الكود أو تواقيع الدوال (مثل `\App\Models\User` أو `\Illuminate\Support\Facades\DB`).
- **التطبيق الصحيح:**
  - يجب استيراد كافة الكلاسات، النماذج، الواجهات (Interfaces)، الخدمات، الدوال الاستثنائية، والحالات (Enums) في أعلى الملف عبر عبارات `use` صريحة ومنظمة أبجدياً أو نوعياً.

---

## 5. طبقة الخدمة وإدارة المعاملات المالية (Service Layer & Transactions)
- **المسؤولية:** تمثل طبقة منطق الأعمال والعمليات المعقدة:
  - هي **المكان الوحيد المخول والمصرح له** بفتح وإغلاق معاملات قاعدة البيانات لضمان تكامل العمليات:
    ```php
    return DB::transaction(function () { ... });
    ```
  - التنسيق بين المستودعات المختلفة (`Repositories`). يُمنع منعاً باتاً كتابة استعلامات Eloquent مباشرة على النماذج (`User::`, `Pos::`) داخل الخدمة طالما كان المستودع المعني موجوداً؛ يجب حقن واستخدام المستودع دائماً (`$this->userRepository->findByPhone(...)`).
  - معالجة وتخزين الملفات والوسائط واستبدالها عبر `FileUploadService`.
  - توزيع وإطلاق وظائف الطوابير (`Queue Jobs`) مثل إرسال الرسائل النصية القصيرة (`SendSmsNotificationJob`) والبريد الإلكتروني (`SendEmailJob`).
  - **منع ازدواجية السجلات:** عدم تسجيل أحداث `SystemLoggerService` يدوياً لعمليات الـ CRUD والحذف المجمع والاستعادة والاعتماد التي ينفذها المستودع، لأن `BaseRepository` يتكفل بتسجيلها تلقائياً.

---

## 6. طبقة المستودعات والقفل المتشائم الرشيد (Repository Layer & Locking Policy)
- **المسؤولية:** المسؤولة الحصرية والوحيدة عن تنفيذ استعلامات وعمليات قاعدة البيانات:
  - كافة المستودعات ترث من `BaseRepository` وتطبق واجهة فرعية ترث من `BaseRepositoryInterface`.
  - **سياسة القفل المتشائم الرشيد:**
    - دوال الاستعراض والقراءة فقط (`findById`, `findTrashedById`, `show`) حرة تماماً من القفل المتشائم لمنع تجميد الجداول والـ `Deadlocks` تحت الضغط المتزامن.
    - يُطبق القفل المتشائم (`findByIdForUpdate` / `lockForUpdate()`) حصرياً داخل معاملات التعديل والحذف (`update`, `patch`, `delete`, `restore`, `forceDelete`) وعند تعديل أو استرجاع السجلات والحركات المالية الحساسة (مثل الأرصدة والمحافظ).
  - العمليات المجمعة تعتمد المسميات القياسية:
    - `bulkDelete(array $ids): int`
    - `bulkRestore(array $ids): int`
    - `bulkForceDelete(array $ids): int`
  - المستودع هو المسؤول عن الرقابة والتدقيق الأمني الذاتي (`recordAddition`, `recordModification`, `recordDeletion`, `recordRestore`, `recordForceDeletion`, `recordBulkDeletion`, `recordBulkRestore`, `recordBulkForceDelete`).

---

## 7. مطابقة النماذج لملفات الهجرة (Migration-First Schema Alignment)
- **القاعدة:** ملفات الهجرة (`database/migrations`) هي المرجع الحصري والوحيد لحقول الجداول وأنواعها والقيود.
- **التطبيق الصارم للـ SoftDeletes:**
  - **يُحظر وضع تريت `use SoftDeletes;`** في أي موديل إلا إذا كان جدوله يحتوي صراحة على `$table->softDeletes();` في ملف الهجرة (مثل منع وضعه في `NetworkPos` لأن جدول `network_pos` لا يحتوي حقل `deleted_at`).
  - استغلال دوال وعلاقات النماذج (`Relationships`, `Scopes`, `Casts`) بدلاً من تكرار الشروط في الكود.

---

## 8. توحيد معاملات المسارات والعقود (Unified Route Resource Binding)
- **القاعدة:** عند تعريف الموارد عبر `Route::apiResource`، يجب دائماً توحيد اسم معامل المسار إلى `{id}` عبر خاصية `parameters`:
  ```php
  Route::apiResource('users', UserController::class)->parameters(['users' => 'id'])->except(['index', 'show']);
  Route::apiResource('networks', NetworkController::class)->parameters(['networks' => 'id'])->except(['index', 'show']);
  Route::apiResource('pos', PosController::class)->parameters(['pos' => 'id'])->except(['index', 'show']);
  ```
  لضمان تطابق اسم المعامل في الرابط مع توقيع دوال المتحكم `function update(..., int $id)` و `function destroy(int $id)` بشكل دائم وموحد في النظام.

---

## 9. الأمان المؤسسي وإدارة الجلسات والأجهزة ومنع التكرار (Security, Sessions & Idempotency)
- **نظام الجلسات:** الاعتماد الحصري على ترويسة `X-Session-Id` ونظام الجلسات في جدول `user_sessions` (دون الاعتماد على التوكنات العشوائية).
- **التحقق الأمني متدرج المستويات في الوسيط (`SessionAuthMiddleware`):**
  1. التحقق من صلاحية الجلسة وتاريخ انتهائها.
  2. التحقق من تفعيل حساب المستخدم (`UserStatus::ACTIVE`) وعدم وجود حظر أمني نشط في `security_bans`.
  3. التحقق من حالة الشبكة التابع لها المستخدم (`RoleKey::NETWORK_OWNER`) أو نقطة البيع (`RoleKey::POS_AGENT`) عبر علاقات الملكية الصريحة.
  4. التحقق من جهاز المستخدم عبر ترويسة `X-Device-Uuid`.
- **مفتاح منع تكرار العمليات المالية (`Idempotency`):** دعم ترويسة `X-Idempotency-Key` للعمليات المالية والحساسة وإرجاع رمز `409 Conflict` عند تزامن الطلبات لنفس المفتاح.

---

## 10. بروتوكول التحكم البرمجي الصارم وبوابات المصادقة (Absolute Execution Control & DoD)
- **العمل في أطوار متتالية واضحة:**
  - الوضع الافتراضي الدائم هو التحليل والمعاينة البرمجية فقط (Analysis Only).
  - يُحظر إنشاء أو تعديل أو حذف ملفات أو تشغيل ترحيلات أو تثبيت حزم دون مصادقة صريحة من المستخدم (Explicit Approval Gate).
  - تنفيذ شريحة رأسية واحدة في كل خطوة (One Feature Slice at a Time) دون ترك دوال وهمية أو TODOs في مسار الإنتاج.
- **تعريف الإنجاز التام (Definition of Done):**
  - فحص الصياغة البرمجية عبر `php -l` والتأكد من خلو الملفات من أي أخطاء.
  - فحص جدول المسارات والاعتماديات بعد أي تعديل على المسارات عبر `php artisan route:list`.
  - عدم إعلان اكتمال أي ميزة دون إبراز أدلة التحقق المادية الصريحة.
