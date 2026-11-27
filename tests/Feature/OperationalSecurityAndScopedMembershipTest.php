<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Enums\SaleType;
use App\Enums\UserStatus;
use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkUser;
use App\Models\Pos;
use App\Models\PosUser;
use App\Models\Request as VoucherRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\VoucherPackage;
use App\Services\RequestService;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\CreatesActiveBillingSubscription;
use Tests\TestCase;
use Tests\Concerns\CreatesProcurementRequestSnapshots;

class OperationalSecurityAndScopedMembershipTest extends TestCase
{
    use CreatesActiveBillingSubscription;
    use CreatesProcurementRequestSnapshots;
    use DatabaseTransactions;

    protected User $superAdmin;

    protected User $networkOwner;

    protected User $posOwner;

    protected Network $networkA;

    protected Network $networkB;

    protected Pos $posA;

    protected VoucherPackage $packageA;

    protected Role $networkMemberRole;

    protected Role $posMemberRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::whereHas('role', function ($q) {
            $q->where('role_key', RoleKey::SUPER_ADMIN->value);
        })->firstOrFail();

        $suffix = uniqid();

        // 1. استخدام الأدوار المعتمدة بالنظام للشبكة ونقطة البيع
        $this->networkMemberRole = Role::where('role_key', RoleKey::NETWORK_ACCOUNTANT->value)->firstOrFail();
        $this->posMemberRole = Role::where('role_key', 'POS_USER')->firstOrFail();

