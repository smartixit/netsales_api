<?php

namespace App\Services\Reports;

use Illuminate\Support\MessageBag;

final class OperationalReportPresetValidator
{
    public function __construct(
        private readonly OperationalReportProviderRegistry $providers,
        private readonly OperationalReportFilterValidator $filters
    ) {}

    public function validate(
        string $reportType,
        ?int $posId,
        array $criteria,
        array $presentation,
        int $schemaVersion
    ): array {
        $provider = $this->providers->get($reportType);
        $definition = $provider->definition();
        $errors = new MessageBag();

        if ($schemaVersion !== (int) ($definition['schema_version'] ?? 1)) {
            $errors->add('report_schema_version', 'تغير تعريف التقرير؛ أعد تحميل التعريف ثم احفظ الإعدادات وفق المخطط الحالي.');
        }

        $typedFilters = $criteria['filters'] ?? null;
        if (! is_array($typedFilters)) {
            $errors->add('criteria.filters', 'قائمة المرشحات المحفوظة غير صالحة.');
            $typedFilters = [];
        }

        $rawFilters = [];
        foreach ($typedFilters as $filterId => $typedValue) {
            if (! is_array($typedValue)
                || array_diff(array_keys($typedValue), ['type', 'value']) !== []
                || ! array_key_exists('type', $typedValue)
                || ! array_key_exists('value', $typedValue)) {
                $errors->add('criteria.filters.'.$filterId, 'قيمة المرشح يجب أن تتضمن النوع والقيمة فقط.');
                continue;
            }

            $value = $typedValue['value'];
            $rawFilters[$filterId] = match ($typedValue['type']) {
                'text', 'integer', 'decimal', 'boolean', 'choice', 'choices' => $value,
                'date' => is_string($value) ? substr($value, 0, 10) : $value,
                'date_range' => is_array($value) ? [
                    'from' => isset($value['start']) && is_string($value['start']) ? substr($value['start'], 0, 10) : null,
                    'to' => isset($value['end']) && is_string($value['end']) ? substr($value['end'], 0, 10) : null,
                ] : $value,
                default => $value,
            };
        }

        $filterErrors = new MessageBag();
        $this->filters->validate($provider->type(), $posId, $rawFilters, $filterErrors);
        foreach ($filterErrors->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $errors->add($field, $message);
            }
        }

        $columns = collect($definition['columns'] ?? [])->keyBy('id');
        $visible = $presentation['visible_column_ids'] ?? [];
        if (count($visible) !== count(array_unique($visible))) {
            $errors->add('presentation.visible_column_ids', 'لا يمكن تكرار العمود في الإعدادات المحفوظة.');
        }
        foreach ($visible as $id) {
            if (! $columns->has($id)) {
                $errors->add('presentation.visible_column_ids', 'يتضمن الإعداد عموداً غير معرف في التقرير.');
            }
        }
        $effectiveVisible = $visible === []
            ? collect($definition['columns'] ?? [])->filter(fn (array $column) => ($column['visible_by_default'] ?? true) === true)->pluck('id')->all()
            : $visible;
        foreach ($columns as $id => $column) {
            if (($column['hideable'] ?? true) === false && ! in_array($id, $effectiveVisible, true)) {
                $errors->add('presentation.visible_column_ids', 'يتضمن الإعداد إخفاء عمود إلزامي.');
            }
        }

        $sorts = $presentation['sorts'] ?? [];
        $seenSorts = [];
        foreach ($sorts as $sort) {
            $id = $sort['field_id'] ?? '';
            if (isset($seenSorts[$id])) {
                $errors->add('presentation.sorts', 'لا يمكن تكرار عمود الترتيب.');
            }
            $seenSorts[$id] = true;
            if (! $columns->has($id) || ! ($columns[$id]['sortable'] ?? false)) {
                $errors->add('presentation.sorts', 'يتضمن الإعداد ترتيباً حسب عمود غير مسموح.');
            }
        }

        $groupBy = $presentation['group_by_field_id'] ?? null;
        if ($groupBy !== null && (! $columns->has($groupBy) || ! ($columns[$groupBy]['groupable'] ?? false))) {
            $errors->add('presentation.group_by_field_id', 'عمود التجميع غير متاح لهذا التقرير.');
        }

        $template = $presentation['template'] ?? null;
        if ($template !== null) {
            $version = $template['version'] ?? [];
            $key = ($template['id'] ?? '').'@'.implode('.', [
                $version['major'] ?? -1,
                $version['minor'] ?? -1,
                $version['patch'] ?? -1,
            ]);
            $allowed = collect($definition['templates'] ?? [])->contains(
                fn (array $item): bool => ($item['id'] ?? '').'@'.(string) ($item['version'] ?? '') === $key
            );
            if (! $allowed) {
                $errors->add('presentation.template', 'قالب المستند المحدد غير معتمد لهذا التقرير.');
            }
        }

        $locales = $definition['supported_locale_tags'] ?? [];
        $locale = $presentation['language_tag'] ?? null;
        if ($locale !== null && ! collect($locales)->contains(
            fn (string $allowed): bool => strtolower($locale) === strtolower($allowed)
                || str_starts_with(strtolower($locale), strtolower($allowed).'-')
        )) {
            $errors->add('presentation.language_tag', 'لغة المستند غير مدعومة لهذا التقرير.');
        }

        if (isset($presentation['page_profile_id'])
            && ! in_array($presentation['page_profile_id'], $definition['page_profile_ids'] ?? [], true)) {
            $errors->add('presentation.page_profile_id', 'حجم الصفحة غير متاح لهذا التقرير.');
        }
        if (($presentation['include_metrics'] ?? false) && empty($definition['metric_slots'])) {
            $errors->add('presentation.include_metrics', 'هذا التقرير لا يوفر مؤشرات.');
        }
        if (($presentation['include_charts'] ?? false) && empty($definition['charts_available'])) {
            $errors->add('presentation.include_charts', 'هذا التقرير لا يوفر رسوماً بيانية.');
        }

        return $errors->messages();
    }
}
