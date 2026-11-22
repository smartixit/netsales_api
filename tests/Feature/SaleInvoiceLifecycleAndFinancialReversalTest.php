<?php

namespace Tests\Feature;

use App\Enums\InvoicePaymentStatus;
use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\SaleType;
use App\Enums\WalletOperationType;
use App\Http\Requests\API\V1\SaleInvoice\StoreSaleInvoiceRequest;
use App\Http\Requests\API\V1\SaleInvoice\UpdateSaleInvoiceRequest;
use App\Models\Network;
use App\Models\NetworkPos;
use App\Models\NetworkWallet;
use App\Models\Pos;
use App\Models\PosWallet;
use App\Models\SaleInvoice;
use App\Models\User;
use App\Models\VoucherPackage;
use App\Services\SaleInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaleInvoiceLifecycleAndFinancialReversalTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected Network $network;

    protected Pos $pos;

    protected NetworkWallet $networkWallet;

    protected PosWallet $posWallet;

    protected VoucherPackage $package;

    protected SaleInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();
        $this->actingAs($this->user);
        $suffix = uniqid();

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'شبكة تجربة الفواتير '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'نقطة تجربة الفواتير '.$suffix,
            'pos_phone_number' => '73'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);

        NetworkPos::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_uuid' => 'net-wal-'.$suffix,
            'wallet_balance' => 10000.00,
            'currency_code' => 'YER',
            'status' => true,
        ]);

        $this->posWallet = PosWallet::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'wallet_uuid' => 'pos-wal-'.$suffix,
            'wallet_balance' => 2000.00,
            'currency_code' => 'YER',
            'status' => true,
        ]);

        $this->package = VoucherPackage::create([
            'network_id' => $this->network->network_id,
            'package_name' => 'Gold Package 500 '.$suffix,
            'package_voucher_price' => 50.00,
            'package_voucher_count' => 100,
            'package_balance' => 500.00,
            'package_default_cash_discount' => 10.00,
            'package_default_credit_discount' => 5.00,
            'status' => true,
            'created_by' => $this->user->user_id,
        ]);

        $this->service = app(SaleInvoiceService::class);
    }

    public function test_delete_cash_invoice_reverses_network_wallet_balance_and_creates_reversal_log(): void
    {
        // 1. Create a CASH invoice of 4,500
        $invoice = $this->createTestInvoice(SaleType::CASH, 4500.00);

        // Network received 4,500 deposit originally
        $this->networkWallet->refresh();
        $initialNetworkBalance = (float) $this->networkWallet->wallet_balance;

        // 2. Delete invoice
        $deleted = $this->service->deleteInvoice($invoice->invoice_id);
        $this->assertTrue($deleted);

        // 3. Verify financial reversal: Network balance decremented by 4,500
        $this->networkWallet->refresh();
        $this->assertEquals(
            $initialNetworkBalance - 4500.00,
            (float) $this->networkWallet->wallet_balance
        );

        $this->assertDatabaseHas('network_wallet_logs', [
            'wallet_id' => $this->networkWallet->wallet_id,
            'log_operation' => WalletOperationType::WITHDRAW->value,
            'log_amount' => 4500.00,
        ]);

        $this->assertSoftDeleted('sale_invoices', [
            'invoice_id' => $invoice->invoice_id,
        ]);
    }

    public function test_restoring_financially_reversed_invoice_is_prohibited(): void
    {
        $invoice = $this->createTestInvoice(SaleType::CASH, 2000.00);
        $this->service->deleteInvoice($invoice->invoice_id);

        $this->expectException(ValidationException::class);
        $this->service->restoreInvoice($invoice->invoice_id);
    }

    private function createTestInvoice(SaleType $type, float $total, float $paid = 0.0, float $remaining = 0.0): SaleInvoice
    {
        $status = $type === SaleType::CASH
            ? InvoicePaymentStatus::PAID
            : ($remaining <= 0.001 ? InvoicePaymentStatus::PAID : ($paid > 0.001 ? InvoicePaymentStatus::PARTIALLY_PAID : InvoicePaymentStatus::UNPAID));

        if ($type === SaleType::CASH) {
            $paid = $total;
            $remaining = 0.0;
        }

        return SaleInvoice::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => $type,
            'invoice_total_amount' => $total,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'payment_status' => $status,
            'invoice_date' => now('Asia/Aden'),
            'created_by' => $this->user->user_id,
        ]);
    }
}