        // مستخدمي المستأجرين role_id = null لأن أدوارهم مقيدة بـ network_users و pos_users
        $this->networkOwner = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Net Owner '.$suffix,
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->posOwner = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'POS Owner '.$suffix,
            'user_phone_number' => '71'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $gov = Governorate::first() ?? Governorate::create(['governorate_name' => 'Sanaa']);
        $dir = Directorate::where('governorate_id', $gov->governorate_id)->first()
            ?? Directorate::create(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Main']);

        // 2. إنشاء الشبكات ونقاط البيع
        $this->networkA = Network::create([
            'owner_id' => $this->networkOwner->user_id,
            'network_name' => 'Operational Net A '.$suffix,
            'network_phone_number' => $this->networkOwner->user_phone_number,
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->createActiveBillingSubscriptionForNetwork($this->networkA, [], null, $this->networkOwner);

        NetworkUser::create([
            'network_id' => $this->networkA->network_id,
            'user_id' => $this->networkOwner->user_id,
            'role_id' => $this->networkMemberRole->role_id,
            'status' => 'ACTIVE',
        ]);

        $this->networkB = Network::create([
            'owner_id' => $this->superAdmin->user_id,
            'network_name' => 'Operational Net B '.$suffix,
            'network_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->posA = Pos::create([
            'owner_id' => $this->posOwner->user_id,
            'pos_name' => 'Operational POS A '.$suffix,
            'pos_phone_number' => $this->posOwner->user_phone_number,
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        PosUser::create([
            'pos_id' => $this->posA->pos_id,
            'user_id' => $this->posOwner->user_id,
            'role_id' => $this->posMemberRole->role_id,
            'status' => 'ACTIVE',
        ]);

        NetworkPos::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'status' => true,
        ]);

        $this->packageA = VoucherPackage::create([
            'network_id' => $this->networkA->network_id,
            'package_name' => 'Package Test '.$suffix,
            'package_price' => 1000,
            'package_hours' => 24,
            'status' => true,
            'created_by' => $this->networkOwner->user_id,
        ]);
    }

    /**
     * اختبار: اعتراض وصول SUPER_ADMIN وحظر دخوله للمسارات التشغيلية الميدانية (403 Forbidden).
     */
    public function test_operational_scope_rejects_super_admin_with_403(): void
    {
        $adminAuth = $this->createAuthenticatedSession($this->superAdmin);

        $response = $this->withHeaders($adminAuth['headers'])
            ->getJson("/api/v1/networks/{$this->networkA->network_id}/members");

        $response->assertStatus(403);
        $this->assertStringContainsString('نطاق المنصة', $response->json('message'));
    }

    /**
     * اختبار: إلزامية مفتاح منع التكرار (X-Idempotency-Key) في العمليات المالية الحساسة (422 Unprocessable).
     */
    public function test_missing_idempotency_key_on_financial_mutation_is_rejected(): void
    {
        $posAuth = $this->createAuthenticatedSession($this->posOwner);
        $headers = array_merge($posAuth['headers'], [
            'X-Strict-Idempotency' => 'true',
        ]);

        // طلب كروت دون إرسال X-Idempotency-Key في الوضع الصارم
        $response = $this->postProcurementRequest([
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posA->pos_id,
                'items' => [
                    [
                        'package_id' => $this->packageA->package_id,
                        'voucher_count' => 5,
                    ],
                ],
            ], $headers);

        $response->assertStatus(422);
        $this->assertStringContainsString('X-Idempotency-Key', $response->json('message'));
    }

    /**
     * اختبار: عمل مفتاح منع التكرار وإعادة الاستجابة المخزنة عند تكرار نفس المفتاح والبيانات.
     */
    public function test_valid_idempotency_key_replays_cached_response(): void
    {
        $posAuth = $this->createAuthenticatedSession($this->posOwner);
        $idempotencyKey = 'req-test-'.uniqid();

        $headers = array_merge($posAuth['headers'], [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        $payload = [
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'items' => [
                [
                    'package_id' => $this->packageA->package_id,
                    'voucher_count' => 5,
                ],
            ],
        ];

        // الطلب الأول
        $first = $this->postProcurementRequest($payload, $headers);
        $first->assertStatus(201);
        $firstRequestId = $first->json('data.request_id');

        // الطلب الثاني المتطابق تماماً بنفس المفتاح
        $second = $this->postProcurementRequest($payload, $headers);
        $second->assertStatus(201);
        $this->assertEquals($firstRequestId, $second->json('data.request_id'));
        $this->assertEquals('true', $second->headers->get('X-Idempotency-Hit'));
    }

    /**
     * اختبار أمني: منع ثغرة IDOR عند محاولة اعتماد طلب يتبع شبكة أخرى.
     */
    public function test_cross_network_request_approval_idor_is_strictly_blocked(): void
    {
        // إنشاء مستخدم شبكة B
        $netBUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Net B Reviewer',
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        NetworkUser::create([
            'network_id' => $this->networkB->network_id,
            'user_id' => $netBUser->user_id,
            'role_id' => $this->networkMemberRole->role_id,
            'status' => 'ACTIVE',
        ]);

        // إنشاء طلب كروت يتبع الشبكة A
        $request = VoucherRequest::create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'created_by' => $this->posOwner->user_id,
            'request_status' => RequestStatus::PENDING,
            'total_amount' => 5000,
        ]);

        $netBAuth = $this->createAuthenticatedSession($netBUser);
        $approveHeaders = array_merge($netBAuth['headers'], [
            'X-Idempotency-Key' => 'appr-idor-'.uniqid(),
        ]);

        // محاولة مستخدم الشبكة B اعتماد طلب الشبكة A
        $response = $this->withHeaders($approveHeaders)
            ->postJson("/api/v1/requests/{$request->request_id}/approve");

        // يجب الرفض بـ 422 أو 403 لمنع IDOR
        $this->assertContains($response->status(), [403, 422]);
        $this->assertStringContainsString('لا تملك الصلاحية', $response->json('message'));
    }

    /**
     * اختبار دورة حياة إدارة أعضاء الشبكة المقيدة (Scoped Network Memberships) وحماية المالك.
     */
    public function test_network_scoped_membership_crud_and_owner_protection(): void
    {
        $ownerAuth = $this->createAuthenticatedSession($this->networkOwner);
        $networkUserRole = Role::where('role_key', RoleKey::NETWORK_USER->value)->firstOrFail();
        $newMemberPhone = '77'.rand(1000000, 9999999);

        $newMemberUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Network Staff Member',
            'user_phone_number' => $newMemberPhone,
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // 1. جلب الأعضاء (يجب أن يشمل المالك)
        $listResponse = $this->withHeaders($ownerAuth['headers'])
            ->getJson("/api/v1/networks/{$this->networkA->network_id}/members");
        $listResponse->assertStatus(200);

        // 2. إضافة عضو جديد للشبكة
        $addResponse = $this->withHeaders($ownerAuth['headers'])
            ->postJson("/api/v1/networks/{$this->networkA->network_id}/members", [
                'user_phone_number' => $newMemberPhone,
                'role_id' => $networkUserRole->role_id,
                'status' => 'ACTIVE',
            ]);
        $addResponse->assertStatus(201);
        $this->assertEquals($newMemberUser->user_id, $addResponse->json('data.user_id'));

        // 3. تعديل حالة العضو
        $statusResponse = $this->withHeaders($ownerAuth['headers'])
            ->putJson("/api/v1/networks/{$this->networkA->network_id}/members/{$newMemberUser->user_id}/status", [
                'status' => 'SUSPENDED',
                'reason' => 'إيقاف مؤقت للاختبار',
            ]);
        $statusResponse->assertStatus(200);
        $this->assertEquals('SUSPENDED', $statusResponse->json('data.status'));

        // 4. محاولة حذف مالك الشبكة: يجب أن تفشل لحماية الملكية
        $deleteOwnerResponse = $this->withHeaders($ownerAuth['headers'])
            ->deleteJson("/api/v1/networks/{$this->networkA->network_id}/members/{$this->networkOwner->user_id}");
        $deleteOwnerResponse->assertStatus(422);
        $this->assertStringContainsString('مالك', $deleteOwnerResponse->json('message'));

        // 5. إزالة العضو الجديد بنجاح
        $deleteMemberResponse = $this->withHeaders($ownerAuth['headers'])
            ->deleteJson("/api/v1/networks/{$this->networkA->network_id}/members/{$newMemberUser->user_id}");
        $deleteMemberResponse->assertStatus(200);
    }

    /**
     * اختبار دورة حياة إدارة أعضاء نقطة البيع المقيدة (Scoped POS Memberships) وحماية المالك.
     */
    public function test_pos_scoped_membership_crud_and_owner_protection(): void
    {
        $ownerAuth = $this->createAuthenticatedSession($this->posOwner);
        $newMemberPhone = '71'.rand(1000000, 9999999);

        $newMemberUser = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'POS Staff Member',
            'user_phone_number' => $newMemberPhone,
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        // 1. جلب أعضاء نقطة البيع
        $listResponse = $this->withHeaders($ownerAuth['headers'])
            ->getJson("/api/v1/pos/{$this->posA->pos_id}/members");
        $listResponse->assertStatus(200);

        // 2. إضافة عضو جديد لنقطة البيع
        $addResponse = $this->withHeaders($ownerAuth['headers'])
            ->postJson("/api/v1/pos/{$this->posA->pos_id}/members", [
                'user_phone_number' => $newMemberPhone,
                'role_id' => $this->posMemberRole->role_id,
                'status' => 'ACTIVE',
            ]);
        $addResponse->assertStatus(201);
        $this->assertEquals($newMemberUser->user_id, $addResponse->json('data.user_id'));

        // 3. تعديل حالة العضو
        $statusResponse = $this->withHeaders($ownerAuth['headers'])
            ->putJson("/api/v1/pos/{$this->posA->pos_id}/members/{$newMemberUser->user_id}/status", [
                'status' => 'SUSPENDED',
                'reason' => 'إيقاف للاختبار',
            ]);
        $statusResponse->assertStatus(200);
        $this->assertEquals('SUSPENDED', $statusResponse->json('data.status'));

        // 4. محاولة حذف مالك نقطة البيع: يجب أن تفشل
        $deleteOwnerResponse = $this->withHeaders($ownerAuth['headers'])
            ->deleteJson("/api/v1/pos/{$this->posA->pos_id}/members/{$this->posOwner->user_id}");
        $deleteOwnerResponse->assertStatus(422);
        $this->assertStringContainsString('مالك', $deleteOwnerResponse->json('message'));

        // 5. إزالة العضو بنجاح
        $deleteMemberResponse = $this->withHeaders($ownerAuth['headers'])
            ->deleteJson("/api/v1/pos/{$this->posA->pos_id}/members/{$newMemberUser->user_id}");
        $deleteMemberResponse->assertStatus(200);
    }

    /**
     * Membership role discovery and assignment must expose only least-privilege
     * operational roles; scope membership alone must never allow privilege escalation.
     */
    public function test_membership_management_requires_exact_owner_capability_and_tenant_scope(): void
    {
        $networkUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Net Member '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        NetworkUser::create([
            'network_id' => $this->networkA->network_id,
            'user_id' => $networkUser->user_id,
            'role_id' => Role::where('role_key', RoleKey::NETWORK_USER->value)->firstOrFail()->role_id,
            'status' => 'ACTIVE',
        ]);
        $networkUserAuth = $this->createAuthenticatedSession($networkUser);

        $this->withHeaders($networkUserAuth['headers'])
            ->getJson("/api/v1/networks/{$this->networkA->network_id}/members")
            ->assertForbidden();

        $this->withHeaders($networkUserAuth['headers'])
            ->postJson("/api/v1/networks/{$this->networkA->network_id}/members", [
                'user_phone_number' => '70'.rand(1000000, 9999999),
                'role_id' => Role::where('role_key', RoleKey::NETWORK_USER->value)->firstOrFail()->role_id,
            ])
            ->assertForbidden();

        // The NETWORK_OWNER membership and network owner_id must describe the same tenant owner.
        $scopedNetworkOwner = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Net Owner '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        $this->networkA->owner_id = $scopedNetworkOwner->user_id;
        $this->networkA->save();

        NetworkUser::create([
            'network_id' => $this->networkA->network_id,
            'user_id' => $scopedNetworkOwner->user_id,
            'role_id' => Role::where('role_key', RoleKey::NETWORK_OWNER->value)->firstOrFail()->role_id,
            'status' => 'ACTIVE',
        ]);
        $scopedOwnerAuth = $this->createAuthenticatedSession($scopedNetworkOwner);

        $this->withHeaders($scopedOwnerAuth['headers'])
            ->getJson("/api/v1/networks/{$this->networkA->network_id}/members")
            ->assertOk();

        $this->withHeaders($scopedOwnerAuth['headers'])
            ->getJson("/api/v1/networks/{$this->networkB->network_id}/members")
            ->assertForbidden();

        $posUser = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'POS Member '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        PosUser::create([
            'pos_id' => $this->posA->pos_id,
            'user_id' => $posUser->user_id,
            'role_id' => Role::where('role_key', RoleKey::POS_USER->value)->firstOrFail()->role_id,
            'status' => 'ACTIVE',
        ]);
        $posUserAuth = $this->createAuthenticatedSession($posUser);

        $this->withHeaders($posUserAuth['headers'])
            ->getJson("/api/v1/pos/{$this->posA->pos_id}/members")
            ->assertForbidden();

        $this->withHeaders($posUserAuth['headers'])
            ->putJson("/api/v1/pos/{$this->posA->pos_id}/members/{$posUser->user_id}/status", [
                'status' => 'SUSPENDED',
            ])
            ->assertForbidden();
    }

