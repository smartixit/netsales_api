# قواعد الهندسة البرمجية والكود النظيف الموحد (Strict Architecture & Clean Code Rules)

تعتبر هذه الوثيقة المرجع الأساسي والملزم لجميع عمليات التطوير والتعديل البرمجي داخل المشروع. يجب الالتزام الصارم بها في كافة الملفات والمراحل دون أي استثناء أو خرق:

---

## 1. منع استخدام `$request->validated()` نهائياً (Zero Validated Call Policy)
- **القاعدة:** يُحظر حظرًا باتًا ومطلقًا كتابة أو استدعاء `$request->validated()` أو `$request->validated('field')` في أي ملف داخل المشروع (Controllers, Services, Jobs, Repositories).
- **التطبيق الصحيح:**
  - الاعتماد بنسبة 100% على ملفات `FormRequest` المخصصة لكل طلب مع توفير دوال قراءة مساعدة عند الحاجة (`getIds()`, `getNetworkId()`, `getStatus()`).
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

## 5. طبقة الخدمة (Service Layer)
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

## 6. طبقة المستودعات (Repository Layer)
- **المسؤولية:** المسؤولة الحصرية والوحيدة عن تنفيذ استعلامات وعمليات قاعدة البيانات:
  - كافة المستودعات ترث من `BaseRepository` وتطبق واجهة فرعية ترث من `BaseRepositoryInterface`.
  - العمليات المجمعة تعتمد المسميات القياسية:
    - `bulkDelete(array $ids): int`
    - `bulkRestore(array $ids): int`
    - `bulkForceDelete(array $ids): int`
  - استخدام القفل المتشائم (`lockForUpdate()`) عند تعديل أو استرجاع السجلات المالية أو الحساسة.
  - المستودع هو المسؤول عن الرقابة والتدقيق الأمني الذاتي (`recordAddition`, `recordModification`, `recordDeletion`, `recordRestore`, `recordForceDeletion`, `recordBulkDeletion`, `recordBulkRestore`, `recordBulkForceDelete`).

---

## 7. مطابقة النماذج لملفات الهجرة (Migration-First Schema Alignment)
- **القاعدة:** ملفات الهجرة (`database/migrations`) هي المرجع الحصري والوحيد لحقول الجداول وأنواعها والقيود.
- **التطبيق الصارم للـ SoftDeletes:**
  - **يُحظر وضع تريت `use SoftDeletes;`** في أي موديل إلا إذا كان جدوله يحتوي صراحة على `$table->softDeletes();` في ملف الهجرة (مثل منع وضعه في `NetworkPos` لأن جدول `network_pos` لا يحتوي حقل `deleted_at`).
  - استغلال دوال وعلاقات النماذج (`Relationships`, `Scopes`, `Casts`) بدلاً من تكرار الشروط في الكود.

---

## 8. توحيد معاملات المسارات (Unified Route Resource Binding)
- **القاعدة:** عند تعريف الموارد عبر `Route::apiResource`، يجب دائماً توحيد اسم معامل المسار إلى `{id}` عبر خاصية `parameters`:
  ```php
  Route::apiResource('users', UserController::class)->parameters(['users' => 'id'])->except(['index', 'show']);
  Route::apiResource('networks', NetworkController::class)->parameters(['networks' => 'id'])->except(['index', 'show']);
  Route::apiResource('pos', PosController::class)->parameters(['pos' => 'id'])->except(['index', 'show']);
  ```
  لضمان تطابق اسم المعامل في الرابط مع توقيع دوال المتحكم `function update(..., int $id)` و `function destroy(int $id)` بشكل دائم وموحد في النظام.
