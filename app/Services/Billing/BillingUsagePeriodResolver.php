<?php

namespace App\Services\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BillingUsagePeriodResolver
{
    private const TIMEZONE = 'Asia/Aden';

    /** @return array{start:string,end:string} */
    public function resolve(CarbonImmutable $now, string $resetPeriod): array
    {
        $local = $now->setTimezone(self::TIMEZONE);
        $start = match (strtoupper($resetPeriod)) {
            'DAY' => $local->startOfDay(),
            'WEEK' => $local->startOfWeek(CarbonImmutable::MONDAY),
            'MONTH' => $local->startOfMonth(),
            'YEAR' => $local->startOfYear(),
            'NEVER' => CarbonImmutable::parse('1970-01-01', self::TIMEZONE),
            default => throw ValidationException::withMessages([
                'reset_period' => ['فترة إعادة تصفير عداد الاستخدام غير مدعومة.'],
            ]),
        };

        $end = match (strtoupper($resetPeriod)) {
            'DAY' => $start->endOfDay(),
            'WEEK' => $start->addWeek()->subSecond(),
            'MONTH' => $start->addMonthNoOverflow()->subSecond(),
            'YEAR' => $start->addYear()->subSecond(),
            'NEVER' => CarbonImmutable::parse('9999-12-31 23:59:59', self::TIMEZONE),
        };

        return ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }
}
