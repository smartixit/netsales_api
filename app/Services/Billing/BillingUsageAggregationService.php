<?php

namespace App\Services\Billing;

use Illuminate\Validation\ValidationException;
use OverflowException;

class BillingUsageAggregationService
{
    public function aggregate(int $currentMicros, int $incomingMicros, string $aggregation): int
    {
        if ($currentMicros < 0 || $incomingMicros <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['قيم التجميع يجب أن تكون غير سالبة والكمية الجديدة أكبر من صفر.'],
            ]);
        }

        return match (strtoupper($aggregation)) {
            'SUM' => $this->safeAdd($currentMicros, $incomingMicros),
            'COUNT' => $this->safeAdd($currentMicros, 1_000_000),
            'LAST' => $incomingMicros,
            'MAX' => max($currentMicros, $incomingMicros),
            'MIN' => $currentMicros === 0 ? $incomingMicros : min($currentMicros, $incomingMicros),
            default => throw ValidationException::withMessages([
                'aggregation' => ['طريقة تجميع الاستهلاك غير مدعومة.'],
            ]),
        };
    }

    /** @return array{previous:int,current:int} */
    public function overage(int $previous, int $current, int $limit): array
    {
        return [
            'previous' => max(0, $previous - $limit),
            'current' => max(0, $current - $limit),
        ];
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new OverflowException('إجمالي الاستهلاك يتجاوز السعة الرقمية المدعومة.');
        }

        return $left + $right;
    }
}
