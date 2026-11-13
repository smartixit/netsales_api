<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Models\Network;
use App\Models\User;
use App\Services\Billing\BillingSelfService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesActiveBillingSubscription;
use Tests\TestCase;

class BillingSelfServiceCursorPaginationTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesActiveBillingSubscription;

    public function test_subscription_collection_uses_cursor_pages_without_repeating_rows(): void
    {
        $actor = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', 'SUPER_ADMIN'))
            ->firstOrFail();

        $network = Network::query()->create([
            'owner_id' => $actor->user_id,
            'network_uuid' => (string) Str::uuid(),
            'network_name' => 'Billing Cursor Pagination '.Str::random(8),
            'network_phone_number' => '73'.random_int(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $firstSubscription = $this->createActiveBillingSubscriptionForNetwork(
            $network,
            actor: $actor,
        );
        $secondSubscription = $this->createActiveBillingSubscriptionForNetwork(
            $network,
            actor: $actor,
        );

        $service = app(BillingSelfService::class);
        $firstPage = $service->subscriptions(
            (int) $firstSubscription->billing_account_id,
            ['per_page' => 1],
            $actor,
        );

        $this->assertInstanceOf(CursorPaginator::class, $firstPage);
        $this->assertCount(1, $firstPage->items());
        $this->assertTrue($firstPage->hasMorePages());

        $cursor = $firstPage->nextCursor();
        $this->assertNotNull($cursor);

        $secondPage = $service->subscriptions(
            (int) $firstSubscription->billing_account_id,
            [
                'per_page' => 1,
                'cursor' => $cursor->encode(),
            ],
            $actor,
        );

        $this->assertInstanceOf(CursorPaginator::class, $secondPage);
        $this->assertCount(1, $secondPage->items());
        $this->assertNotSame(
            $firstPage->items()[0]->billing_subscription_id,
            $secondPage->items()[0]->billing_subscription_id,
        );
        $this->assertSame(
            [
                $firstSubscription->billing_subscription_id,
                $secondSubscription->billing_subscription_id,
            ],
            collect([
                $firstPage->items()[0]->billing_subscription_id,
                $secondPage->items()[0]->billing_subscription_id,
            ])->sort()->values()->all(),
        );
    }
}