    public function test_network_membership_exposes_user_and_accountant_roles_without_owner_escalation(): void
    {
        $ownerAuth = $this->createAuthenticatedSession($this->networkOwner);
        $networkUserRole = Role::where('role_key', 'NETWORK_USER')->firstOrFail();
        $networkAccountantRole = Role::where('role_key', 'NETWORK_ACCOUNTANT')->firstOrFail();
        $networkOwnerRole = Role::where('role_key', 'NETWORK_OWNER')->firstOrFail();
        $platformRole = Role::where('role_key', 'SUPER_ADMIN')->firstOrFail();

        $roles = $this->withHeaders($ownerAuth['headers'])
            ->getJson("/api/v1/networks/{$this->networkA->network_id}/members/assignable-roles")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            ['NETWORK_USER', 'NETWORK_ACCOUNTANT'],
            array_column($roles->json('data'), 'role_key')
        );

        $rejectedCandidateIds = [];
        foreach ([$networkOwnerRole, $platformRole] as $role) {
            $candidate = $this->createUserWithScope(RoleScope::NETWORK, [
                'role_id' => null,
                'user_full_name' => 'Rejected Network Staff '.uniqid(),
                'user_phone_number' => '70'.rand(1000000, 9999999),
                'user_password' => bcrypt('password'),
                'status' => UserStatus::ACTIVE,
            ]);

            $rejectedCandidateIds[] = $candidate->user_id;

            $this->withHeaders($ownerAuth['headers'])
                ->postJson("/api/v1/networks/{$this->networkA->network_id}/members", [
                    'user_phone_number' => $candidate->user_phone_number,
                    'role_id' => $role->role_id,
                ])
                ->assertUnprocessable();
        }

