<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Models\BillingSubscription;
use App\Models\BillingUsageAggregate;
use App\Models\BillingUsageEvent;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Services\Billing\BillingInvoiceService;
use App\Services\Billing\BillingUsageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesActiveBillingSubscription;
use Tests\TestCase;

class BillingUsageServiceTest extends TestCase
{
    use CreatesActiveBillingSubscription;
    use DatabaseTransactions;

    private BillingSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-28 12:00:00', 'Asia/Aden'));

        $governorate = Governorate::query()->first()
            ?? Governorate::query()->create(['governorate_name' => 'Usage Test Governorate']);
        $directorate = Directorate::query()->where('governorate_id', $governorate->governorate_id)->first()
            ?? Directorate::query()->create([
                'governorate_id' => $governorate->governorate_id,
                'directorate_name' => 'Usage Test Directorate',
            ]);

        $owner = $this->createUserWithScope(RoleScope::NETWORK, [
            'user_full_name' => 'Billing Usage Test Owner',
            'user_phone_number' => '77'.random_int(10000000, 99999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $network = Network::query()->create([
            'owner_id' => $owner->user_id,
            'network_name' => 'Billing Usage Test '.Str::random(8),
            'network_phone_number' => '73'.random_int(10000000, 99999999),
            'governorate_id' => $governorate->governorate_id,
            'directorate_id' => $directorate->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $currencyId = (int) DB::table('currencies')->where('code', 'YER')->value('currency_id');
        $this->subscription = $this->createActiveBillingSubscriptionForNetwork(
            $network,
            [],
            null,
            $owner,
            [
                'api_calls' => [
                    'value' => '2.000000',
                    'aggregation' => 'SUM',
                    'reset_period' => 'MONTH',
                    'overage_behavior' => 'INVOICE',
                    'configuration' => [
                        'overage_prices' => [[
                            'currency_id' => $currencyId,
                            'unit_size_micros' => 500000,
                            'unit_amount_minor' => 100,
                        ]],
                    ],
                ],
                'daily_events' => [
                    'value' => '2',
                    'aggregation' => 'COUNT',
                    'reset_period' => 'DAY',
                    'overage_behavior' => 'BLOCK',
                ],
            ]
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sum_meter_applies_limit_and_records_only_incremental_overage_charges(): void
    {
        $service = app(BillingUsageService::class);
        $subscriptionId = (int) $this->subscription->billing_subscription_id;

        $first = $service->record($subscriptionId, 'api_calls', '1.5', 'api-usage-1');
        $second = $service->record($subscriptionId, 'api_calls', '1', 'api-usage-2');
        $third = $service->record($subscriptionId, 'api_calls', '0.5', 'api-usage-3');

        $this->assertSame(0, (int) $first->amount_minor);
        $this->assertSame(100, (int) $second->amount_minor);
        $this->assertSame(100, (int) $third->amount_minor);

        $aggregate = BillingUsageAggregate::query()
            ->where('billing_subscription_id', $subscriptionId)
            ->whereHas('meter', fn ($query) => $query->where('meter_key', 'api_calls'))
            ->firstOrFail();

        $this->assertSame('3.000000', (string) $aggregate->quantity);
        $this->assertSame(200, (int) $aggregate->amount_minor);
        $this->assertSame('2026-11-01', $aggregate->period_start->toDateString());
        $this->assertSame('2026-11-30', $aggregate->period_end->toDateString());
        $this->assertSame(200, (int) BillingUsageEvent::query()
            ->where('billing_subscription_id', $subscriptionId)
            ->sum('amount_minor'));

        $invoice = app(BillingInvoiceService::class)->createForSubscription(
            $this->subscription,
            null,
            $this->subscription->current_period_start?->toDateString(),
            $this->subscription->current_period_end?->toDateString()
        );

        $this->assertSame(200, (int) $invoice->lines()
            ->where('line_type', 'METERED_USAGE')
            ->sum('total_minor'));
    }

    public function test_count_meter_counts_events_and_blocks_after_the_configured_count(): void
    {
        $service = app(BillingUsageService::class);
        $subscriptionId = (int) $this->subscription->billing_subscription_id;

        $service->record($subscriptionId, 'daily_events', '50', 'daily-event-1');
        $service->record($subscriptionId, 'daily_events', '0.5', 'daily-event-2');

        $this->expectException(ValidationException::class);
        $service->record($subscriptionId, 'daily_events', '100', 'daily-event-3');
    }

    public function test_monthly_reset_creates_a_new_usage_bucket_without_resetting_the_invoice_period(): void
    {
        $service = app(BillingUsageService::class);
        $subscriptionId = (int) $this->subscription->billing_subscription_id;
        $billingPeriodStart = $this->subscription->current_period_start->toDateString();

        Carbon::setTestNow(Carbon::parse('2026-11-30 23:59:00', 'Asia/Aden'));
        $novemberEvent = $service->record($subscriptionId, 'api_calls', '0.5', 'monthly-reset-november');

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:01:00', 'Asia/Aden'));
        $decemberEvent = $service->record($subscriptionId, 'api_calls', '0.5', 'monthly-reset-december');

        $this->assertSame($billingPeriodStart, $novemberEvent->period_start->toDateString());
        $this->assertSame($billingPeriodStart, $decemberEvent->period_start->toDateString());
        $this->assertSame('2026-12-01', $decemberEvent->occurred_at->toDateString());

        $this->assertSame(2, BillingUsageAggregate::query()
            ->where('billing_subscription_id', $subscriptionId)
            ->whereHas('meter', fn ($query) => $query->where('meter_key', 'api_calls'))
            ->count());
    }

    public function test_restricted_trial_mode_only_records_explicitly_allowed_metered_features(): void
    {
        DB::table('billing_subscriptions')
            ->where('billing_subscription_id', $this->subscription->billing_subscription_id)
            ->update([
                'status' => 'TRIALING',
                'trial_policy_snapshot' => json_encode([
                    'entitlement_mode' => 'RESTRICTED',
                    'entitlement_config' => ['allowed_features' => []],
                ], JSON_THROW_ON_ERROR),
            ]);

        $service = app(BillingUsageService::class);
        $subscriptionId = (int) $this->subscription->billing_subscription_id;

        try {
            $service->record($subscriptionId, 'api_calls', '1', 'trial-restricted-denied');
            $this->fail('A restricted trial must reject a metered feature that is not explicitly allowed.');
        } catch (ValidationException) {
            $this->assertSame(0, BillingUsageEvent::query()
                ->where('billing_subscription_id', $subscriptionId)
                ->count());
        }

        DB::table('billing_subscriptions')
            ->where('billing_subscription_id', $subscriptionId)
            ->update([
                'trial_policy_snapshot' => json_encode([
                    'entitlement_mode' => 'RESTRICTED',
                    'entitlement_config' => ['allowed_features' => ['api_calls']],
                ], JSON_THROW_ON_ERROR),
            ]);

        $event = $service->record($subscriptionId, 'api_calls', '1', 'trial-restricted-allowed');

        $this->assertSame('1.000000', (string) $event->quantity);
    }

    public function test_none_trial_mode_blocks_metered_usage_recording(): void
    {
        DB::table('billing_subscriptions')
            ->where('billing_subscription_id', $this->subscription->billing_subscription_id)
            ->update([
                'status' => 'TRIALING',
                'trial_policy_snapshot' => json_encode(['entitlement_mode' => 'NONE'], JSON_THROW_ON_ERROR),
            ]);

        $this->expectException(ValidationException::class);
        app(BillingUsageService::class)->record(
            (int) $this->subscription->billing_subscription_id,
            'api_calls',
            '1',
            'trial-none-usage'
        );
    }
}
