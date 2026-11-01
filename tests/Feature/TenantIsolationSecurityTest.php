<?php

namespace Tests\Feature;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleKey;
use App\Enums\SaleType;
use App\Enums\UserStatus;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\VoucherPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantIsolationSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $networkOwnerA;

    protected User $networkOwnerB;

    protected User $posOwnerA;

    protected User $posOwnerB;

    protected Network $networkA;

    protected Network $networkB;

    protected Pos $posA;

    protected Pos $posB;

    protected VoucherPackage $packageA;

    protected SaleInvoice $invoiceA;

    protected SaleInvoice $invoiceB;

    protected function setUp(): void
    {
        parent::setUp();

        $posRole = Role::where('role_key', RoleKey::POS_AGENT->value)->first();
        $netRole = Role::where('role_key', RoleKey::NETWORK_OWNER->value)->first();
        $gov = Governorate::first() ?? Governorate::create(['governorate_name' => 'Sanaa']);
        $dir = Directorate::first() ?? Directorate::create(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Main']);

        // 1. Create Network Owners
        $this->networkOwnerA = User::create([
            'role_id' => null,
            'user_full_name' => 'Net Owner A',
            'user_phone_number' => '77111111'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->networkOwnerB = User::create([
            'role_id' => null,
            'user_full_name' => 'Net Owner B',
            'user_phone_number' => '77222222'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // 2. Create POS Owners
        $this->posOwnerA = User::create([
            'role_id' => null,
            'user_full_name' => 'POS Owner A',
            'user_phone_number' => '77333333'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->posOwnerB = User::create([
            'role_id' => null,
            'user_full_name' => 'POS Owner B',
            'user_phone_number' => '77444444'.rand(10, 99),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // 3. Create Networks
        $this->networkA = Network::create([
            'owner_id' => $this->networkOwnerA->user_id,
            'network_name' => 'Network A',
            'network_phone_number' => '771111110',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->networkB = Network::create([
            'owner_id' => $this->networkOwnerB->user_id,
            'network_name' => 'Network B',
            'network_phone_number' => '772222220',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        // 4. Create POS
        $this->posA = Pos::create([
            'owner_id' => $this->posOwnerA->user_id,
            'pos_name' => 'POS A',
            'pos_phone_number' => '773333330',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        $this->posB = Pos::create([
            'owner_id' => $this->posOwnerB->user_id,
            'pos_name' => 'POS B',
            'pos_phone_number' => '774444440',
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        // Link POS to Networks
        NetworkPos::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'is_active' => true,
        ]);

        NetworkPos::create([
            'network_id' => $this->networkB->network_id,
            'pos_id' => $this->posB->pos_id,
            'is_active' => true,
        ]);

        // Memberships
        NetworkUser::create([
            'network_id' => $this->networkA->network_id,
            'user_id' => $this->networkOwnerA->user_id,
            'role_id' => $netRole->role_id,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::create([
            'network_id' => $this->networkB->network_id,
            'user_id' => $this->networkOwnerB->user_id,
            'role_id' => $netRole->role_id,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $this->posA->pos_id,
            'user_id' => $this->posOwnerA->user_id,
            'role_id' => $posRole->role_id,
            'status' => 'ACTIVE',
        ]);

        PosUser::create([
            'pos_id' => $this->posB->pos_id,
            'user_id' => $this->posOwnerB->user_id,
            'role_id' => $posRole->role_id,
            'status' => 'ACTIVE',
        ]);

        // 5. Create Invoices
        $this->invoiceA = SaleInvoice::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'sale_type' => SaleType::CASH,
            'invoice_total_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'remaining_amount' => 0.00,
            'payment_status' => InvoicePaymentStatus::PAID,
            'invoice_date' => now(),
            'created_by' => $this->networkOwnerA->user_id,
        ]);

        $this->invoiceB = SaleInvoice::create([
            'network_id' => $this->networkB->network_id,
            'pos_id' => $this->posB->pos_id,
            'sale_type' => SaleType::CASH,
            'invoice_total_amount' => 2000.00,
            'paid_amount' => 2000.00,
            'remaining_amount' => 0.00,
            'payment_status' => InvoicePaymentStatus::PAID,
            'invoice_date' => now(),
            'created_by' => $this->networkOwnerB->user_id,
        ]);

        // 6. Create Package for Network A
        $this->packageA = VoucherPackage::create([
            'network_id' => $this->networkA->network_id,
            'package_name' => 'Package A',
            'package_price' => 500.00,
            'created_by' => $this->networkOwnerA->user_id,
            'is_active' => true,
        ]);
    }

    /**
     * SEC-01: An unauthorized POS user attempting to view an invoice belonging to another POS/Network receives 403 Forbidden.
     */
    public function test_pos_user_cannot_view_another_pos_invoice_idor(): void
    {
        $sessionData = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($sessionData['headers'])
            ->getJson("/api/v1/sale-invoices/{$this->invoiceB->invoice_id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'غير مصرح لك بالوصول إلى هذه الفاتورة.',
        ]);
    }

    /**
     * SEC-01: An authorized POS user can view their own POS invoice.
     */
    public function test_pos_user_can_view_own_invoice(): void
    {
        $sessionData = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($sessionData['headers'])
            ->getJson("/api/v1/sale-invoices/{$this->invoiceA->invoice_id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /**
     * SEC-01: An unauthorized Network owner cannot view an invoice from another network.
     */
    public function test_network_owner_cannot_view_another_network_invoice(): void
    {
        $sessionData = $this->createAuthenticatedSession($this->networkOwnerA);

        $response = $this->withHeaders($sessionData['headers'])
            ->getJson("/api/v1/sale-invoices/{$this->invoiceB->invoice_id}");

        $response->assertStatus(403);
    }

    /**
     * SEC-01: Invoice listing is scoped to the user's POS.
     */
    public function test_sale_invoices_index_is_strictly_scoped_for_pos(): void
    {
        $sessionData = $this->createAuthenticatedSession($this->posOwnerA);

        $response = $this->withHeaders($sessionData['headers'])
            ->getJson('/api/v1/sale-invoices');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('invoice_id')->toArray();

        $this->assertContains($this->invoiceA->invoice_id, $ids);
        $this->assertNotContains($this->invoiceB->invoice_id, $ids);
    }

    /**
     * SEC-02: A POS user cannot submit a request impersonating another POS.
     */
    public function test_pos_user_cannot_impersonate_another_pos_in_request(): void
    {
        $sessionData = $this->createAuthenticatedSession($this->posOwnerA);

        // Link POS B to Network A as well, so the network-pos relation is valid, but the user does NOT own POS B
        NetworkPos::firstOrCreate([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posB->pos_id,
        ], [
            'is_active' => true,
        ]);

        $payload = [
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posB->pos_id, // Attempting to order on behalf of POS B
            'sale_type' => SaleType::CASH->value,
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 10,
                ],
            ],
        ];

        $response = $this->withHeaders($sessionData['headers'])
            ->postJson('/api/v1/requests', $payload);

        // Must be rejected with 403 Forbidden or validation error
        $this->assertTrue(in_array($response->status(), [403, 422], true));
    }
}
