<?php

namespace App\Services\Reports;

use App\Enums\OperationalReportType;
use App\Support\DecimalAmount;
use Illuminate\Support\Carbon;
use Illuminate\Support\MessageBag;

final class OperationalReportFilterValidator
{
    public function __construct(
        private readonly OperationalReportProviderRegistry $registry
    ) {}

    /**
     * Validate the filter values against the selected provider's public schema.
     *
     * @param  array<string, mixed>  $filters
     */
    public function validate(
        OperationalReportType $type,
        ?int $posId,
        array $filters,
        MessageBag $errors
    ): void {
        $definition = $this->registry->get($type)->definition();
        $posSelection = $definition['pos_selection'] ?? 'optional';

        if ($posSelection === 'required' && $posId === null) {
            $errors->add('pos_id', 'هذا التقرير يتطلب تحديد نقطة البيع.');
        }

        if ($posSelection === 'forbidden' && $posId !== null) {
            $errors->add('pos_id', 'لا يقبل هذا التقرير التصفية حسب نقطة البيع.');
        }

        $filterDefinitions = $definition['filters'] ?? [];
        $allowedFilterIds = array_column($filterDefinitions, 'id');

        foreach (array_keys($filters) as $filterId) {
            if (! in_array($filterId, $allowedFilterIds, true)) {
                $errors->add("filters.{$filterId}", 'هذا المرشح غير مدعوم لنوع التقرير المحدد.');
            }
        }

        foreach ($filterDefinitions as $filter) {
            $filterId = $filter['id'];
            $hasValue = array_key_exists($filterId, $filters)
                && $filters[$filterId] !== null
                && $filters[$filterId] !== '';

            if (($filter['required'] ?? false) && ! $hasValue) {
                $errors->add("filters.{$filterId}", 'هذا المرشح مطلوب لهذا التقرير.');
                continue;
            }

            if (! $hasValue) {
                continue;
            }

            $value = $filters[$filterId];
            $kind = $filter['kind'] ?? null;

            switch ($kind) {
                case 'text':
                    if (! is_string($value)) {
                        $errors->add("filters.{$filterId}", 'يجب أن تكون قيمة المرشح نصاً.');
                        break;
                    }

                    $length = mb_strlen(trim($value));
                    $minLength = (int) ($filter['min_length'] ?? 0);
                    $maxLength = (int) ($filter['max_length'] ?? 0);

                    if ($minLength > 0 && $length < $minLength) {
                        $errors->add("filters.{$filterId}", "يجب ألا يقل النص عن {$minLength} محرفاً.");
                    }
                    if ($maxLength > 0 && $length > $maxLength) {
                        $errors->add("filters.{$filterId}", "يجب ألا يتجاوز النص {$maxLength} محرفاً.");
                    }
                    break;

                case 'date':
                    if (! $this->isValidDate($value)) {
                        $errors->add("filters.{$filterId}", 'يجب إدخال التاريخ بصيغة YYYY-MM-DD.');
                    }
                    break;

                case 'integer':
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $errors->add("filters.{$filterId}", 'يجب إدخال عدد صحيح.');
                    }
                    break;

                case 'decimal':
                    try {
                        DecimalAmount::toMinorUnits($value);
                    } catch (\Throwable) {
                        $errors->add("filters.{$filterId}", 'يجب إدخال مبلغ عشري صالح بخانتين كحد أقصى.');
                    }
                    break;

                case 'boolean':
                    if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                        $errors->add("filters.{$filterId}", 'قيمة منطقية غير صالحة.');
                    }
                    break;

                case 'single_choice':
                    $allowed = array_column($filter['choices'] ?? [], 'value');
                    if (! is_scalar($value) || ! in_array((string) $value, $allowed, true)) {
                        $errors->add("filters.{$filterId}", 'القيمة المحددة غير مدعومة لهذا التقرير.');
                    }
                    break;

                case 'multi_choice':
                    $allowed = array_column($filter['choices'] ?? [], 'value');
                    if (! is_array($value)
                        || array_diff(array_map('strval', $value), $allowed) !== []) {
                        $errors->add("filters.{$filterId}", 'تحتوي القائمة على قيمة غير مدعومة لهذا التقرير.');
                    }
                    break;

                case 'date_range':
                    if (! is_array($value)
                        || ! $this->isValidDate($value['from'] ?? null)
                        || ! $this->isValidDate($value['to'] ?? null)
                        || $value['to'] < $value['from']) {
                        $errors->add("filters.{$filterId}", 'نطاق التاريخ غير صالح.');
                    }
                    break;

                default:
                    $errors->add("filters.{$filterId}", 'نوع المرشح غير مدعوم في تعريف التقرير.');
                    break;
            }
        }

        if (isset($filters['from_date'], $filters['to_date'])
            && $this->isValidDate($filters['from_date'])
            && $this->isValidDate($filters['to_date'])
            && $filters['to_date'] < $filters['from_date']) {
            $errors->add('filters.to_date', 'يجب أن يكون تاريخ النهاية مساوياً لتاريخ البداية أو بعده.');
        }
    }

    private function isValidDate(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return false;
        }

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
