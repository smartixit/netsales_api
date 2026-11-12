<?php

namespace App\Services\Reports;

use App\Enums\OperationalReportType;
use Illuminate\Support\MessageBag;

final class OperationalReportFilterValidator
{
    public function __construct(
        private readonly OperationalReportProviderRegistry $registry
    ) {}

    /**
     * Adds report-specific validation errors to the supplied MessageBag.
     *
     * @param  array<string, mixed>  $input
     */
    public function validate(OperationalReportType $type, ?int $posId, array $input, MessageBag $errors): void
    {
        $definition = $this->registry->get($type)->definition();
        $posSelection = $definition['pos_selection'] ?? 'optional';

        if ($posSelection === 'required' && $posId === null) {
            $errors->add('pos_id', 'هذا التقرير يتطلب تحديد نقطة البيع.');
        }

        if ($posSelection === 'forbidden' && $posId !== null) {
            $errors->add('pos_id', 'لا يقبل هذا التقرير التصفية حسب نقطة البيع.');
        }

        foreach ($definition['filters'] as $filter) {
            $id = $filter['id'];
            if (! array_key_exists($id, $input) || $input[$id] === null || $input[$id] === '') {
                continue;
            }

            if (($filter['kind'] ?? null) === 'single_choice') {
                $allowed = $filter['choices'] ?? [];
                if (! in_array((string) $input[$id], $allowed, true)) {
                    $errors->add($id, 'القيمة المحددة غير مدعومة لهذا التقرير.');
                }
            }

            if (($filter['kind'] ?? null) === 'text') {
                $maxLength = (int) ($filter['max_length'] ?? 0);
                if ($maxLength > 0 && mb_strlen(trim((string) $input[$id])) > $maxLength) {
                    $errors->add($id, "يجب ألا يتجاوز البحث {$maxLength} محرفاً.");
                }
            }
        }
    }
}
