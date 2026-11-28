<?php

namespace Tests\Unit;

use App\Services\Billing\BillingUsageAggregationService;
use App\Services\Billing\BillingUsagePeriodResolver;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingUsageRulesTest extends TestCase
{
    public function test_all_meter_aggregation_modes_are_applied_as_configured(): void
    {
        $aggregation = app(BillingUsageAggregationService::class);

        $this->assertSame(5_000_000, $aggregation->aggregate(2_000_000, 3_000_000, 'SUM'));
        $this->assertSame(3_000_000, $aggregation->aggregate(2_000_000, 3_000_000, 'COUNT'));
        $this->assertSame(3_000_000, $aggregation->aggregate(2_000_000, 3_000_000, 'LAST'));
        $this->assertSame(3_000_000, $aggregation->aggregate(2_000_000, 3_000_000, 'MAX'));
        $this->assertSame(2_000_000, $aggregation->aggregate(2_000_000, 3_000_000, 'MIN'));
        $this->assertSame(3_000_000, $aggregation->aggregate(0, 3_000_000, 'MIN'));
    }

    public function test_count_counts_events_instead_of_summing_submitted_quantity(): void
    {
        $this->assertSame(
            2_000_000,
            app(BillingUsageAggregationService::class)->aggregate(1_000_000, 25_000_000, 'COUNT')
        );
    }

    public function test_overage_calculation_returns_only_the_new_billable_delta(): void
    {
        $result = app(BillingUsageAggregationService::class)->overage(8_000_000, 13_000_000, 10_000_000);
        $this->assertSame(['previous' => 0, 'current' => 3_000_000], $result);
    }

    public function test_reset_period_resolver_supports_every_calendar_window_and_lifetime(): void
    {
        $resolver = app(BillingUsagePeriodResolver::class);
        $now = CarbonImmutable::parse('2026-11-28 14:37:12', 'Asia/Aden');

        $this->assertSame(['start' => '2026-11-28', 'end' => '2026-11-28'], $resolver->resolve($now, 'DAY'));
        $this->assertSame(['start' => '2026-11-23', 'end' => '2026-11-29'], $resolver->resolve($now, 'WEEK'));
        $this->assertSame(['start' => '2026-11-01', 'end' => '2026-11-30'], $resolver->resolve($now, 'MONTH'));
        $this->assertSame(['start' => '2026-01-01', 'end' => '2026-12-31'], $resolver->resolve($now, 'YEAR'));
        $this->assertSame(['start' => '1970-01-01', 'end' => '9999-12-31'], $resolver->resolve($now, 'NEVER'));
    }

    public function test_unknown_aggregation_and_reset_period_are_rejected(): void
    {
        try {
            app(BillingUsageAggregationService::class)->aggregate(0, 1_000_000, 'AVERAGE');
            $this->fail('Unknown aggregation should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            app(BillingUsagePeriodResolver::class)->resolve(CarbonImmutable::now('Asia/Aden'), 'QUARTER');
            $this->fail('Unknown reset period should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