        foreach ($rejectedCandidateIds as $rejectedUserId) {
            $this->assertDatabaseMissing('network_users', [
                'network_id' => $this->networkA->network_id,
                'user_id' => $rejectedUserId,
            ]);
        }

        $accountantCandidate = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Network Accountant Candidate '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertFalse($accountantCandidate->hasPermission('expenses.create'));

        $this->withHeaders($ownerAuth['headers'])
            ->postJson("/api/v1/networks/{$this->networkA->network_id}/members", [
                'user_phone_number' => $accountantCandidate->user_phone_number,
                'role_id' => $networkAccountantRole->role_id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('network_users', [
            'network_id' => $this->networkA->network_id,
            'user_id' => $accountantCandidate->user_id,
            'role_id' => $networkAccountantRole->role_id,
        ]);
        $effectiveNetworkAccountant = $accountantCandidate->fresh();
        $this->assertTrue($effectiveNetworkAccountant->hasPermission('expenses.create'));
        $this->assertTrue($effectiveNetworkAccountant->hasPermission('sales.report'));
        $this->assertFalse($effectiveNetworkAccountant->hasPermission('network_members.change_role'));
        $this->assertFalse($effectiveNetworkAccountant->hasPermission('network_wallets.deposit'));

        $changeCandidate = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Network Role Change Candidate '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->withHeaders($ownerAuth['headers'])
            ->postJson("/api/v1/networks/{$this->networkA->network_id}/members", [
                'user_phone_number' => $changeCandidate->user_phone_number,
                'role_id' => $networkUserRole->role_id,
            ])
            ->assertCreated();

        $this->withHeaders($ownerAuth['headers'])
            ->putJson("/api/v1/networks/{$this->networkA->network_id}/members/{$changeCandidate->user_id}/role", [
                'role_id' => $networkAccountantRole->role_id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('network_users', [
            'network_id' => $this->networkA->network_id,
            'user_id' => $changeCandidate->user_id,
            'role_id' => $networkAccountantRole->role_id,
        ]);
        $this->assertEquals(
            [1, 2, 3],
            array_map(
                'intval',
                \Illuminate\Support\Facades\DB::table('network_membership_audit_logs')
                    ->where('network_id', $this->networkA->network_id)
                    ->orderBy('event_seq')
                    ->pluck('event_seq')
                    ->all()
            )
        );

        $this->withHeaders($ownerAuth['headers'])
            ->putJson("/api/v1/networks/{$this->networkA->network_id}/members/{$changeCandidate->user_id}/role", [
                'role_id' => $networkOwnerRole->role_id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['role_id' => $networkUserRole->role_id]);
    }

    public function test_pos_membership_exposes_user_and_accountant_roles_without_agent_escalation(): void
    {
        $ownerAuth = $this->createAuthenticatedSession($this->posOwner);
        $posUserRole = Role::where('role_key', 'POS_USER')->firstOrFail();
        $posAccountantRole = Role::where('role_key', 'POS_ACCOUNTANT')->firstOrFail();
        $posAgentRole = Role::where('role_key', 'POS_AGENT')->firstOrFail();
        $platformRole = Role::where('role_key', 'SUPER_ADMIN')->firstOrFail();

        $roles = $this->withHeaders($ownerAuth['headers'])
            ->getJson("/api/v1/pos/{$this->posA->pos_id}/members/assignable-roles")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            ['POS_USER', 'POS_ACCOUNTANT'],
            array_column($roles->json('data'), 'role_key')
        );

        $rejectedCandidateIds = [];
        foreach ([$posAgentRole, $platformRole] as $role) {
            $candidate = $this->createUserWithScope(RoleScope::POS, [
                'role_id' => null,
                'user_full_name' => 'Rejected POS Staff '.uniqid(),
                'user_phone_number' => '70'.rand(1000000, 9999999),
                'user_password' => bcrypt('password'),
                'status' => UserStatus::ACTIVE,
            ]);

            $rejectedCandidateIds[] = $candidate->user_id;

            $this->withHeaders($ownerAuth['headers'])
                ->postJson("/api/v1/pos/{$this->posA->pos_id}/members", [
                    'user_phone_number' => $candidate->user_phone_number,
                    'role_id' => $role->role_id,
                ])
                ->assertUnprocessable();
        }

        foreach ($rejectedCandidateIds as $rejectedUserId) {
            $this->assertDatabaseMissing('pos_users', [
                'pos_id' => $this->posA->pos_id,
                'user_id' => $rejectedUserId,
            ]);
        }

        $accountantCandidate = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'POS Accountant Candidate '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->assertFalse($accountantCandidate->hasPermission('sales.report'));

        $this->withHeaders($ownerAuth['headers'])
            ->postJson("/api/v1/pos/{$this->posA->pos_id}/members", [
                'user_phone_number' => $accountantCandidate->user_phone_number,
                'role_id' => $posAccountantRole->role_id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('pos_users', [
            'pos_id' => $this->posA->pos_id,
            'user_id' => $accountantCandidate->user_id,
            'role_id' => $posAccountantRole->role_id,
        ]);
        $effectivePosAccountant = $accountantCandidate->fresh();
        $this->assertTrue($effectivePosAccountant->hasPermission('sales.report'));
        $this->assertTrue($effectivePosAccountant->hasPermission('pos_wallets.report'));
        $this->assertFalse($effectivePosAccountant->hasPermission('pos_members.change_role'));
        $this->assertFalse($effectivePosAccountant->hasPermission('sales.create'));

        $changeCandidate = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'user_full_name' => 'POS Role Change Candidate '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->withHeaders($ownerAuth['headers'])
            ->postJson("/api/v1/pos/{$this->posA->pos_id}/members", [
                'user_phone_number' => $changeCandidate->user_phone_number,
                'role_id' => $posUserRole->role_id,
            ])
            ->assertCreated();

        $this->withHeaders($ownerAuth['headers'])
            ->putJson("/api/v1/pos/{$this->posA->pos_id}/members/{$changeCandidate->user_id}/role", [
                'role_id' => $posAccountantRole->role_id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('pos_users', [
            'pos_id' => $this->posA->pos_id,
            'user_id' => $changeCandidate->user_id,
            'role_id' => $posAccountantRole->role_id,
        ]);
        $this->assertEquals(
            [1, 2, 3],
            array_map(
                'intval',
                \Illuminate\Support\Facades\DB::table('pos_membership_audit_logs')
                    ->where('pos_id', $this->posA->pos_id)
                    ->orderBy('event_seq')
                    ->pluck('event_seq')
                    ->all()
            )
        );

        $this->withHeaders($ownerAuth['headers'])
            ->putJson("/api/v1/pos/{$this->posA->pos_id}/members/{$changeCandidate->user_id}/role", [
                'role_id' => $posAgentRole->role_id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['role_id' => $posUserRole->role_id]);
    }

    public function test_permissions_are_isolated_per_active_network_membership(): void
    {
        $multiTenantUser = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Multi Tenant Permission User '.uniqid(),
            'user_phone_number' => '70'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $ownerRole = Role::where('role_key', RoleKey::NETWORK_OWNER->value)->firstOrFail();

        NetworkUser::create([
            'network_id' => $this->networkA->network_id,
            'user_id' => $multiTenantUser->user_id,
            'role_id' => $ownerRole->role_id,
            'status' => 'ACTIVE',
        ]);

        NetworkUser::create([
            'network_id' => $this->networkB->network_id,
            'user_id' => $multiTenantUser->user_id,
            'role_id' => $this->networkMemberRole->role_id,
            'status' => 'ACTIVE',
        ]);

        $authorization = app(\App\Services\TenantPermissionService::class);

        $this->assertTrue(
            $authorization->allowsNetwork(
                $multiTenantUser,
                (int) $this->networkA->network_id,
                'requests.approve'
            )
        );

        $this->assertFalse(
            $authorization->allowsNetwork(
                $multiTenantUser,
                (int) $this->networkB->network_id,
                'requests.approve'
            )
        );

        NetworkUser::query()
            ->where('network_id', $this->networkA->network_id)
            ->where('user_id', $multiTenantUser->user_id)
            ->update(['status' => 'SUSPENDED']);

        $this->assertFalse(
            $authorization->allowsNetwork(
                $multiTenantUser->fresh(),
                (int) $this->networkA->network_id,
                'requests.approve'
            )
        );
    }

    public function test_membership_form_requests_reject_invalid_inputs_for_both_tenant_scopes(): void
    {
        $networkAuth = $this->createAuthenticatedSession($this->networkOwner);
        $networkBase = "/api/v1/networks/{$this->networkA->network_id}/members";

        $this->withHeaders($networkAuth['headers'])
            ->postJson($networkBase, [
                'user_phone_number' => '',
                'role_id' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_phone_number', 'role_id']);

        $this->withHeaders($networkAuth['headers'])
            ->putJson("{$networkBase}/{$this->posOwner->user_id}/role", ['role_id' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        $this->withHeaders($networkAuth['headers'])
            ->putJson("{$networkBase}/{$this->posOwner->user_id}/status", ['status' => 'UNKNOWN'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withHeaders($networkAuth['headers'])
            ->deleteJson("{$networkBase}/{$this->posOwner->user_id}", ['reason' => str_repeat('x', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $posAuth = $this->createAuthenticatedSession($this->posOwner);
        $posBase = "/api/v1/pos/{$this->posA->pos_id}/members";

        $this->withHeaders($posAuth['headers'])
            ->postJson($posBase, [
                'user_phone_number' => '',
                'role_id' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_phone_number', 'role_id']);

        $this->withHeaders($posAuth['headers'])
            ->putJson("{$posBase}/{$this->networkOwner->user_id}/role", ['role_id' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id']);

        $this->withHeaders($posAuth['headers'])
            ->putJson("{$posBase}/{$this->networkOwner->user_id}/status", ['status' => 'UNKNOWN'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->withHeaders($posAuth['headers'])
            ->deleteJson("{$posBase}/{$this->networkOwner->user_id}", ['reason' => str_repeat('x', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_membership_lists_support_cursor_pagination_and_server_side_filters_for_both_scopes(): void
    {
        for ($index = 1; $index <= 24; $index++) {
            $networkMember = $this->createUserWithScope(RoleScope::NETWORK, [
                'role_id' => null,
                'user_full_name' => 'Network Cursor Member '.$index,
                'user_phone_number' => '77'.str_pad((string) (8000000 + $index), 7, '0', STR_PAD_LEFT),
                'user_password' => bcrypt('password'),
                'status' => UserStatus::ACTIVE,
            ]);
            NetworkUser::create([
                'network_id' => $this->networkA->network_id,
                'user_id' => $networkMember->user_id,
                'role_id' => $this->networkMemberRole->role_id,
                'status' => $index === 7 ? 'SUSPENDED' : 'ACTIVE',
            ]);

            $posMember = $this->createUserWithScope(RoleScope::POS, [
                'role_id' => null,
                'user_full_name' => 'POS Cursor Member '.$index,
                'user_phone_number' => '71'.str_pad((string) (8000000 + $index), 7, '0', STR_PAD_LEFT),
                'user_password' => bcrypt('password'),
                'status' => UserStatus::ACTIVE,
            ]);
            PosUser::create([
                'pos_id' => $this->posA->pos_id,
                'user_id' => $posMember->user_id,
                'role_id' => $this->posMemberRole->role_id,
                'status' => $index === 7 ? 'SUSPENDED' : 'ACTIVE',
            ]);
        }

        $networkAuth = $this->createAuthenticatedSession($this->networkOwner);
        $networkUrl = "/api/v1/networks/{$this->networkA->network_id}/members";
        $networkFirst = $this->withHeaders($networkAuth['headers'])
            ->getJson($networkUrl.'?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.summary.total', 25)
            ->assertJsonPath('meta.summary.active', 24)
            ->assertJsonPath('meta.summary.suspended', 1)
            ->assertJsonPath('meta.summary.invited', 0);
        $networkCursor = $networkFirst->json('next_cursor');
        $this->assertNotEmpty($networkCursor);

        $networkSecond = $this->withHeaders($networkAuth['headers'])
            ->getJson($networkUrl.'?per_page=10&cursor='.urlencode($networkCursor))
            ->assertOk()
            ->assertJsonCount(10, 'data');
        $networkIds = array_merge(
            array_column($networkFirst->json('data'), 'id'),
            array_column($networkSecond->json('data'), 'id')
        );
        $this->assertCount(20, array_unique($networkIds));

        $this->withHeaders($networkAuth['headers'])
            ->getJson($networkUrl.'?search='.urlencode('Network Cursor Member 7').'&status=SUSPENDED')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'SUSPENDED');

        $posAuth = $this->createAuthenticatedSession($this->posOwner);
        $posUrl = "/api/v1/pos/{$this->posA->pos_id}/members";
        $posFirst = $this->withHeaders($posAuth['headers'])
            ->getJson($posUrl.'?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.summary.total', 25)
            ->assertJsonPath('meta.summary.active', 24)
            ->assertJsonPath('meta.summary.suspended', 1)
            ->assertJsonPath('meta.summary.invited', 0);
        $posCursor = $posFirst->json('next_cursor');
        $this->assertNotEmpty($posCursor);

        $posSecond = $this->withHeaders($posAuth['headers'])
            ->getJson($posUrl.'?per_page=10&cursor='.urlencode($posCursor))
            ->assertOk()
            ->assertJsonCount(10, 'data');
        $posIds = array_merge(
            array_column($posFirst->json('data'), 'id'),
            array_column($posSecond->json('data'), 'id')
        );
        $this->assertCount(20, array_unique($posIds));

        $this->withHeaders($posAuth['headers'])
            ->getJson($posUrl.'?search='.urlencode('POS Cursor Member 7').'&status=SUSPENDED')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'SUSPENDED');
    }

    public function test_force_delete_never_deletes_a_live_request(): void
    {
        $request = VoucherRequest::query()->create([
            'network_id' => $this->networkA->network_id,
            'pos_id' => $this->posA->pos_id,
            'sale_type' => SaleType::CASH,
            'request_total_amount' => 100,
            'request_date' => now('Asia/Aden'),
            'status' => RequestStatus::PENDING,
            'created_by' => $this->posOwner->user_id,
        ]);

        try {
            app(RequestService::class)->forceDeleteRequest(
                (int) $request->request_id,
                (int) $this->posOwner->user_id
            );
            $this->fail('A live request must never be force-deleted.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('requests', [
                'request_id' => $request->request_id,
                'deleted_at' => null,
            ]);
        }

        try {
            app(RequestService::class)->bulkForceDelete(
                [(int) $request->request_id],
                (int) $this->posOwner->user_id
            );
            $this->fail('A live request must never be included in bulk force-delete.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('requests', [
                'request_id' => $request->request_id,
                'deleted_at' => null,
            ]);
        }
    }


}
