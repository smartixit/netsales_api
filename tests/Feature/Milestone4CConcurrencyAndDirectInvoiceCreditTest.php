<?php

namespace Tests\Feature;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\RoleScope;
use App\Enums\SaleType;
use App\Enums\UserStatus;
use App\Enums\WalletOperationType;
use App\Http\Requests\API\V1\SaleInvoice\StoreSaleInvoiceRequest;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\NetworkWalletLog;
use App\Models\OutboxMessage;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\PosWalletLog;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserSession;
use App\Models\VoucherPackage;
use App\Services\SaleInvoiceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class Milestone4CConcurrencyAndDirectInvoiceDebtTest extends TestCase
{
    protected User $networkOwner;

    protected User $posOwner;

    protected Network $network;

    protected Pos $pos;

    protected PosWallet $posWallet;

    protected NetworkWallet $networkWallet;

    protected VoucherPackage $package;

    /** @var array<string, string> */
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = uniqid();

        $this->networkOwner = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'مالك الشبكة المتزامنة '.$suffix,
            'user_phone_number' => '77'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->network = Network::create([
            'owner_id' => $this->networkOwner->user_id,
            'network_name' => 'شبكة التزامن '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_uuid' => 'NET-W-'.$suffix,
            'wallet_balance' => 0.00,
            'status' => true,
        ]);

        $this->posOwner = $this->createUserWithScope(RoleScope::POS, [
            'role_id' => null,
            'role_scope' => null,
            'user_full_name' => 'وكيل نقطة البيع المتزامنة '.$suffix,
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->posOwner->user_id,
            'pos_name' => 'نقطة التزامن '.$suffix,
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

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_uuid' => 'POS-W-'.$suffix,
            'wallet_balance' => 0.00,
            'credit_limit' => 200000.00,
            'status' => true,
        ]);

        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'package_name' => 'باقة التزامن '.$suffix,
            'package_voucher_price' => 5000.00,
            'package_hours' => 24,
            'package_price' => 5000.00,
            'status' => true,
            'created_by' => $this->networkOwner->user_id,
        ]);

        $deviceUuid = 'DEV-NET-'.$suffix;
        UserDevice::create([
            'user_id' => $this->networkOwner->user_id,
            'device_uuid' => $deviceUuid,
            'device_name' => 'Net Device',
            'device_type' => 'ANDROID',
            'status' => 'ACTIVE',
            'is_primary' => true,
        ]);

        $session = UserSession::create([
            'user_id' => $this->networkOwner->user_id,
            'device_uuid' => $deviceUuid,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'Accept' => 'application/json',
            'X-Session-Id' => (string) $session->session_id,
            'X-Device-Uuid' => $deviceUuid,
            'X-Bypass-Encryption' => 'true',
        ];
    }

    /**
     * 1. التحقق من محاسبة فاتورة CREDIT المباشرة وحفظ حالة السداد الجزئي دون Repayment.
     */
    public function test_direct_credit_invoice_preserves_partial_payment_and_wallet_settlement(): void
    {
        $this->posWallet->wallet_balance = 2000.00;
        $this->posWallet->save();

        /** @var SaleInvoiceService $invoiceService */
        $invoiceService = app(SaleInvoiceService::class);

        // إنشاء طلب فاتورة بيع مباشر بقيمة 10,000 ريال نوع CREDIT
        // رصيد المحفظة 2,000، لذا المدفوع مغطى 2,000، والمتبقي ذمة مدينة 8,000
        $request = StoreSaleInvoiceRequest::create('/api/v1/sale-invoices', 'POST', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CREDIT',
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 2,
                    'unit_price' => 5000.00,
                    'subtotal' => 10000.00,
                ],
            ],
            'notes' => 'فاتورة مباشرة لاختبار المحاسبة والديون',
        ]);
        $request->setUserResolver(fn () => $this->networkOwner);

        $invoice = $invoiceService->createDirectInvoice($request, $this->networkOwner->user_id);

        $invoice->refresh();
        $this->networkWallet->refresh();
        $this->posWallet->refresh();

        $this->assertEquals(10000.00, (float) $invoice->invoice_total_amount);
        $this->assertEquals(2000.00, (float) $invoice->paid_amount);
        $this->assertEquals(8000.00, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PARTIALLY_PAID, $invoice->payment_status);

        // النقد المتاح للشبكة زاد فقط بالمبلغ المغطى فعلياً (2,000)، وليس كامل الفاتورة
        $this->assertEquals(2000.00, (float) $this->networkWallet->wallet_balance);
        // محفظة نقطة البيع نزلت إلى -8,000
        $this->assertEquals(-8000.00, (float) $this->posWallet->wallet_balance);

        // يجب أن ينتج عن الفاتورة قيد شبكة واحد فقط بالمبلغ المغطى فعلياً.
        $networkLogs = NetworkWalletLog::query()
            ->where('wallet_id', $this->networkWallet->wallet_id)
            ->orderBy('log_id')
            ->get();

        $this->assertCount(1, $networkLogs);
        $this->assertSame(WalletOperationType::DEPOSIT, $networkLogs->sole()->log_operation);
        $this->assertSame('2000.00', $networkLogs->sole()->log_amount);

        // ويجب أن يسجل خصم الفاتورة كاملاً من محفظة نقطة البيع مرة واحدة.
        $posLogs = PosWalletLog::query()
            ->where('wallet_id', $this->posWallet->wallet_id)
            ->orderBy('log_id')
            ->get();

        $this->assertCount(1, $posLogs);
        $this->assertSame(WalletOperationType::WITHDRAW, $posLogs->sole()->log_operation_type);
        $this->assertSame('10000.00', $posLogs->sole()->log_amount);

    }
}
