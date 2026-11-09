<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\RoleKey;
use App\Enums\SaleType;
use App\Enums\UserStatus;
use App\Models\Directorate;
use App\Models\Expense;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\Request as ProcurementRequest;
use App\Models\Role;
use App\Models\SaleInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NetworkWorkspaceOverviewTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{int, int} */
    private function geographicIds(): array
    {
        $governorate = Governorate::query()->first()
            ?? Governorate::create(['governorate_name' => 'Workspace Test Governorate']);
        $directorate = Directorate::query()
            ->where('governorate_id', $governorate->governorate_id)
            ->first()
            ?? Directorate::create([
                'governorate_id' => $governorate->governorate_id,
                'directorate_name' => 'Workspace Test Directorate',
            ]);

        return [$governorate->governorate_id, $directorate->directorate_id];
    }

    public function test_network_owner_receives_only_the_selected_networks_live_overview(): void
    {
        [$governorateId, $directorateId] = $this->geographicIds();
        $ownerRole = Role::query()
            ->where('role_key', RoleKey::NETWORK_OWNER->value)
            ->firstOrFail();

        $owner = User::create([
            'role_id' => null,
            'user_full_name' => 'Network Overview Owner',
            'user_phone_number' => '7'.random_int(100000000, 999999999),
            'user_password' => 'password',
            'status' => UserStatus::ACTIVE,
        ]);

        $network = Network::create([
            'owner_id' => $owner->user_id,
            'network_name' => 'Overview Network '.uniqid(),
            'network_phone_number' => $owner->user_phone_number,
            'governorate_id' => $governorateId,
            'directorate_id' => $directorateId,
            'status' => NetworkStatus::ACTIVE,
        ]);

        NetworkUser::create([
            'network_id' => $network->network_id,
            'user_id' => $owner->user_id,
            'role_id' => $ownerRole->role_id,
            'status' => 'ACTIVE',
            'last_event_seq' => 1,
        ]);

        $otherOwner = User::create([
            'role_id' => null,
            'user_full_name' => 'Another Network Owner',
            'user_phone_number' => '7'.random_int(100000000, 999999999),
            'user_password' => 'password',
            'status' => UserStatus::ACTIVE,
        ]);

        $otherNetwork = Network::create([
            'owner_id' => $otherOwner->user_id,
            'network_name' => 'Other Network '.uniqid(),
            'network_phone_number' => $otherOwner->user_phone_number,
            'governorate_id' => $governorateId,
            'directorate_id' => $directorateId,
            'status' => NetworkStatus::ACTIVE,
        ]);

        NetworkUser::create([
            'network_id' => $otherNetwork->network_id,
            'user_id' => $otherOwner->user_id,
            'role_id' => $ownerRole->role_id,
            'status' => 'ACTIVE',
            'last_event_seq' => 1,
        ]);

        $activePos = Pos::create([
            'owner_id' => $owner->user_id,
            'pos_name' => 'Active POS '.uniqid(),
            'pos_phone_number' => '73'.random_int(100000000, 999999999),
            'governorate_id' => $governorateId,
            'directorate_id' => $directorateId,
            'status' => PosStatus::ACTIVE,
        ]);
        $inactivePos = Pos::create([
            'owner_id' => $otherOwner->user_id,
            'pos_name' => 'Inactive POS '.uniqid(),
            'pos_phone_number' => '73'.random_int(100000000, 999999999),
            'governorate_id' => $governorateId,
            'directorate_id' => $directorateId,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $network->network_id,
            'pos_id' => $activePos->pos_id,
            'status' => true,
        ]);
        NetworkPos::create([
            'network_id' => $network->network_id,
            'pos_id' => $inactivePos->pos_id,
            'status' => false,
        ]);

        $wallet = NetworkWallet::create([
            'network_id' => $network->network_id,
            'wallet_balance' => '123456.78',
            'status' => true,
        ]);

        $now = now('Asia/Aden');

        ProcurementRequest::create([
            'network_id' => $network->network_id,
            'pos_id' => $activePos->pos_id,
            'request_uuid' => 'REQ-'.uniqid(),
            'sale_type' => SaleType::CREDIT,
            'request_total_amount' => '900.00',
            'request_date' => $now->copy()->subDay(),
            'status' => RequestStatus::PENDING,
            'created_by' => $owner->user_id,
        ]);
        ProcurementRequest::create([
            'network_id' => $otherNetwork->network_id,
            'pos_id' => $inactivePos->pos_id,
            'request_uuid' => 'REQ-'.uniqid(),
            'sale_type' => SaleType::CASH,
            'request_total_amount' => '5000.00',
            'request_date' => $now->copy()->subDay(),
            'status' => RequestStatus::PENDING,
            'created_by' => $owner->user_id,
        ]);

        SaleInvoice::create([
            'network_id' => $network->network_id,
            'pos_id' => $activePos->pos_id,
            'invoice_uuid' => 'INV-'.uniqid(),
            'sale_type' => SaleType::CREDIT,
            'invoice_total_amount' => '1000.00',
            'paid_amount' => '400.00',
            'remaining_amount' => '600.00',
            'payment_status' => 'PARTIALLY_PAID',
            'invoice_date' => $now->copy()->subDays(2),
            'created_by' => $owner->user_id,
        ]);
        SaleInvoice::create([
            'network_id' => $otherNetwork->network_id,
            'pos_id' => $activePos->pos_id,
            'invoice_uuid' => 'INV-'.uniqid(),
            'sale_type' => SaleType::CASH,
            'invoice_total_amount' => '9999.00',
            'paid_amount' => '9999.00',
            'remaining_amount' => '0.00',
            'payment_status' => 'PAID',
            'invoice_date' => $now->copy()->subDays(2),
            'created_by' => $owner->user_id,
        ]);

        Expense::create([
            'network_id' => $network->network_id,
            'expense_uuid' => 'EXP-'.uniqid(),
            'expense_total_amount' => '200.00',
            'expense_date' => $now->copy()->subDay(),
            'created_by' => $owner->user_id,
        ]);
        Expense::create([
            'network_id' => $otherNetwork->network_id,
            'expense_uuid' => 'EXP-'.uniqid(),
            'expense_total_amount' => '7000.00',
            'expense_date' => $now->copy()->subDay(),
            'created_by' => $owner->user_id,
        ]);

        $headers = $this->createAuthenticatedSession($owner)['headers'];
        $response = $this->getJson(
            "/api/v1/networks/{$network->network_id}/workspace-overview?period=30d",
            $headers
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.timezone', 'Asia/Aden')
            ->assertJsonPath('data.period.key', '30d')
            ->assertJsonPath('data.network.network_id', $network->network_id)
            ->assertJsonPath('data.metrics.points_of_sale.total', 2)
            ->assertJsonPath('data.metrics.points_of_sale.active', 1)
            ->assertJsonPath('data.metrics.points_of_sale.inactive', 1)
            ->assertJsonPath('data.metrics.requests.pending', 1)
            ->assertJsonPath('data.metrics.sales.total_amount', '1000.00')
            ->assertJsonPath('data.metrics.sales.paid_amount', '400.00')
            ->assertJsonPath('data.metrics.sales.outstanding_amount', '600.00')
            ->assertJsonPath('data.metrics.expenses.total_amount', '200.00')
            ->assertJsonPath('data.metrics.wallet.balance', '123456.78')
            ->assertJsonPath('data.recent_pending_requests.0.pos_id', $activePos->pos_id)
            ->assertJsonPath('data.recent_pending_requests.0.sale_type_label', 'آجل');

        $this->assertCount(1, $response->json('data.recent_pending_requests'));
        $this->assertNotSame(
            $otherNetwork->network_id,
            $response->json('data.network.network_id')
        );

        $this->getJson(
            "/api/v1/networks/{$otherNetwork->network_id}/workspace-overview",
            $headers
        )->assertForbidden();
    }

    public function test_workspace_overview_rejects_an_unsupported_period(): void
    {
        [$governorateId, $directorateId] = $this->geographicIds();
        $role = Role::query()
            ->where('role_key', RoleKey::NETWORK_OWNER->value)
            ->firstOrFail();

        $owner = User::create([
            'role_id' => null,
            'user_full_name' => 'Network Period Owner',
            'user_phone_number' => '7'.random_int(100000000, 999999999),
            'user_password' => 'password',
            'status' => UserStatus::ACTIVE,
        ]);

        $network = Network::create([
            'owner_id' => $owner->user_id,
            'network_name' => 'Period Network '.uniqid(),
            'network_phone_number' => $owner->user_phone_number,
            'governorate_id' => $governorateId,
            'directorate_id' => $directorateId,
            'status' => NetworkStatus::ACTIVE,
        ]);

        NetworkUser::create([
            'network_id' => $network->network_id,
            'user_id' => $owner->user_id,
            'role_id' => $role->role_id,
            'status' => 'ACTIVE',
            'last_event_seq' => 1,
        ]);

        $headers = $this->createAuthenticatedSession($owner)['headers'];

        $this->getJson(
            "/api/v1/networks/{$network->network_id}/workspace-overview?period=all",
            $headers
        )->assertUnprocessable()->assertJsonValidationErrors('period');
    }

    public function test_workspace_overview_omits_financial_metrics_for_a_network_user_without_finance_permissions(): void
    {
        [$governorateId, $directorateId] = $this->geographicIds();
        $ownerRole = Role::query()
            ->where('role_key', RoleKey::NETWORK_OWNER->value)
            ->firstOrFail();
        $memberRole = Role::query()
            ->where('role_key', RoleKey::NETWORK_USER->value)
            ->firstOrFail();

        $owner = User::create([
            'role_id' => null,
            'user_full_name' => 'Workspace Member Owner',
            'user_phone_number' => '7'.random_int(100000000, 999999999),
            'user_password' => 'password',
            'status' => UserStatus::ACTIVE,
        ]);
        $member = User::create([
            'role_id' => null,
            'user_full_name' => 'Workspace Network Member',
            'user_phone_number' => '7'.random_int(100000000, 999999999),
            'user_password' => 'password',
            'status' => UserStatus::ACTIVE,
        ]);

        $network = Network::create([
            'owner_id' => $owner->user_id,
            'network_name' => 'Member Network '.uniqid(),
            'network_phone_number' => $owner->user_phone_number,
            'governorate_id' => $governorateId,
            'directorate_id' => $directorateId,
            'status' => NetworkStatus::ACTIVE,
        ]);

        NetworkUser::create([
            'network_id' => $network->network_id,
            'user_id' => $owner->user_id,
            'role_id' => $ownerRole->role_id,
            'status' => 'ACTIVE',
            'last_event_seq' => 1,
        ]);

        NetworkUser::create([
            'network_id' => $network->network_id,
            'user_id' => $member->user_id,
            'role_id' => $memberRole->role_id,
            'status' => 'ACTIVE',
        ]);

        NetworkWallet::create([
            'network_id' => $network->network_id,
            'wallet_balance' => '999.00',
            'status' => true,
        ]);

        $headers = $this->createAuthenticatedSession($member)['headers'];
        $response = $this->getJson(
            "/api/v1/networks/{$network->network_id}/workspace-overview",
            $headers
        );

        $response->assertOk();
        $this->assertArrayNotHasKey('wallet', $response->json('data.metrics'));
        $this->assertArrayNotHasKey('expenses', $response->json('data.metrics'));
    }
}
