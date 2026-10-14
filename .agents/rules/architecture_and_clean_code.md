# قواعد ومعايير المعمارية والكود النظيف الموحد (Strict Architecture & Clean Code Rules)

يتم تطبيق هذه القواعد بشكل دائم ومستمر على كامل مسارات العمل في كافة ملفات المشروع دون أي استثناء:

---

## 1. منع استخدام `$request->validated()` نهائياً (Zero Validated Call Policy)
- يُحظر حظرًا باتًا ومطلقًا كتابة أو استدعاء `$request->validated()` أو `$request->validated('field')` في أي ملف داخل المشروع (Controllers, Services, Jobs, Repositories).
- الاعتماد بنسبة 100% على ملفات `FormRequest` المخصصة لكل طلب مع تزويدها بدوال قراءة مساعدة (`getIds()`, `getNetworkId()`, `getStatus()`).
- قراءة الخصائص والحقول المحققة مباشرة من كائن الطلب: `$request->user_phone_number`، `$request->network_name`، `$request->getIds()`، إلخ.
- تمرير كائن الطلب المحقن بكامل نوعه (Typed FormRequest) مباشرة إلى دالة الخدمة المناسبة.

---

## 2. نمط المتحكم النحيف الصارم (Strict Lean Controller)
- يقتصر دور المتحكم (Controller) فقط وحصراً على:
  1. استقبال كائن الـ `FormRequest` المحقق.
  2. تمريره مباشرة إلى طبقة الخدمة المعنية (`Service Layer`).
  3. تغليف النتيجة المعادة واستجابات الأخطاء عبر `ApiResponseTrait` وموارد الـ API (`JsonResource`).
- المحظورات الصارمة داخل الكونترولر:
  - يُمنع منعاً باتاً فتح أو إغلاق معاملات قاعدة البيانات `DB::transaction`.
  - يُمنع منعاً باتاً كتابة أي استعلام مباشر لقاعدة البيانات أو استدعاء النماذج (`User::`, `where`, `find`, `findOrFail`).
  - يُمنع منعاً باتاً كتابة أي منطق أعمال أو شروط تحقق إضافية أو معالجات حسابية/مالية.

---

## 3. المعالجة الموحدة للاستثناءات في الكونترولر (Uniform Try-Catch Architecture)
- يجب تغليف **كافة دوال المتحكمات بلا أي استثناء** (بما في ذلك `index`, `show`, `store`, `update`, `destroy`, `trashed`, `restore`, `forceDelete`, `batchDelete`, `batchRestore`, `batchForceDelete`, `approve`, `reject` وكافة الدوال المخصصة) بكتل استثناء موحدة:
  ```php
  try {
      return $this->successResponse(...);
  } catch (ValidationException $e) {
      return $this->validationErrorResponse($e);
  } catch (Exception $e) {
      return $this->errorResponse('رسالة الخطأ: '.$e->getMessage(), [], 422);
  }
  ```

---

## 4. تنظيم الاستيرادات ومنع المسارات الكاملة (Clean Imports via `use`)
- يُمنع تضمين مسارات الملفات والكلاسات كاملة (Inline FQCN) داخل جسم الكود أو تواقيع الدوال (مثل `\App\Models\User` أو `\Illuminate\Support\Facades\DB`).
- استيراد كافة الكلاسات، النماذج، الواجهات (Interfaces)، الخدمات، الدوال الاستثنائية، والـ Enums في أعلى الملف عبر عبارات `use` صريحة ومنظمة.

---

## 5. طبقة الخدمة (Service Layer)
- تمثل طبقة منطق الأعمال والعمليات المعقدة:
  - هي المكان الوحيد المخول والمصرح له بفتح وإغلاق معاملات قاعدة البيانات `DB::transaction`.
  - التنسيق بين المستودعات (`Repositories`). يُمنع منعاً باتاً كتابة استعلامات Eloquent مباشرة على النماذج (`User::`, `Pos::`) داخل الخدمة طالما كان المستودع المعني متوفراً؛ يجب حقن واستخدام المستودع دائماً.
  - معالجة وتخزين الملفات والوسائط واستبدالها عبر `FileUploadService`.
  - توزيع وإطلاق وظائف الطوابير (`Queue Jobs`) مثل إرسال الرسائل القصيرة والبريد.
  - منع ازدواجية السجلات: عدم تسجيل أحداث `SystemLoggerService` يدوياً لعمليات الـ CRUD والحذف المجمع والاستعادة والاعتماد التي ينفذها المستودع، لأن `BaseRepository` يتكفل بتسجيلها تلقائياً.

---

## 6. طبقة المستودعات (Repository Layer)
- المسؤولة الحصرية والوحيدة عن تنفيذ استعلامات وعمليات قاعدة البيانات:
  - كافة المستودعات ترث من `BaseRepository` وتطبق واجهة فرعية ترث من `BaseRepositoryInterface`.
  - العمليات المجمعة تعتمد المسميات القياسية:
    - `bulkDelete(array $ids): int`
    - `bulkRestore(array $ids): int`
    - `bulkForceDelete(array $ids): int`
  - استخدام القفل المتشائم (`lockForUpdate()`) عند تعديل أو استرجاع السجلات المالية أو الحساسة.
  - التوثيق الأمني التلقائي الذاتي عبر دوال التدقيق المدمجة في `BaseRepository`.

---

## 7. مطابقة النماذج لملفات الهجرة (Migration-First Schema Alignment)
- ملفات الهجرة (`database/migrations`) هي المرجع الحصري والوحيد لحقول الجداول وأنواعها والقيود.
- **التطبيق الصارم للـ SoftDeletes:**
  - يُحظر وضع تريت `use SoftDeletes;` في أي موديل إلا إذا كان جدوله يحتوي صراحة على `$table->softDeletes();` في ملف الهجرة.
  - استغلال دوال وعلاقات النماذج (`Relationships`, `Scopes`, `Casts`) بدلاً من تكرار الشروط في الكود.

---

## 8. توحيد معاملات المسارات (Unified Route Resource Binding)
- عند تعريف الموارد عبر `Route::apiResource`، يجب دائماً توحيد اسم معامل المسار إلى `{id}` عبر خاصية `parameters`:
  ```php
  Route::apiResource('users', UserController::class)->parameters(['users' => 'id'])->except(['index', 'show']);
  Route::apiResource('networks', NetworkController::class)->parameters(['networks' => 'id'])->except(['index', 'show']);
  Route::apiResource('pos', PosController::class)->parameters(['pos' => 'id'])->except(['index', 'show']);
  ```
