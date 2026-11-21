<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleScope;
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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesActiveBillingSubscription;
use Tests\TestCase;

class VoucherPackageLifecycleDatabaseTest extends TestCase
{
    use CreatesActiveBillingSubscription;
    use DatabaseTransactions;

    protected User $networkOwner;

    protected User $posOwner;

    protected array $headersNet = [];

    protected array $headersPos = [];

    protected Network $network;

    protected Pos $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $gov = Governorate::first() ?? Governorate::create(['governorate_name' => 'Sanaa']);
        $dir = Directorate::where('governorate_id', $gov->governorate_id)->first()
            ?? Directorate::create(['governorate_id' => $gov->governorate_id, 'directorate_name' => 'Main']);

        $suffix = uniqid();

        // 1. Create Users
        [$this->networkOwner, $this->headersNet] = $this->createUserWithSession('owner_'.$suffix, RoleScope::NETWORK);
        [$this->posOwner, $this->headersPos] = $this->createUserWithSession('pos_'.$suffix, RoleScope::POS);

        // 2. Create Network
        $this->network = Network::create([
            'owner_id' => $this->networkOwner->user_id,
            'network_name' => 'شبكة الاختبار الشامل '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $netOwnerRoleId = Role::where('role_key', 'NETWORK_OWNER')->value('role_id');
        NetworkUser::create([
            'network_id' => $this->network->network_id,
            'user_id' => $this->networkOwner->user_id,
            'role_id' => $netOwnerRoleId,
            'status' => 'ACTIVE',
        ]);

        // 3. Create POS
        $this->pos = Pos::create([
            'owner_id' => $this->posOwner->user_id,
            'pos_name' => 'نقطة بيع الاختبار '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => $gov->governorate_id,
            'directorate_id' => $dir->directorate_id,
            'status' => PosStatus::ACTIVE,
        ]);

        $posOwnerRoleId = Role::where('role_key', 'POS_AGENT')->value('role_id');
        PosUser::create([
            'pos_id' => $this->pos->pos_id,
            'user_id' => $this->posOwner->user_id,
            'role_id' => $posOwnerRoleId,
            'status' => 'ACTIVE',
        ]);

        // Link POS to Network
        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        // Provision active subscription so the network has quota to create voucher packages
        $this->createActiveBillingSubscriptionForNetwork($this->network);
    }

    protected function createUserWithSession(string $username, RoleScope $scope): array
    {
        $user = $this->createUserWithScope($scope, [
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
            'app_version' => '1.0.0',
            'os_version' => 'Android 14',
            'is_trusted' => true,
            'status' => 'ACTIVE',
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
    public function full_lifecycle_scenario_1_create_package_with_fractional_specifications_stored_in_base_units()
    {
        // سيناريو 1: المستخدم يُنشئ باقة "1.5GB" بسعر 1000، خصم نقدي 10%، آجل 5%
        // التحويل: 1.5 GB = 1,610,612,736 بايت، 1.5 ساعة = 90 دقيقة، 45 يوم (شهر ونصف) = 64,800 دقيقة
        $payload = [
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة السرعة 1.5GB',
            'package_color' => '0xFF0D7A5F',
            'package_balance' => 1610612736,
            'package_uptime' => 90,
            'package_validity' => 64800,
            'package_voucher_count' => 51,
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.0,
            'package_default_credit_discount' => 5.0,
            'status' => true,
        ];

        $response = $this->withHeaders($this->headersNet)
            ->postJson('/api/v1/voucher-packages', $payload);

        $response->assertStatus(201);
        $packageId = $response->json('data.package_id');
        $this->assertNotNull($packageId);

        // التحقق المباشر من قاعدة البيانات:
        // 1. القيمة مخزنة كعدد صحيح بالبايت في العمود
        $this->assertDatabaseHas('voucher_packages', [
            'package_id' => $packageId,
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة السرعة 1.5GB',
            'package_balance' => 1610612736,
            'package_uptime' => 90,
            'package_validity' => 64800,
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.0,
            'package_default_credit_discount' => 5.0,
            'status' => 1,
        ]);

        // 2. التحقق من حسابات الأسعار السيادية في استجابة الـ Resource
        $data = $response->json('data');
        $this->assertEquals(1610612736, $data['package_balance']);
        $this->assertEquals(90, $data['package_uptime']);
        $this->assertEquals(64800, $data['package_validity']);
        $this->assertEquals(900.00, $data['effective_cash_price']);
        $this->assertEquals(950.00, $data['effective_credit_price']);
        $this->assertEquals(100.00, $data['cash_discount_amount']);
        $this->assertEquals(50.00, $data['credit_discount_amount']);
        $this->assertTrue($data['status']);
    }

    #[Test]
    public function full_lifecycle_scenario_2_terabyte_scale_package_inserts_and_queries_without_overflow()
    {
        // سيناريو 2: المستخدم يُنشئ باقة شركات بسعة 2.5 تيرابايت = 2,748,779,069,440 بايت
        // هذه القيمة تتجاوز 2.14 مليار بايت (حد الـ 32-bit signed integer) وتثبت سلامة استخدام unsignedBigInteger
        $payload = [
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة المؤسسات الكبرى 2.5TB',
            'package_balance' => 2748779069440,
            'package_uptime' => 525600, // سنة كاملة
            'package_validity' => 525600,
            'package_voucher_count' => 500,
            'package_voucher_price' => 50000.00,
            'package_default_cash_discount' => 20.0,
            'package_default_credit_discount' => 10.0,
            'status' => true,
        ];

        $response = $this->withHeaders($this->headersNet)
            ->postJson('/api/v1/voucher-packages', $payload);

        $response->assertStatus(201);
        $packageId = $response->json('data.package_id');

        // استعلام مباشر عبر الموديل من قاعدة البيانات
        $saved = VoucherPackage::findOrFail($packageId);
        $this->assertEquals(2748779069440, $saved->package_balance);
        $this->assertIsInt($saved->package_balance);
    }

    #[Test]
    public function full_lifecycle_scenario_3_unmetered_and_unlimited_package_with_zero_or_null_specifications()
    {
        // سيناريو 3: باقة مفتوحة الرصيد والوقت (بلا قيود)
        $payload = [
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة التجوال المفتوح',
            'package_balance' => 0,
            'package_uptime' => null,
            'package_validity' => null,
            'package_voucher_count' => 10,
            'package_voucher_price' => 5000.00,
            'package_default_cash_discount' => 5.0,
            'package_default_credit_discount' => 0.0,
            'status' => true,
        ];

        $response = $this->withHeaders($this->headersNet)
            ->postJson('/api/v1/voucher-packages', $payload);

        $response->assertStatus(201);
        $packageId = $response->json('data.package_id');

        $saved = VoucherPackage::findOrFail($packageId);
        $this->assertEquals(0, $saved->package_balance);
        $this->assertNull($saved->package_uptime);
        $this->assertNull($saved->package_validity);
    }

    #[Test]
    public function full_lifecycle_scenario_4_update_package_specifications_prices_and_recalculated_values()
    {
        // سيناريو 4: تعديل مواصفات الباقة وأسعارها وإعادة الحساب الآلي
        $package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->networkOwner->user_id,
            'package_name' => 'باقة التوفير 1GB',
            'package_balance' => 1073741824, // 1GB
            'package_uptime' => 60, // ساعة
            'package_validity' => 1440, // يوم
            'package_voucher_count' => 100,
            'package_voucher_price' => 500.00,
            'package_default_cash_discount' => 5.0,
            'package_default_credit_discount' => 2.0,
            'status' => true,
        ]);

        // ترقية الباقة إلى 3GB (3,221,225,472 بايت) وتحديث الأسعار
        $updatePayload = [
            'package_name' => 'باقة التوفير المطورة 3GB',
            'package_balance' => 3221225472,
            'package_uptime' => 180, // 3 ساعات
            'package_validity' => 4320, // 3 أيام
            'package_voucher_price' => 1200.00,
            'package_default_cash_discount' => 15.0,
            'package_default_credit_discount' => 8.0,
        ];

        $response = $this->withHeaders($this->headersNet)
            ->putJson("/api/v1/voucher-packages/{$package->package_id}", $updatePayload);

        $response->assertOk();

        // التحقق من قاعدة البيانات
        $this->assertDatabaseHas('voucher_packages', [
            'package_id' => $package->package_id,
            'package_name' => 'باقة التوفير المطورة 3GB',
            'package_balance' => 3221225472,
            'package_uptime' => 180,
            'package_validity' => 4320,
            'package_voucher_price' => 1200.00,
            'package_default_cash_discount' => 15.0,
            'package_default_credit_discount' => 8.0,
        ]);

        // التحقق من الاستجابة وحسابات الأسعار الفعلية (1200 - 15% = 1020، 1200 - 8% = 1104)
        $data = $response->json('data');
        $this->assertEquals(1020.00, $data['effective_cash_price']);
        $this->assertEquals(1104.00, $data['effective_credit_price']);
        $this->assertEquals(180.00, $data['cash_discount_amount']);
        $this->assertEquals(96.00, $data['credit_discount_amount']);
    }

    #[Test]
    public function full_lifecycle_scenario_5_toggle_status_reflects_in_database_and_affects_pos_visibility()
    {
        // سيناريو 5: تفعيل وتعطيل الباقة والتحقق من انعكاس ذلك فوراً في قاعدة البيانات ورؤية نقاط البيع
        $package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->networkOwner->user_id,
            'package_name' => 'باقة تحت التجربة',
            'package_voucher_price' => 800.00,
            'status' => true,
        ]);

        // 1. التحقق أن نقطة البيع تستطيع رؤية الباقة النشطة
        $posList = $this->withHeaders($this->headersPos)
            ->getJson("/api/v1/networks/{$this->network->network_id}/voucher-packages");
        $posList->assertOk();
        $ids = collect($posList->json('data'))->pluck('package_id');
        $this->assertTrue($ids->contains($package->package_id));

        // 2. صاحب الشبكة يقوم بتعطيل الباقة (Toggle Status -> Inactive)
        $toggleRes = $this->withHeaders($this->headersNet)
            ->postJson("/api/v1/voucher-packages/{$package->package_id}/toggle-status");
        $toggleRes->assertOk();
        $this->assertFalse($toggleRes->json('data.status'));

        // التحقق من قاعدة البيانات مباشرة
        $this->assertDatabaseHas('voucher_packages', [
            'package_id' => $package->package_id,
            'status' => 0,
        ]);

        // 3. التحقق أن نقطة البيع لم تعد قادرة على رؤية الباقة المعطلة إطلاقاً
        $posListAfter = $this->withHeaders($this->headersPos)
            ->getJson("/api/v1/networks/{$this->network->network_id}/voucher-packages");
        $posListAfter->assertOk();
        $idsAfter = collect($posListAfter->json('data'))->pluck('package_id');
        $this->assertFalse($idsAfter->contains($package->package_id));

        // 4. صاحب الشبكة يعيد تفعيل الباقة (Toggle Status -> Active)
        $toggleAgain = $this->withHeaders($this->headersNet)
            ->postJson("/api/v1/voucher-packages/{$package->package_id}/toggle-status");
        $toggleAgain->assertOk();
        $this->assertTrue($toggleAgain->json('data.status'));

        $this->assertDatabaseHas('voucher_packages', [
            'package_id' => $package->package_id,
            'status' => 1,
        ]);
    }

