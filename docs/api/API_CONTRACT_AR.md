# عقد API المشترك بين Laravel وFlutter

## لماذا نحتاج عقدًا؟

يجب ألا يعتمد تطبيق Flutter على تخمين شكل استجابة Laravel. نحتاج تعريفًا يمكن مراجعته للطلبات والاستجابات والمصادقة والأخطاء والترقيم والتوافق بين الإصدارات.

## الخيار الموصى به لتوثيق Laravel: Scramble

Scramble يولّد توثيق OpenAPI 3.1 من مسارات Laravel وأنواع PHP وقواعد التحقق وموارد الاستجابة، دون الحاجة إلى تكرار كل شيء يدويًا في PHPDoc.

من جذر مشروع Laravel:

```powershell
composer require --dev dedoc/scramble
```

ثم حدّث إرشادات Laravel Boost لاكتشاف تكامل المهارة:

```powershell
php artisan boost:update --discover
```

تحقق من توفر أوامر Scramble:

```powershell
php artisan list --raw | Select-String '^scramble:'
```

حلّل توثيق الـ API:

```powershell
php artisan scramble:analyze --fail-on-unknown
```

إذا كان التطبيق يحتوي فعلًا على API routes يجب توثيقها، أضف `--fail-on-empty` حتى يفشل الفحص عندما لا يجد Scramble أي مسارات. لا تضف هذا الخيار إلى مشروع لا يحتوي API بعد.

صدّر العقد إلى ملف يمكن مراجعته في Git:

```powershell
php artisan scramble:export --path=docs/api/openapi.json
```

راجع الملف قبل اعتماده. لا تعتبر نجاح التصدير وحده دليلًا على صحة كل schema؛ تحقق من المسارات والحقول والحالات والأمثلة.

## ما الذي يجب مراجعته في كل تغيير؟

- HTTP method والمسار واسم route.
- معايير المصادقة والصلاحيات.
- query/path parameters وrequest body.
- رموز HTTP: النجاح والتحقق والمصادقة والصلاحية وعدم العثور والتعارض وتحديد المعدل.
- أسماء الحقول والأنواع والحقول المطلوبة والحقول nullable.
- enums وقيمها، وتنسيق التاريخ والوقت والمنطقة الزمنية.
- response envelope وpagination وmetadata.
- بنية رسائل الأخطاء ورسائل التحقق.
- الرفع والتنزيل، وحجم ونوع الملفات.
- هل التغيير متوافق مع إصدارات Flutter السابقة أم يحتاج versioning/migration؟

## تنسيق العمل بين الفريقين

1. يحدد المطور/الوكيل العقد الحالي قبل التعديل.
2. يُعدّل Laravel validation/resources/types والاختبارات.
3. يُعاد توليد OpenAPI ويُراجع الفرق.
4. يُحدّث Flutter models/serialization/repositories/error mapping والاختبارات.
5. يُشغّل اختبار End-to-End واحد أو أكثر على API اختبارية للتحقق من الاتصال الحقيقي.
6. يراجع `code-critic` التوافق بين الطرفين؛ ويُستدعى `security-auditor` عند المساس بالمصادقة والصلاحيات والبيانات.

## ملاحظة حول توليد عميل Dart

لا تثبّت مولّد API تلقائيًا. قد يتعارض الكود المولّد مع المعمارية الحالية أو GetX أو طبقة Dio/interceptors/error handling. إذا تقرر اعتماده لاحقًا، فاعزل الملفات المولدة داخل طبقة data، ولا تضع منطق العمل أو واجهة المستخدم داخلها.

## إضافة فحص Scramble إلى Laravel CI (اختياري)

بعد تثبيت Scramble والتأكد من أن مسارات API الموثقة موجودة، يمكن إضافة خطوة إلى workflow الخاص بـ Laravel:

```yaml
      - name: Analyze OpenAPI contract when Scramble is installed
        shell: bash
        run: |
          if php artisan list --raw | grep -q '^scramble:analyze'; then
            php artisan scramble:analyze --fail-on-unknown
          else
            echo "Scramble is not installed; skipping API contract analysis."
          fi
```

إذا كان كل مشروع يستخدم هذا الـ workflow يتوقع وجود API موثقة، يمكن تغيير الأمر إلى `php artisan scramble:analyze --fail-on-empty --fail-on-unknown`. لا تفعّل ذلك لقالب Laravel عام أو مشروع لم يضف API routes بعد.
