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
            'credit_limit' => 10000.00,
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

    public function test_delete_credit_invoice_refunds_pos_balance_and_reverses_network_collected_portion(): void
    {
        // 1. Create a CREDIT invoice of total 5,000, paid 2,000, remaining 3,000
        $invoice = $this->createTestInvoice(SaleType::CREDIT, 5000.00, paid: 2000.00, remaining: 3000.00);

        $this->posWallet->refresh();
        $posBalanceBeforeDelete = (float) $this->posWallet->wallet_balance;

        $this->networkWallet->refresh();
        $networkBalanceBeforeDelete = (float) $this->networkWallet->wallet_balance;

        // 2. Delete invoice
        $deleted = $this->service->deleteInvoice($invoice->invoice_id);
        $this->assertTrue($deleted);

        // 3. Verify POS was refunded the entire 5,000 debit
        $this->posWallet->refresh();
        $this->assertEquals(
            $posBalanceBeforeDelete + 5000.00,
            (float) $this->posWallet->wallet_balance
        );

        // 4. Verify Network was decremented the 2,000 collected portion
        $this->networkWallet->refresh();
        $this->assertEquals(
            $networkBalanceBeforeDelete - 2000.00,
            (float) $this->networkWallet->wallet_balance
        );

        $this->assertDatabaseHas('pos_wallet_logs', [
            'wallet_id' => $this->posWallet->wallet_id,
            'log_operation_type' => WalletOperationType::DEPOSIT->value,
            'log_amount' => 5000.00,
        ]);

        $this->assertDatabaseHas('network_wallet_logs', [
            'wallet_id' => $this->networkWallet->wallet_id,
            'log_operation' => WalletOperationType::WITHDRAW->value,
            'log_amount' => 2000.00,
        ]);
    }

    public function test_updating_sale_type_from_cash_to_credit_atomically_adjusts_both_wallets(): void
    {
        // 1. Create CASH invoice of 4,000 (paid = 4,000, remaining = 0)
        $invoice = $this->createTestInvoice(SaleType::CASH, 4000.00);

        $this->posWallet->update(['wallet_balance' => '1500.00']);
        $this->networkWallet->update(['wallet_balance' => '14000.00']);

        // 2. Update sale type to CREDIT
        $request = new UpdateSaleInvoiceRequest;
        $request->merge(['sale_type' => 'CREDIT']);

        $updated = $this->service->updateSaleType($invoice->invoice_id, $request);

        // 3. In CREDIT: POS has 1,500, so 1,500 is paid and 2,500 is remaining debt
        $this->assertEquals(SaleType::CREDIT, $updated->sale_type);
        $this->assertEquals(1500.00, (float) $updated->paid_amount);
        $this->assertEquals(2500.00, (float) $updated->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PARTIALLY_PAID, $updated->payment_status);

        // 4. POS wallet should be debited 4,000 -> 1,500 - 4,000 = -2,500
        $this->posWallet->refresh();
        $this->assertEquals(-2500.00, (float) $this->posWallet->wallet_balance);

        // 5. Network wallet originally got 4,000 cash; now as credit it only gets 1,500 -> diff 2,500 deducted
        $this->networkWallet->refresh();
        $this->assertEquals(14000.00 - 2500.00, (float) $this->networkWallet->wallet_balance);
    }

    public function test_updating_sale_type_from_credit_to_cash_clears_pos_debt_and_collects_remainder(): void
    {
        // 1. Create CREDIT invoice of 5,000 (paid = 1,000, remaining = 4,000)
        $invoice = $this->createTestInvoice(SaleType::CREDIT, 5000.00, paid: 1000.00, remaining: 4000.00);

        $this->posWallet->update(['wallet_balance' => '-4000.00']);
        $this->networkWallet->update(['wallet_balance' => '11000.00']);

        // 2. Update to CASH
        $request = new UpdateSaleInvoiceRequest;
        $request->merge(['sale_type' => 'CASH']);

        $updated = $this->service->updateSaleType($invoice->invoice_id, $request);

        // 3. In CASH: fully paid
        $this->assertEquals(SaleType::CASH, $updated->sale_type);
        $this->assertEquals(5000.00, (float) $updated->paid_amount);
        $this->assertEquals(0.00, (float) $updated->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PAID, $updated->payment_status);

        // 4. POS had debt of 4,000 from this invoice; now that it's paid in cash, POS gets the 5,000 credit back -> -4,000 + 5,000 = 1,000
        $this->posWallet->refresh();
        $this->assertEquals(1000.00, (float) $this->posWallet->wallet_balance);

        // 5. Network gets remaining 4,000 in cash -> 11,000 + 4,000 = 15,000
        $this->networkWallet->refresh();
        $this->assertEquals(15000.00, (float) $this->networkWallet->wallet_balance);
    }

    public function test_create_credit_sale_invoice_with_cash_down_payment_updates_wallets_and_invoice_amounts(): void
    {
        // POS has balance 0, credit limit 10,000
        $this->posWallet->update(['wallet_balance' => 0.00, 'credit_limit' => 10000.00]);
        $this->networkWallet->update(['wallet_balance' => 5000.00]);

        $request = new StoreSaleInvoiceRequest;
        $request->merge([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'sale_type' => 'CREDIT',
            'paid_amount' => 1500.00, // Cash down payment
            'items' => [
                [
                    'package_id' => $this->package->package_id,
                    'voucher_count' => 100, // 100 * 50 = 5000 (after 5 credit discount = 4500)
                ],
            ],
        ]);

        $invoice = $this->service->createDirectInvoice($request, $this->user->user_id);

        $this->assertEquals(SaleType::CREDIT, $invoice->sale_type);
        $this->assertEquals(4750.00, (float) $invoice->invoice_total_amount);
        $this->assertEquals(1500.00, (float) $invoice->paid_amount);
        $this->assertEquals(3250.00, (float) $invoice->remaining_amount);
        $this->assertEquals(InvoicePaymentStatus::PARTIALLY_PAID, $invoice->payment_status);

        // POS wallet balance: was 0, now owes 3250 -> -3250
        $this->posWallet->refresh();
        $this->assertEquals(-3250.00, (float) $this->posWallet->wallet_balance);

        // Network wallet balance: was 5000, received 1500 cash -> 6500
        $this->networkWallet->refresh();
        $this->assertEquals(6500.00, (float) $this->networkWallet->wallet_balance);
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
