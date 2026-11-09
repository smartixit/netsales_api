<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\RoleKey;
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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OperationalSecurityAndScopedMembershipTest extends TestCase
{
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
        $this->networkMemberRole = Role::where('role_key', 'NETWORK_USER')->firstOrFail();
        $this->posMemberRole = Role::where('role_key', 'POS_USER')->firstOrFail();

        // مستخدمي المستأجرين role_id = null لأن أدوارهم مقيدة بـ network_users و pos_users
        $this->networkOwner = User::create([
            'role_id' => null,
            'user_full_name' => 'Net Owner '.$suffix,
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->posOwner = User::create([
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
        $this->assertStringContainsString('SUPER_ADMIN', $response->json('message'));
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
        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/requests', [
                'network_id' => $this->networkA->network_id,
                'pos_id' => $this->posA->pos_id,
                'items' => [
                    [
                        'package_id' => $this->packageA->package_id,
                        'voucher_count' => 5,
                    ],
                ],
            ]);

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
        $first = $this->withHeaders($headers)->postJson('/api/v1/requests', $payload);
        $first->assertStatus(201);
        $firstRequestId = $first->json('data.request_id');

        // الطلب الثاني المتطابق تماماً بنفس المفتاح
        $second = $this->withHeaders($headers)->postJson('/api/v1/requests', $payload);
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
        $netBUser = User::create([
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
        $this->assertStringContainsString('غير مصرح', $response->json('message'));
    }

    /**
     * اختبار دورة حياة إدارة أعضاء الشبكة المقيدة (Scoped Network Memberships) وحماية المالك.
     */
    public function test_network_scoped_membership_crud_and_owner_protection(): void
    {
        $ownerAuth = $this->createAuthenticatedSession($this->networkOwner);
        $newMemberPhone = '77'.rand(1000000, 9999999);

        $newMemberUser = User::create([
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
                'role_id' => $this->networkMemberRole->role_id,
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

        $newMemberUser = User::create([
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
    public function test_network_membership_only_exposes_and_accepts_network_user_role(): void
    {
        $ownerAuth = $this->createAuthenticatedSession($this->networkOwner);
        $networkUserRole = \App\Models\Role::where('role_key', 'NETWORK_USER')->firstOrFail();
        $networkAccountantRole = \App\Models\Role::where('role_key', 'NETWORK_ACCOUNTANT')->firstOrFail();
        $networkOwnerRole = \App\Models\Role::where('role_key', 'NETWORK_OWNER')->firstOrFail();
        $platformRole = \App\Models\Role::where('role_key', 'SUPER_ADMIN')->firstOrFail();

        $roles = $this->withHeaders($ownerAuth['headers'])
            ->getJson("/api/v1/networks/{$this->networkA->network_id}/members/assignable-roles")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role_key', 'NETWORK_USER');

        $this->assertSame('NETWORK_USER', $roles->json('data.0.role_key'));

        foreach ([$networkAccountantRole, $networkOwnerRole, $platformRole] as $role) {
            $candidate = User::create([
                'role_id' => null,
                'user_full_name' => 'Rejected Network Staff '.uniqid(),
                'user_phone_number' => '70'.rand(1000000, 9999999),
                'user_password' => bcrypt('password'),
                'status' => UserStatus::ACTIVE,
            ]);

            $response = $this->withHeaders($ownerAuth['headers'])
                ->postJson("/api/v1/networks/{$this->networkA->network_id}/members", [
                    'user_phone_number' => $candidate->user_phone_number,
                    'role_id' => $role->role_id,
                ]);

            $response->assertUnprocessable();
        }

        $this->assertDatabaseMissing('network_users', [
            'network_id' => $this->networkA->network_id,
            'role_id' => $networkAccountantRole->role_id,
        ]);

        $this->assertDatabaseHas('roles', ['role_id' => $networkUserRole->role_id]);
    }

    public function test_pos_membership_only_exposes_and_accepts_pos_user_role(): void
    {
        $ownerAuth = $this->createAuthenticatedSession($this->posOwner);
        $posUserRole = \App\Models\Role::where('role_key', 'POS_USER')->firstOrFail();
        $posAccountantRole = \App\Models\Role::where('role_key', 'POS_ACCOUNTANT')->firstOrFail();
        $posAgentRole = \App\Models\Role::where('role_key', 'POS_AGENT')->firstOrFail();
        $platformRole = \App\Models\Role::where('role_key', 'SUPER_ADMIN')->firstOrFail();

        $this->withHeaders($ownerAuth['headers'])
            ->getJson("/api/v1/pos/{$this->posA->pos_id}/members/assignable-roles")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role_key', 'POS_USER');

        foreach ([$posAccountantRole, $posAgentRole, $platformRole] as $role) {
            $candidate = User::create([
                'role_id' => null,
                'user_full_name' => 'Rejected POS Staff '.uniqid(),
                'user_phone_number' => '70'.rand(1000000, 9999999),
                'user_password' => bcrypt('password'),
                'status' => UserStatus::ACTIVE,
            ]);

            $response = $this->withHeaders($ownerAuth['headers'])
                ->postJson("/api/v1/pos/{$this->posA->pos_id}/members", [
                    'user_phone_number' => $candidate->user_phone_number,
                    'role_id' => $role->role_id,
                ]);

            $response->assertUnprocessable();
        }

        $this->assertDatabaseMissing('pos_users', [
            'pos_id' => $this->posA->pos_id,
            'role_id' => $posAccountantRole->role_id,
        ]);

        $this->assertDatabaseHas('roles', ['role_id' => $posUserRole->role_id]);
    }

}
