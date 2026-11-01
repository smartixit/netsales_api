<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RequestStatus;
use App\Enums\SaleType;
use App\Http\Resources\API\V1\Request\RequestResource;
use App\Http\Resources\API\V1\SaleInvoice\SaleInvoiceResource;
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
use App\Services\SaleInvoiceService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request as HttpRequest;
use Tests\TestCase;

class RequestInvoiceRelationshipTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected Network $network;

    protected Pos $pos;

    protected VoucherPackage $package;

    protected NetworkWallet $networkWallet;

    protected PosWallet $posWallet;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();
        $suffix = uniqid();
        $deviceUuid = 'RELATION-DEV-'.$suffix;

        UserDevice::firstOrCreate([
            'user_id' => $this->user->user_id,
            'device_uuid' => $deviceUuid,
        ], [
            'device_name' => 'PHPUnit Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'device_uuid' => $deviceUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Device-Uuid' => $deviceUuid,
            'X-Bypass-Encryption' => 'true',
        ];

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'شبكة العلاقة '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'نقطة العلاقة '.$suffix,
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

        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'created_by' => $this->user->user_id,
            'package_name' => 'باقة العلاقة '.$suffix,
            'package_color' => '#10B981',
            'package_voucher_price' => 500.00,
            'package_default_cash_discount' => 10.00,
            'package_default_credit_discount' => 5.00,
            'status' => true,
        ]);

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_balance' => 50000.00,
            'status' => true,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);
    }

    public function test_sale_invoice_generation_populates_request_id_and_eloquent_relationships(): void
    {
        // 1. إنشاء طلب معلق وبنوده
        $request = RequestModel::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH,
            'request_total_amount' => 4500.00,
            'status' => RequestStatus::PENDING,
            'created_by' => $this->user->user_id,
        ]);

        $request->items()->create([
            'package_id' => $this->package->package_id,
            'voucher_price' => 500.00,
            'voucher_count' => 10,
            'item_discount' => 10.00,
            'item_total_amount' => 4500.00,
        ]);

        // 2. توليد الفاتورة عبر SaleInvoiceService
        $service = app(SaleInvoiceService::class);
        $invoice = $service->generateFromRequest($request, $this->user->user_id);

        $this->assertNotNull($invoice->invoice_id);
        $this->assertEquals($request->request_id, $invoice->request_id, 'يجب ربط الفاتورة بمعرف الطلب في قاعدة البيانات');

        // 3. التحقق من علاقات Eloquent الثنائية
        // Request -> invoice (HasOne)
        $retrievedInvoice = $request->fresh()->invoice;
        $this->assertNotNull($retrievedInvoice);
        $this->assertEquals($invoice->invoice_id, $retrievedInvoice->invoice_id);
        $this->assertEquals($invoice->invoice_uuid, $retrievedInvoice->invoice_uuid);

        // SaleInvoice -> request (BelongsTo)
        $retrievedRequest = $invoice->fresh()->request;
        $this->assertNotNull($retrievedRequest);
        $this->assertEquals($request->request_id, $retrievedRequest->request_id);
        $this->assertEquals($request->request_uuid, $retrievedRequest->request_uuid);
    }

    public function test_database_unique_constraint_prevents_duplicate_invoice_for_same_request(): void
    {
        $request = RequestModel::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH,
            'request_total_amount' => 1000.00,
            'status' => RequestStatus::APPROVED,
            'created_by' => $this->user->user_id,
        ]);

        // الفاتورة الأولى
        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'request_id' => $request->request_id,
            'sale_type' => SaleType::CASH,
            'invoice_total_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'remaining_amount' => 0.00,
            'created_by' => $this->user->user_id,
        ]);

        // محاولة إدخال فاتورة ثانية مرتبطة بنفس الطلب مباشرة على قاعدة البيانات
        $this->expectException(QueryException::class);

        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'request_id' => $request->request_id, // تكرار نفس request_id
            'sale_type' => SaleType::CASH,
            'invoice_total_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'remaining_amount' => 0.00,
            'created_by' => $this->user->user_id,
        ]);
    }

    public function test_direct_invoices_without_request_have_null_request_id(): void
    {
        $directInvoice = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'request_id' => null, // فاتورة بيع مباشرة غير ناتجة عن طلب توريد
            'sale_type' => SaleType::CASH,
            'invoice_total_amount' => 5000.00,
            'paid_amount' => 5000.00,
            'remaining_amount' => 0.00,
            'created_by' => $this->user->user_id,
        ]);

        $this->assertNull($directInvoice->fresh()->request_id);
        $this->assertNull($directInvoice->fresh()->request);
    }

    public function test_foreign_key_rejects_non_existent_request_id(): void
    {
        $this->expectException(QueryException::class);

        SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'request_id' => 99999999, // معرف طلب غير موجود إطلاقاً
            'sale_type' => SaleType::CASH,
            'invoice_total_amount' => 5000.00,
            'paid_amount' => 5000.00,
            'remaining_amount' => 0.00,
            'created_by' => $this->user->user_id,
        ]);
    }

    public function test_api_resources_expose_bidirectional_relationship_uuids(): void
    {
        $request = RequestModel::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => SaleType::CASH,
            'request_total_amount' => 2000.00,
            'status' => RequestStatus::APPROVED,
            'created_by' => $this->user->user_id,
        ]);

        $invoice = SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'request_id' => $request->request_id,
            'sale_type' => SaleType::CASH,
            'invoice_total_amount' => 2000.00,
            'paid_amount' => 2000.00,
            'remaining_amount' => 0.00,
            'created_by' => $this->user->user_id,
        ]);

        $dummyHttpReq = HttpRequest::create('/api/v1/test');

        // فحص مورد الطلب RequestResource
        $requestData = (new RequestResource($request->load('invoice')))->toArray($dummyHttpReq);
        $this->assertEquals($invoice->invoice_id, $requestData['invoice_id']);
        $this->assertEquals($invoice->invoice_uuid, $requestData['invoice_uuid']);

        // فحص مورد الفاتورة SaleInvoiceResource
        $invoiceData = (new SaleInvoiceResource($invoice->load('request')))->toArray($dummyHttpReq);
        $this->assertEquals($request->request_id, $invoiceData['request_id']);
        $this->assertEquals($request->request_uuid, $invoiceData['request_uuid']);
    }
}
