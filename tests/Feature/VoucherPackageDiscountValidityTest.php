<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\SaleType;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\Request as RequestModel;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Models\VoucherPackageDiscount;
use App\Services\RequestPricingReviewService;
use App\Services\VoucherPackageDiscountService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoucherPackageDiscountValidityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected array $headers;

    protected Network $network;

    protected Pos $pos;

    protected VoucherPackage $package;

    protected VoucherPackageDiscountService $discountService;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();
        $this->user = User::firstOrFail();

        $devUuid = 'DEV-'.$suffix;
        UserDevice::firstOrCreate([
            'user_id' => $this->user->user_id,
            'device_uuid' => $devUuid,
        ], [
            'device_name' => 'Test Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => $devUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Device-Uuid' => $devUuid,
            'X-Bypass-Encryption' => 'true',
        ];

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'شبكة التخفيضات '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'نقطة الأفق '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
        ]);

        NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);

        PosWallet::create([
            'pos_id' => $this->pos->pos_id,
            'network_id' => $this->network->network_id,
            'wallet_balance' => 100000.00,
            'status' => true,
        ]);

        // الباقة الافتراضية: كاش 10%، آجل 5%
        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة التوفير 1000',
            'package_voucher_price' => 1000.00,
            'package_default_cash_discount' => 10.00,
            'package_default_credit_discount' => 5.00,
            'package_voucher_count' => 500,
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        $this->discountService = app(VoucherPackageDiscountService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Case 1: Valid custom discount overrides a higher default discount (Priority Rule).
     */
    public function test_valid_custom_discount_overrides_higher_default_discount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        // Default is 10%, Custom is 7% (lower than default, but MUST win)
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 7.00,
            'discount_credit' => 3.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-10 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertTrue($effective['is_custom']);
        $this->assertEquals(7.00, $effective['discount_cash']);
        $this->assertEquals(3.00, $effective['discount_credit']);
        $this->assertEquals(930.00, $effective['effective_cash_price']);
    }

    /**
     * Case 2: Valid custom discount overrides a lower default discount.
     */
    public function test_valid_custom_discount_overrides_lower_default_discount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        // Default is 10%, Custom is 18% (higher than default, MUST win)
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 18.00,
            'discount_credit' => 12.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-10 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertTrue($effective['is_custom']);
        $this->assertEquals(18.00, $effective['discount_cash']);
        $this->assertEquals(12.00, $effective['discount_credit']);
    }

    /**
     * Case 3: Manually disabled custom discount falls back to package default.
     */
    public function test_disabled_custom_discount_falls_back_to_package_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 20.00,
            'discount_credit' => 15.00,
            'status' => false, // disabled
            'discount_start_date' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-10 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertFalse($effective['is_custom']);
        $this->assertEquals(10.00, $effective['discount_cash']); // package default
        $this->assertEquals(5.00, $effective['discount_credit']); // package default
    }

    /**
     * Case 4: Future custom discount falls back to package default.
     */
    public function test_future_custom_discount_falls_back_to_package_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        // Starts tomorrow
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 25.00,
            'discount_credit' => 20.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-11-03 00:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-10 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertFalse($effective['is_custom']);
        $this->assertEquals(10.00, $effective['discount_cash']);
    }

    /**
     * Case 5: Expired custom discount falls back to package default.
     */
    public function test_expired_custom_discount_falls_back_to_package_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        // Expired yesterday
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 30.00,
            'discount_credit' => 25.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-10-01 00:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertFalse($effective['is_custom']);
        $this->assertEquals(10.00, $effective['discount_cash']);
    }

    /**
     * Case 6: Soft-deleted custom discount falls back to package default.
     */
    public function test_soft_deleted_custom_discount_falls_back_to_package_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        $discount = VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 40.00,
            'discount_credit' => 35.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-10 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $discount->delete(); // Soft delete

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertFalse($effective['is_custom']);
        $this->assertEquals(10.00, $effective['discount_cash']);
    }

    /**
     * Case 7: Null start date is treated as having no lower time boundary.
     */
    public function test_null_start_date_has_no_lower_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 15.00,
            'discount_credit' => 10.00,
            'status' => true,
            'discount_start_date' => null, // No lower bound
            'discount_end_date' => Carbon::parse('2026-11-10 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertTrue($effective['is_custom']);
        $this->assertEquals(15.00, $effective['discount_cash']);
    }

    /**
     * Case 8: Null end date does not expire.
     */
    public function test_null_end_date_does_not_expire(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 14.00,
            'discount_credit' => 9.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Aden'),
            'discount_end_date' => null, // Open-ended
            'created_by' => $this->user->user_id,
        ]);

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertTrue($effective['is_custom']);
        $this->assertEquals(14.00, $effective['discount_cash']);

        // Advance time 5 years into the future
        Carbon::setTestNow(Carbon::parse('2031-11-02 12:00:00', 'Asia/Aden'));

        $effectiveFuture = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertTrue($effectiveFuture['is_custom']);
        $this->assertEquals(14.00, $effectiveFuture['discount_cash']);
    }

    /**
     * Case 9 & 10: Start-time boundary is inclusive (<= now) and End-time boundary is exclusive (> now).
     */
    public function test_start_inclusive_and_end_exclusive_boundaries(): void
    {
        $exactStart = Carbon::parse('2026-11-02 10:00:00', 'Asia/Aden');
        $exactEnd = Carbon::parse('2026-11-02 18:00:00', 'Asia/Aden');

        $discount = VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 16.00,
            'discount_credit' => 11.00,
            'status' => true,
            'discount_start_date' => $exactStart,
            'discount_end_date' => $exactEnd,
            'created_by' => $this->user->user_id,
        ]);

        // 1 second before start -> Not effective
        Carbon::setTestNow($exactStart->copy()->subSecond());
        $this->assertFalse($discount->isEffectiveNow());
        $res = $this->discountService->getEffectiveDiscount($this->network->network_id, $this->pos->pos_id, $this->package->package_id);
        $this->assertFalse($res['is_custom']);

        // Exactly at start -> Effective (inclusive)
        Carbon::setTestNow($exactStart);
        $this->assertTrue($discount->isEffectiveNow());
        $res = $this->discountService->getEffectiveDiscount($this->network->network_id, $this->pos->pos_id, $this->package->package_id);
        $this->assertTrue($res['is_custom']);

        // 1 second before end -> Effective
        Carbon::setTestNow($exactEnd->copy()->subSecond());
        $this->assertTrue($discount->isEffectiveNow());
        $res = $this->discountService->getEffectiveDiscount($this->network->network_id, $this->pos->pos_id, $this->package->package_id);
        $this->assertTrue($res['is_custom']);

        // Exactly at end -> NOT effective (exclusive)
        Carbon::setTestNow($exactEnd);
        $this->assertFalse($discount->isEffectiveNow());
        $res = $this->discountService->getEffectiveDiscount($this->network->network_id, $this->pos->pos_id, $this->package->package_id);
        $this->assertFalse($res['is_custom']);
    }

    /**
     * Case 11 - 14: Cash and credit percentages remain strictly independent.
     */
    public function test_cash_and_credit_rates_are_independent_and_unmixed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        // Custom has 15% cash and 2% credit (credit is lower than default 5%)
        VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 15.00,
            'discount_credit' => 2.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-10 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        $effective = $this->discountService->getEffectiveDiscount(
            $this->network->network_id,
            $this->pos->pos_id,
            $this->package->package_id
        );

        $this->assertEquals(15.00, $effective['discount_cash']);
        $this->assertEquals(2.00, $effective['discount_credit']);
        $this->assertEquals(850.00, $effective['effective_cash_price']);
        $this->assertEquals(980.00, $effective['effective_credit_price']);
        $this->assertEquals(150.00, $effective['cash_savings']);
        $this->assertEquals(20.00, $effective['credit_savings']);
    }

    /**
     * Case 15 - 17: Date validation rejects invalid dates, equal dates, and end < start.
     */
    public function test_date_validation_rejects_invalid_date_boundaries(): void
    {
        // 1. Invalid date-time format
        $res = $this->withHeaders($this->headers)->postJson('/api/v1/voucher-package-discounts', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 10.0,
            'discount_credit' => 5.0,
            'discount_start_date' => 'invalid-date-string',
        ]);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['discount_start_date']);

        // 2. End date earlier than start date
        $res = $this->withHeaders($this->headers)->postJson('/api/v1/voucher-package-discounts', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 10.0,
            'discount_credit' => 5.0,
            'discount_start_date' => '2026-11-05 10:00:00',
            'discount_end_date' => '2026-11-04 10:00:00',
        ]);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['discount_end_date']);

        // 3. End date equal to start date
        $res = $this->withHeaders($this->headers)->postJson('/api/v1/voucher-package-discounts', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 10.0,
            'discount_credit' => 5.0,
            'discount_start_date' => '2026-11-05 10:00:00',
            'discount_end_date' => '2026-11-05 10:00:00',
        ]);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['discount_end_date']);
    }

    /**
     * Case 18 - 20: Partial update combined period validation and clearing optional end date.
     */
    public function test_partial_update_validates_combined_resulting_period_and_preserves_omitted_fields(): void
    {
        $discount = VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 12.00,
            'discount_credit' => 6.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-11-05 10:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-15 10:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        // Attempting to update ONLY discount_end_date to a time before stored discount_start_date
        $res = $this->withHeaders($this->headers)->putJson('/api/v1/voucher-package-discounts/'.$discount->discount_id, [
            'discount_end_date' => '2026-11-04 10:00:00', // Earlier than stored start (Nov 5)
        ]);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['discount_end_date']);

        // Updating only discount_cash preserves the stored dates
        $res = $this->withHeaders($this->headers)->putJson('/api/v1/voucher-package-discounts/'.$discount->discount_id, [
            'discount_cash' => 14.50,
        ]);
        $res->assertStatus(200);
        $discount->refresh();
        $this->assertEquals(14.50, (float) $discount->discount_cash);
        $this->assertEquals('2026-11-05 10:00:00', Carbon::parse($discount->discount_start_date)->toDateTimeString());
        $this->assertEquals('2026-11-15 10:00:00', Carbon::parse($discount->discount_end_date)->toDateTimeString());

        // Clearing the end date with null
        $res = $this->withHeaders($this->headers)->putJson('/api/v1/voucher-package-discounts/'.$discount->discount_id, [
            'discount_end_date' => null,
        ]);
        $res->assertStatus(200);
        $discount->refresh();
        $this->assertNull($discount->discount_end_date);
    }

    /**
     * Case 21 - 26: API creation, updating, resource serialization, and administrative filtering.
     */
    public function test_api_crud_and_resource_distinguishes_status_from_effectiveness(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        // 1. Create with scheduled future start
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/voucher-package-discounts', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 20.0,
            'discount_credit' => 15.0,
            'status' => true,
            'discount_start_date' => '2026-11-05 00:00:00',
            'discount_end_date' => '2026-11-20 00:00:00',
        ]);

        $createRes->assertStatus(201);
        $createRes->assertJsonPath('data.discount_cash', 20);
        $createRes->assertJsonPath('data.status', true);
        $createRes->assertJsonPath('data.discount_start_date', '2026-11-05 00:00:00');
        $createRes->assertJsonPath('data.discount_end_date', '2026-11-20 00:00:00');
        $createRes->assertJsonPath('data.is_effective_now', false); // Future -> not effective now!

        $discountId = $createRes->json('data.discount_id');

        // 2. Listing still includes scheduled future discount
        $listRes = $this->withHeaders($this->headers)->getJson('/api/v1/voucher-package-discounts?network_id='.$this->network->network_id);
        $listRes->assertStatus(200);
        $this->assertCount(1, $listRes->json('data'));

        // 3. Effective discount endpoint shows package default because custom is future
        $effectiveRes = $this->withHeaders($this->headers)->getJson(
            "/api/v1/voucher-package-discounts/effective?network_id={$this->network->network_id}&pos_id={$this->pos->pos_id}&package_id={$this->package->package_id}"
        );
        $effectiveRes->assertStatus(200);
        $effectiveRes->assertJsonPath('data.is_custom', false);
        $effectiveRes->assertJsonPath('data.discount_cash', 10);

        // 4. Update discount start date to yesterday -> becomes effective now
        $updateRes = $this->withHeaders($this->headers)->putJson('/api/v1/voucher-package-discounts/'.$discountId, [
            'discount_start_date' => '2026-11-01 00:00:00',
        ]);
        $updateRes->assertStatus(200);
        $updateRes->assertJsonPath('data.is_effective_now', true); // Now effective!

        // 5. Effective discount endpoint now returns custom rate
        $effectiveRes2 = $this->withHeaders($this->headers)->getJson(
            "/api/v1/voucher-package-discounts/effective?network_id={$this->network->network_id}&pos_id={$this->pos->pos_id}&package_id={$this->package->package_id}"
        );
        $effectiveRes2->assertStatus(200);
        $effectiveRes2->assertJsonPath('data.is_custom', true);
        $effectiveRes2->assertJsonPath('data.discount_cash', 20);
    }

    /**
     * Case 27 - 29: Procurement snapshot compatibility:
     * A discount captured at request submission or review selection remains immutable.
     */
    public function test_procurement_snapshot_compatibility_across_discount_expiration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', 'Asia/Aden'));

        // 1. Create a custom discount active today, expiring tomorrow
        $discount = VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 22.00,
            'discount_credit' => 16.00,
            'status' => true,
            'discount_start_date' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Aden'),
            'discount_end_date' => Carbon::parse('2026-11-03 00:00:00', 'Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);

        // 2. POS submits a procurement request while discount is 22%
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/requests', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH->value,
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 10,
                ],
            ],
        ]);
        $createRes->assertStatus(201);
        $requestId = $createRes->json('data.request_id');
        $req = RequestModel::with('items')->findOrFail($requestId);

        $firstItem = $req->items->first();
        $this->assertEquals(22.00, (float) $firstItem->item_discount);
        $this->assertEquals(7800.00, (float) $req->request_total_amount); // 1000 * 10 * (1 - 0.22) = 7800

        // 3. Advance time to next week -> Custom discount is now EXPIRED!
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Asia/Aden'));
        $this->session->update(['expires_at' => Carbon::now('Asia/Aden')->addDays(30)]);
        $this->assertFalse($discount->fresh()->isEffectiveNow());

        // Effective discount query today returns 10% (package default)
        $effectiveNow = $this->discountService->getEffectiveDiscount($this->network->network_id, $this->pos->pos_id, $this->package->package_id);
        $this->assertFalse($effectiveNow['is_custom']);
        $this->assertEquals(10.00, $effectiveNow['discount_cash']);

        // 4. Verify original request item STILL has 22% captured discount (Zero Retrospective Change)
        $req->refresh();
        $this->assertEquals(22.00, (float) $req->items()->first()->item_discount);
        $this->assertEquals(7800.00, (float) $req->request_total_amount);

        // 5. Network approves the request without modifying items -> invoice preserves the 22% captured pricing!
        $approveRes = $this->withHeaders($this->headers)->postJson("/api/v1/requests/{$requestId}/approve");
        $approveRes->assertStatus(200);

        $invoice = SaleInvoice::where('request_id', $requestId)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(7800.00, (float) $invoice->invoice_total_amount);
        $this->assertEquals(22.00, (float) $invoice->items()->first()->item_discount);

        // 6. Preview HMAC token protection for new/replacement items
        $packageB = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة إضافية 500',
            'package_voucher_price' => 500.00,
            'package_default_cash_discount' => 5.00,
            'package_default_credit_discount' => 2.00,
            'package_voucher_count' => 100,
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        $pricingService = app(RequestPricingReviewService::class);
        $token = $pricingService->generatePricingToken($req->request_id, $packageB->package_id, 500.0, 25.0);

        // Even though catalog effective discount for packageB is 5%, token preserves frozen 25%
        $resolved = $pricingService->resolveItemPricing($req, $packageB->package_id, $token, SaleType::CASH);

        $this->assertEquals(25.0, $resolved['item_discount']);
        $this->assertEquals('REVIEW_CAPTURED', $resolved['source']);
    }

    /**
     * Preventing restoration of soft-deleted discount if active discount already exists.
     */
    public function test_cannot_restore_discount_if_active_one_already_exists(): void
    {
        // 1. Create first discount and delete it
        $d1 = VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 10.00,
            'discount_credit' => 5.00,
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);
        $d1->delete();

        // 2. Create second discount (active)
        $d2 = VoucherPackageDiscount::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'package_id' => $this->package->package_id,
            'discount_cash' => 20.00,
            'discount_credit' => 15.00,
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        // 3. Attempting to restore d1 must be rejected to prevent duplicate active records
        $this->expectException(ValidationException::class);
        $this->discountService->restore($d1->discount_id);
    }
}
