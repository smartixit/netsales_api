<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Http\Controllers\API\V1\BaseController;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoucherPackageTenantAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $networkOwnerA;

    protected User $networkOwnerB;

    protected User $posOwnerA;

    protected User $networkAccountantA;

    protected array $headersNetA = [];

    protected array $headersNetB = [];

    protected array $headersPosA = [];

    protected array $headersAccountantA = [];

    protected Network $networkA;

    protected Network $networkB;

    protected Pos $posA;

    protected Pos $posB;

    protected VoucherPackage $packageA;

    protected VoucherPackage $packageAInactive;

    protected VoucherPackage $packageB;

    protected VoucherPackageDiscount $discountPosA;

    protected VoucherPackageDiscount $discountPosB;

    protected function setUp(): void
    {
        parent::setUp();

        $gov = Governorate::first() ?? Governorate::create(['governorate_name' => 'Sanaa']);
        $dir = Directorate::where('governorate_id', $gov->governorate_id)->first()
            ?? Directorate::create(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Main']);

        $suffix = uniqid();

        // 1. Create Users
        [$this->networkOwnerA, $this->headersNetA] = $this->createUserWithSession('net_owner_a_'.$suffix);
        [$this->networkOwnerB, $this->headersNetB] = $this->createUserWithSession('net_owner_b_'.$suffix);
        [$this->posOwnerA, $this->headersPosA] = $this->createUserWithSession('pos_owner_a_'.$suffix);
        [$posOwnerB, $headersPosB] = $this->createUserWithSession('pos_owner_b_'.$suffix);
        [$this->networkAccountantA, $this->headersAccountantA] = $this->createUserWithSession('net_acc_a_'.$suffix);

        // 2. Create Networks
        $this->networkA = Network::create([
            'owner_id' => $this->networkOwnerA->user_id,
            'network_name' => 'شبكة الأمان A '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->networkB = Network::create([
            'owner_id' => $this->networkOwnerB->user_id,
            'network_name' => 'شبكة الأمان B '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        // Assign Network memberships for owners
        $netOwnerRoleId = Role::where('role_key', 'NETWORK_OWNER')->value('role_id');
        NetworkUser::create([
            'network_id' => $this->networkA->network_id,
            'user_id' => $this->networkOwnerA->user_id,
            'role_id' => $netOwnerRoleId,
            'status' => 'ACTIVE',
        ]);
        NetworkUser::create([
            'network_id' => $this->networkB->network_id,
            'user_id' => $this->networkOwnerB->user_id,
            'role_id' => $netOwnerRoleId,
            'status' => 'ACTIVE',
        ]);

        // 3. Create POS entities
        $this->posA = Pos::create([
            'owner_id' => $this->posOwnerA->user_id,
            'pos_name' => 'نقطة الأمان A '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        $posAgentRoleId = Role::where('role_key', 'POS_AGENT')->value('role_id');
        PosUser::create([
            'pos_id' => $this->posA->pos_id,
            'user_id' => $this->posOwnerA->user_id,
            'role_id' => $posAgentRoleId,
            'status' => 'ACTIVE',
        ]);

        $this->posB = Pos::create([
            'owner_id' => $posOwnerB->user_id,
            'pos_name' => 'نقطة الأمان B '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        // 4. Link POS A to Network A (Active Link)
        NetworkPos::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'status' => true,
        ]);

        // Link POS B to Network A (Active Link)
        NetworkPos::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posB->pos_id,
            'status' => true,
        ]);

        // 5. Network A Accountant Membership (View only)
        $accountantRole = Role::where('role_key', 'NETWORK_ACCOUNTANT')->firstOrFail();

        NetworkUser::create([
            'network_id' => $this->networkA->network_id,
            'user_id' => $this->networkAccountantA->user_id,
            'role_id' => $accountantRole->role_id,
            'status' => 'ACTIVE',
        ]);

        // 6. Packages
        $this->packageA = VoucherPackage::create([
            'network_id' => $this->networkA->network_id,
            'created_by' => $this->networkOwnerA->user_id,
            'package_name' => 'باقة شبكة A '.$suffix,
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.0,
            'package_default_credit_discount' => 5.0,
            'status' => true,
        ]);

        $this->packageAInactive = VoucherPackage::create([
            'network_id' => $this->networkA->network_id,
            'created_by' => $this->networkOwnerA->user_id,
            'package_name' => 'باقة معطلة لشبكة A '.$suffix,
            'package_voucher_price' => 1500.00,
            'package_default_cash_discount' => 10.0,
            'package_default_credit_discount' => 5.0,
            'status' => false,
        ]);

        $this->packageB = VoucherPackage::create([
            'network_id' => $this->networkB->network_id,
            'created_by' => $this->networkOwnerB->user_id,
            'package_name' => 'باقة شبكة B '.$suffix,
            'package_voucher_price' => 2000.00,
            'package_default_cash_discount' => 12.0,
            'package_default_credit_discount' => 6.0,
            'status' => true,
        ]);

        // 7. Discounts
        $this->discountPosA = VoucherPackageDiscount::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'package_id' => $this->packageA->package_id,
            'created_by' => $this->networkOwnerA->user_id,
            'discount_cash' => 15.0,
            'discount_credit' => 8.0,
            'status' => true,
        ]);

        $this->discountPosB = VoucherPackageDiscount::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posB->pos_id,
            'package_id' => $this->packageA->package_id,
            'created_by' => $this->networkOwnerA->user_id,
            'discount_cash' => 20.0,
            'discount_credit' => 10.0,
            'status' => true,
        ]);
    }

    protected function createUserWithSession(string $username): array
    {
        $user = User::create([
            'user_full_name' => $username,
            'user_email' => $username.'@netsales.local',
            'user_phone_number' => '7'.rand(10000000, 99999999),
            'user_password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);

        $devUuid = 'DEV-'.uniqid();
        UserDevice::create([
            'user_id' => $user->user_id,
            'device_uuid' => $devUuid,
            'device_name' => 'Test Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $session = UserSession::create([
            'user_id' => $user->user_id,
            'device_uuid' => $devUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $headers = [
            'X-Session-Id' => (string) $session->session_id,
            'X-Device-Uuid' => $devUuid,
            'X-Bypass-Encryption' => 'true',
        ];

        return [$user, $headers];
    }

    #[Test]
    public function network_owner_can_manage_own_network_packages_but_cannot_access_foreign_network()
    {
        // 1. Owner A can view Package A
        $res = $this->withHeaders($this->headersNetA)
            ->getJson("/api/v1/voucher-packages/{$this->packageA->package_id}");
        $res->assertOk();
        $this->assertEquals($this->packageA->package_id, $res->json('data.package_id'));

        // 2. Owner A CANNOT view Package B (Foreign tenant)
        $res = $this->withHeaders($this->headersNetA)
            ->getJson("/api/v1/voucher-packages/{$this->packageB->package_id}");
        $res->assertStatus(403);

        // 3. Owner A CANNOT update Package B
        $res = $this->withHeaders($this->headersNetA)
            ->putJson("/api/v1/voucher-packages/{$this->packageB->package_id}", [
                'package_name' => 'محاولة اختراق اسم باقة B',
            ]);
        $res->assertStatus(403);

        // 4. Owner A CANNOT delete Package B
        $res = $this->withHeaders($this->headersNetA)
            ->deleteJson("/api/v1/voucher-packages/{$this->packageB->package_id}");
        $res->assertStatus(403);

        // 5. Owner A CANNOT create package under Network B
        $res = $this->withHeaders($this->headersNetA)
            ->postJson('/api/v1/voucher-packages', [
                'network_id' => $this->networkB->network_id,
                'package_name' => 'باقة متسللة لشبكة B',
                'package_voucher_price' => 500.0,
                'package_default_cash_discount' => 5.0,
                'package_default_credit_discount' => 2.0,
            ]);
        $res->assertStatus(403);
    }

    #[Test]
    public function package_index_strictly_isolates_catalog_across_networks()
    {
        // Owner A lists packages - MUST contain Package A and MUST NOT contain Package B
        $resA = $this->withHeaders($this->headersNetA)->getJson('/api/v1/voucher-packages');
        $resA->assertOk();
        $idsA = collect($resA->json('data'))->pluck('package_id')->toArray();
        $this->assertContains($this->packageA->package_id, $idsA);
        $this->assertNotContains($this->packageB->package_id, $idsA);

        // Owner B lists packages - MUST contain Package B and MUST NOT contain Package A
        $resB = $this->withHeaders($this->headersNetB)->getJson('/api/v1/voucher-packages');
        $resB->assertOk();
        $idsB = collect($resB->json('data'))->pluck('package_id')->toArray();
        $this->assertContains($this->packageB->package_id, $idsB);
        $this->assertNotContains($this->packageA->package_id, $idsB);

        // Owner A explicitly filters for foreign network_id => 403 Forbidden
        $resForbidden = $this->withHeaders($this->headersNetA)
            ->getJson("/api/v1/voucher-packages?network_id={$this->networkB->network_id}");
        $resForbidden->assertStatus(403);
    }

    #[Test]
    public function network_accountant_is_read_only_and_cannot_mutate_packages()
    {
        // 1. Accountant can view package in own network
        $res = $this->withHeaders($this->headersAccountantA)
            ->getJson("/api/v1/voucher-packages/{$this->packageA->package_id}");
        $res->assertOk();

        // 2. Accountant CANNOT create package
        $res = $this->withHeaders($this->headersAccountantA)
            ->postJson('/api/v1/voucher-packages', [
                'network_id' => $this->networkA->network_id,
                'package_name' => 'باقة من محاسب غير مصرح',
                'package_voucher_price' => 500.0,
                'package_default_cash_discount' => 5.0,
                'package_default_credit_discount' => 2.0,
            ]);
        $res->assertStatus(403);

        // 3. Accountant CANNOT update package
        $res = $this->withHeaders($this->headersAccountantA)
            ->putJson("/api/v1/voucher-packages/{$this->packageA->package_id}", [
                'package_name' => 'تعديل محاسب محظور',
            ]);
        $res->assertStatus(403);

        // 4. Accountant CANNOT delete package
        $res = $this->withHeaders($this->headersAccountantA)
            ->deleteJson("/api/v1/voucher-packages/{$this->packageA->package_id}");
        $res->assertStatus(403);
    }

    #[Test]
    public function pos_user_can_view_linked_network_packages_but_cannot_view_unlinked_networks()
    {
        // POS A is linked to Network A: Can view Network A packages
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/networks/{$this->networkA->network_id}/voucher-packages");
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('package_id')->toArray();
        $this->assertContains($this->packageA->package_id, $ids);

        // POS A can view specific active package in Network A
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-packages/{$this->packageA->package_id}");
        $res->assertOk();

        // POS A is NOT linked to Network B: Cannot view Network B packages
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/networks/{$this->networkB->network_id}/voucher-packages");
        $res->assertStatus(403);

        // POS A cannot view specific package in Network B
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-packages/{$this->packageB->package_id}");
        $res->assertStatus(403);

        // POS A CANNOT mutate any package
        $res = $this->withHeaders($this->headersPosA)
            ->putJson("/api/v1/voucher-packages/{$this->packageA->package_id}", ['package_name' => 'POS tamper']);
        $res->assertStatus(403);
    }

    #[Test]
    public function pos_user_can_only_view_own_discounts_and_never_other_pos_discounts()
    {
        // 1. POS A can view its own discount
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts/{$this->discountPosA->discount_id}");
        $res->assertOk();
        $this->assertEquals($this->posA->pos_id, $res->json('data.pos_id'));

        // 2. POS A CANNOT view POS B's discount (Forbidden!)
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts/{$this->discountPosB->discount_id}");
        $res->assertStatus(403);

        // 3. POS A querying discounts list only receives POS A records
        $res = $this->withHeaders($this->headersPosA)
            ->getJson('/api/v1/voucher-package-discounts');
        $res->assertOk();
        $posIds = collect($res->json('data'))->pluck('pos_id')->unique()->toArray();
        $this->assertEquals([$this->posA->pos_id], $posIds);

        // 4. POS A attempting to filter for POS B explicitly is rejected with 403
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts?pos_id={$this->posB->pos_id}");
        $res->assertStatus(403);

        // 5. POS A cannot create/update/delete any discount
        $res = $this->withHeaders($this->headersPosA)
            ->deleteJson("/api/v1/voucher-package-discounts/{$this->discountPosA->discount_id}");
        $res->assertStatus(403);
    }

    #[Test]
    public function effective_discount_enforces_strict_authorization_and_cross_tenant_isolation()
    {
        // 1. POS A can calculate effective discount for (Network A, POS A, Package A)
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts/effective?network_id={$this->networkA->network_id}&pos_id={$this->posA->pos_id}&package_id={$this->packageA->package_id}");
        $res->assertOk();
        $this->assertTrue($res->json('data.is_custom'));
        $this->assertEquals(15.0, (float) $res->json('data.discount_cash'));

        // 2. POS A CANNOT calculate effective discount for POS B (Forbidden impersonation!)
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts/effective?network_id={$this->networkA->network_id}&pos_id={$this->posB->pos_id}&package_id={$this->packageA->package_id}");
        $res->assertStatus(403);

        // 3. POS A CANNOT calculate effective discount for unlinked Network B
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts/effective?network_id={$this->networkB->network_id}&pos_id={$this->posA->pos_id}&package_id={$this->packageB->package_id}");
        $res->assertStatus(403);

        // 4. Network Owner B CANNOT calculate effective discount for Network A
        $res = $this->withHeaders($this->headersNetB)
            ->getJson("/api/v1/voucher-package-discounts/effective?network_id={$this->networkA->network_id}&pos_id={$this->posA->pos_id}&package_id={$this->packageA->package_id}");
        $res->assertStatus(403);
    }

    #[Test]
    public function batch_operations_fail_atomically_when_containing_cross_tenant_records()
    {
        // Network A Owner attempts to batch delete both Package A and foreign Package B
        $res = $this->withHeaders($this->headersNetA)
            ->postJson('/api/v1/voucher-packages/batch-delete', [
                'ids' => [$this->packageA->package_id, $this->packageB->package_id],
            ]);

        // MUST be rejected with 403 Forbidden
        $res->assertStatus(403);

        // ATOMICITY CHECK: Package A MUST NOT be deleted!
        $this->packageA->refresh();
        $this->assertNull($this->packageA->deleted_at);

        // Package B MUST NOT be deleted!
        $this->packageB->refresh();
        $this->assertNull($this->packageB->deleted_at);
    }

    #[Test]
    public function discount_creation_rejects_mismatched_network_or_unlinked_pos()
    {
        // 1. Trying to create discount for Package B under Network A (Package B belongs to Network B)
        $res = $this->withHeaders($this->headersNetA)
            ->postJson('/api/v1/voucher-package-discounts', [
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posA->pos_id,
                'package_id' => $this->packageB->package_id, // Mismatched!
                'discount_cash' => 25.0,
                'discount_credit' => 15.0,
            ]);
        $res->assertStatus(422);

        // 2. Trying to create discount for an unlinked POS under Network B
        $res = $this->withHeaders($this->headersNetB)
            ->postJson('/api/v1/voucher-package-discounts', [
                'network_id' => $this->networkB->network_id,
                'pos_id' => $this->posA->pos_id, // POS A is NOT linked to Network B!
                'package_id' => $this->packageB->package_id,
                'discount_cash' => 20.0,
                'discount_credit' => 10.0,
            ]);
        $res->assertStatus(422);
    }

    #[Test]
    public function pos_user_receives_only_active_packages_and_cannot_see_inactive_packages_in_general_or_network_list()
    {
        // 1. POS A general package list: receives active packageA, but NOT inactive packageAInactive, nor unlinked packageB
        $res = $this->withHeaders($this->headersPosA)->getJson('/api/v1/voucher-packages');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('package_id')->toArray();
        $this->assertContains($this->packageA->package_id, $ids);
        $this->assertNotContains($this->packageAInactive->package_id, $ids);
        $this->assertNotContains($this->packageB->package_id, $ids);

        // 2. POS A network-specific list: receives active packageA, but NOT inactive packageAInactive
        $res = $this->withHeaders($this->headersPosA)->getJson("/api/v1/networks/{$this->networkA->network_id}/voucher-packages");
        $res->assertOk();
        $netIds = collect($res->json('data'))->pluck('package_id')->toArray();
        $this->assertContains($this->packageA->package_id, $netIds);
        $this->assertNotContains($this->packageAInactive->package_id, $netIds);

        // 3. POS A explicitly requesting status=0: MUST NOT receive inactive package
        $res = $this->withHeaders($this->headersPosA)->getJson("/api/v1/networks/{$this->networkA->network_id}/voucher-packages?status=0");
        $res->assertOk();
        $filteredIds = collect($res->json('data'))->pluck('package_id')->toArray();
        $this->assertNotContains($this->packageAInactive->package_id, $filteredIds);

        // 4. Authorized Network Owner A: can see both active and inactive packages
        $resOwner = $this->withHeaders($this->headersNetA)->getJson('/api/v1/voucher-packages');
        $resOwner->assertOk();
        $ownerIds = collect($resOwner->json('data'))->pluck('package_id')->toArray();
        $this->assertContains($this->packageA->package_id, $ownerIds);
        $this->assertContains($this->packageAInactive->package_id, $ownerIds);

        // 5. Authorized Network Owner A explicitly filtering status=0: gets inactive package
        $resOwnerInactive = $this->withHeaders($this->headersNetA)->getJson("/api/v1/networks/{$this->networkA->network_id}/voucher-packages?status=0");
        $resOwnerInactive->assertOk();
        $ownerInactiveIds = collect($resOwnerInactive->json('data'))->pluck('package_id')->toArray();
        $this->assertContains($this->packageAInactive->package_id, $ownerInactiveIds);
        $this->assertNotContains($this->packageA->package_id, $ownerInactiveIds);

        // 6. POS A cannot see unlinked Network B packages
        $resUnlinked = $this->withHeaders($this->headersPosA)->getJson("/api/v1/networks/{$this->networkB->network_id}/voucher-packages");
        $resUnlinked->assertStatus(403);
    }

    #[Test]
    public function pos_user_can_list_discounts_only_while_commercial_relationship_is_active()
    {
        // 1. While relationship is active: POS A receives discountPosA
        $res = $this->withHeaders($this->headersPosA)->getJson('/api/v1/voucher-package-discounts');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('discount_id')->toArray();
        $this->assertContains($this->discountPosA->discount_id, $ids);

        // 2. Disable commercial relationship (status = false)
        NetworkPos::where('network_id', $this->networkA->network_id)
            ->where('pos_id', $this->posA->pos_id)
            ->update(['status' => false]);

        // POS A can no longer retrieve discount through list endpoint
        $resDisabled = $this->withHeaders($this->headersPosA)->getJson('/api/v1/voucher-package-discounts');
        $resDisabled->assertOk();
        $disabledIds = collect($resDisabled->json('data'))->pluck('discount_id')->toArray();
        $this->assertNotContains($this->discountPosA->discount_id, $disabledIds);

        // 3. Soft-delete the discount
        $this->discountPosA->delete();

        // POS A cannot retrieve trashed discount while relationship is disabled
        $resTrashedPos = $this->withHeaders($this->headersPosA)->getJson('/api/v1/voucher-package-discounts/trashed');
        $resTrashedPos->assertOk();
        $trashedPosIds = collect($resTrashedPos->json('data'))->pluck('discount_id')->toArray();
        $this->assertNotContains($this->discountPosA->discount_id, $trashedPosIds);

        // 4. Network Owner retains access to historical records even when POS link is inactive
        $resTrashedOwner = $this->withHeaders($this->headersNetA)->getJson('/api/v1/voucher-package-discounts/trashed');
        $resTrashedOwner->assertOk();
        $trashedOwnerIds = collect($resTrashedOwner->json('data'))->pluck('discount_id')->toArray();
        $this->assertContains($this->discountPosA->discount_id, $trashedOwnerIds);

        // 5. Restore relationship: POS A can see it in trash again
        NetworkPos::where('network_id', $this->networkA->network_id)
            ->where('pos_id', $this->posA->pos_id)
            ->update(['status' => true]);

        $resTrashedRestored = $this->withHeaders($this->headersPosA)->getJson('/api/v1/voucher-package-discounts/trashed');
        $resTrashedRestored->assertOk();
        $trashedRestoredIds = collect($resTrashedRestored->json('data'))->pluck('discount_id')->toArray();
        $this->assertContains($this->discountPosA->discount_id, $trashedRestoredIds);

        $this->discountPosA->restore();
    }

    #[Test]
    public function explicit_filters_cannot_bypass_pos_authorization_scope()
    {
        // 1. POS A cannot query other POS discounts via pos_id filter -> 403
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts?pos_id={$this->posB->pos_id}");
        $res->assertStatus(403);

        // 2. POS A cannot query unlinked network discounts via network_id filter -> 403
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts?network_id={$this->networkB->network_id}");
        $res->assertStatus(403);

        // 3. POS A filtering by package_id of foreign network returns empty (no access bypass)
        $res = $this->withHeaders($this->headersPosA)
            ->getJson("/api/v1/voucher-package-discounts?package_id={$this->packageB->package_id}");
        $res->assertOk();
        $this->assertEmpty($res->json('data'));
    }

    #[Test]
    public function mixed_user_retains_network_visibility_and_pos_relationship_restrictions()
    {
        $suffix = uniqid();
        [$mixedUser, $mixedHeaders] = $this->createUserWithSession('mixed_user_'.$suffix);

        // Network B membership (Network-side access)
        $netUserRoleId = Role::where('role_key', 'NETWORK_USER')->value('role_id');
        NetworkUser::create([
            'network_id' => $this->networkB->network_id,
            'user_id' => $mixedUser->user_id,
            'role_id' => $netUserRoleId,
            'status' => 'ACTIVE',
        ]);

        $packageBInactive = VoucherPackage::create([
            'network_id' => $this->networkB->network_id,
            'created_by' => $this->networkOwnerB->user_id,
            'package_name' => 'باقة معطلة لشبكة B '.$suffix,
            'package_voucher_price' => 2500.00,
            'package_default_cash_discount' => 10.0,
            'package_default_credit_discount' => 5.0,
            'status' => false,
        ]);

        // POS A membership (POS-side access, linked to Network A)
        $posUserRoleId = Role::where('role_key', 'POS_USER')->value('role_id');
        PosUser::create([
            'pos_id' => $this->posA->pos_id,
            'user_id' => $mixedUser->user_id,
            'role_id' => $posUserRoleId,
            'status' => 'ACTIVE',
        ]);

        // Discount for Network B
        $discountNetB = VoucherPackageDiscount::create([
            'network_id' => $this->networkB->network_id,
            'pos_id' => $this->posB->pos_id,
            'package_id' => $this->packageB->package_id,
            'created_by' => $this->networkOwnerB->user_id,
            'discount_cash' => 5.0,
            'discount_credit' => 2.0,
            'status' => true,
        ]);

        // Mixed user lists packages:
        // From Network B: sees active packageB AND inactive packageBInactive
        // From Network A: sees active packageA, but NEVER inactive packageAInactive
        $resPackages = $this->withHeaders($mixedHeaders)->getJson('/api/v1/voucher-packages');
        $resPackages->assertOk();
        $pkgIds = collect($resPackages->json('data'))->pluck('package_id')->toArray();
        $this->assertContains($this->packageB->package_id, $pkgIds);
        $this->assertContains($packageBInactive->package_id, $pkgIds);
        $this->assertContains($this->packageA->package_id, $pkgIds);
        $this->assertNotContains($this->packageAInactive->package_id, $pkgIds);

        // Mixed user lists discounts:
        $resDiscounts = $this->withHeaders($mixedHeaders)->getJson('/api/v1/voucher-package-discounts');
        $resDiscounts->assertOk();
        $discIds = collect($resDiscounts->json('data'))->pluck('discount_id')->toArray();
        $this->assertContains($discountNetB->discount_id, $discIds);
        $this->assertContains($this->discountPosA->discount_id, $discIds);

        // Disable Network A - POS A relationship:
        NetworkPos::where('network_id', $this->networkA->network_id)
            ->where('pos_id', $this->posA->pos_id)
            ->update(['status' => false]);

        // In active discount list: Network B discount remains visible, but POS A discount disappears
        $resDiscountsAfter = $this->withHeaders($mixedHeaders)->getJson('/api/v1/voucher-package-discounts');
        $resDiscountsAfter->assertOk();
        $discIdsAfter = collect($resDiscountsAfter->json('data'))->pluck('discount_id')->toArray();
        $this->assertContains($discountNetB->discount_id, $discIdsAfter);
        $this->assertNotContains($this->discountPosA->discount_id, $discIdsAfter);

        // Restore link
        NetworkPos::where('network_id', $this->networkA->network_id)
            ->where('pos_id', $this->posA->pos_id)
            ->update(['status' => true]);
    }

    #[Test]
    public function stateless_current_user_does_not_leak_across_requests_on_reused_controller_instance()
    {
        $controller = new class extends BaseController
        {
            public function resolveUser(bool $fallback = false): ?User
            {
                return $this->currentUser($fallback);
            }
        };

        // 1. Authenticated context resolves user
        auth()->login($this->networkOwnerA);
        $u1 = $controller->resolveUser(false);
        $this->assertNotNull($u1);
        $this->assertEquals($this->networkOwnerA->user_id, $u1->user_id);

        // 2. Subsequent unauthenticated call on SAME controller instance MUST NOT return previous user
        auth()->logout();
        $u2 = $controller->resolveUser(false);
        $this->assertNull($u2);

        // 3. Fallback to system returns system user
        $sysUser = $controller->resolveUser(true);
        $this->assertNotNull($sysUser);
        $this->assertEquals(0, $sysUser->user_id);

        // 4. Subsequent unauthenticated call without fallback MUST NOT return system user
        $u3 = $controller->resolveUser(false);
        $this->assertNull($u3);
    }

    #[Test]
    public function batch_operations_fail_atomically_for_remaining_package_and_discount_endpoints()
    {
        // 1. Package batch-restore:
        $this->packageA->delete();
        $this->packageB->delete();

        $resPkgRestore = $this->withHeaders($this->headersNetA)
            ->postJson('/api/v1/voucher-packages/batch-restore', [
                'ids' => [$this->packageA->package_id, $this->packageB->package_id],
            ]);
        $resPkgRestore->assertStatus(403);
        $this->assertNotNull($this->packageA->fresh()->deleted_at);
        $this->assertNotNull($this->packageB->fresh()->deleted_at);

        // 2. Package batch-force-delete:
        $resPkgForce = $this->withHeaders($this->headersNetA)
            ->postJson('/api/v1/voucher-packages/batch-force-delete', [
                'ids' => [$this->packageA->package_id, $this->packageB->package_id],
            ]);
        $resPkgForce->assertStatus(403);
        $this->assertDatabaseHas('voucher_packages', ['package_id' => $this->packageA->package_id]);
        $this->assertDatabaseHas('voucher_packages', ['package_id' => $this->packageB->package_id]);

        $this->packageA->restore();
        $this->packageB->restore();

        // 3. Create a discount for Network B
        $discountNetB = VoucherPackageDiscount::create([
            'network_id' => $this->networkB->network_id,
            'pos_id' => $this->posB->pos_id,
            'package_id' => $this->packageB->package_id,
            'created_by' => $this->networkOwnerB->user_id,
            'discount_cash' => 10.0,
            'discount_credit' => 5.0,
            'status' => true,
        ]);

        // 4. Discount batch-delete:
        $resDiscDel = $this->withHeaders($this->headersNetA)
            ->postJson('/api/v1/voucher-package-discounts/batch-delete', [
                'ids' => [$this->discountPosA->discount_id, $discountNetB->discount_id],
            ]);
        $resDiscDel->assertStatus(403);
        $this->assertNull($this->discountPosA->fresh()->deleted_at);
        $this->assertNull($discountNetB->fresh()->deleted_at);

        // 5. Discount batch-restore:
        $this->discountPosA->delete();
        $discountNetB->delete();

        $resDiscRestore = $this->withHeaders($this->headersNetA)
            ->postJson('/api/v1/voucher-package-discounts/batch-restore', [
                'ids' => [$this->discountPosA->discount_id, $discountNetB->discount_id],
            ]);
        $resDiscRestore->assertStatus(403);
        $this->assertNotNull($this->discountPosA->fresh()->deleted_at);
        $this->assertNotNull($discountNetB->fresh()->deleted_at);

        // 6. Discount batch-force-delete:
        $resDiscForce = $this->withHeaders($this->headersNetA)
            ->postJson('/api/v1/voucher-package-discounts/batch-force-delete', [
                'ids' => [$this->discountPosA->discount_id, $discountNetB->discount_id],
            ]);
        $resDiscForce->assertStatus(403);
        $this->assertDatabaseHas('voucher_package_discounts', ['discount_id' => $this->discountPosA->discount_id]);
        $this->assertDatabaseHas('voucher_package_discounts', ['discount_id' => $discountNetB->discount_id]);

        $this->discountPosA->restore();
        $discountNetB->restore();
    }
}