    #[Test]
    public function full_lifecycle_scenario_6_soft_delete_and_restore_cycle()
    {
        // سيناريو 6: الحذف المؤقت والاسترجاع من سلة المحذوفات
        $package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->networkOwner->user_id,
            'package_name' => 'باقة قابلة للحذف',
            'package_voucher_price' => 700.00,
            'status' => true,
        ]);

        // 1. حذف الباقة
        $delRes = $this->withHeaders($this->headersNet)
            ->deleteJson("/api/v1/voucher-packages/{$package->package_id}");
        $delRes->assertOk();

        // التحقق من قاعدة البيانات أنها حذفت ناعماً (Soft Deleted)
        $this->assertSoftDeleted('voucher_packages', [
            'package_id' => $package->package_id,
        ]);

        // التحقق أنها استُثنيت من قائمة الباقات العادية
        $listRes = $this->withHeaders($this->headersNet)
            ->getJson('/api/v1/voucher-packages');
        $listRes->assertOk();
        $activeIds = collect($listRes->json('data'))->pluck('package_id');
        $this->assertFalse($activeIds->contains($package->package_id));

        // 2. استرجاع الباقة من سلة المحذوفات
        $restoreRes = $this->withHeaders($this->headersNet)
            ->postJson("/api/v1/voucher-packages/{$package->package_id}/restore");
        $restoreRes->assertOk();

        // التحقق من قاعدة البيانات أن deleted_at أصبح null
        $this->assertDatabaseHas('voucher_packages', [
            'package_id' => $package->package_id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function full_lifecycle_scenario_7_server_strictly_enforces_financial_calculations_ignoring_client_injection()
    {
        // سيناريو 7: محاولة حقن أسعار أو خصومات زائفة من العميل
        // العميل يحاول إرسال سعر وهمي effective_cash_price = 10 ر.ي بينما السعر الأساسي 1000 والخصم 10%
        $maliciousPayload = [
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة تجربة أمنية',
            'package_balance' => 1024,
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.0,
            'package_default_credit_discount' => 5.0,
            'effective_cash_price' => 10.00, // محاولة تلاعب
            'effective_credit_price' => 20.00, // محاولة تلاعب
            'cash_discount_amount' => 990.00, // محاولة تلاعب
        ];

        $response = $this->withHeaders($this->headersNet)
            ->postJson('/api/v1/voucher-packages', $maliciousPayload);

        $response->assertStatus(201);

        // الخادم يفرض حسابه السيادي ويتجاهل القيم المحقونة تماماً
        $data = $response->json('data');
        $this->assertEquals(900.00, $data['effective_cash_price']);
        $this->assertEquals(950.00, $data['effective_credit_price']);
        $this->assertEquals(100.00, $data['cash_discount_amount']);
        $this->assertEquals(50.00, $data['credit_discount_amount']);
    }
}
