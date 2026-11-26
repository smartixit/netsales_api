<?php

namespace Tests\Feature;

use App\Enums\BillingInvoiceStatus;
use App\Enums\NetworkStatus;
use App\Enums\RoleScope;
use App\Http\Resources\API\V1\Billing\BillingInvoiceResource;
use App\Models\BillingAccount;
use App\Models\BillingInvoice;
use App\Models\BillingInvoiceLine;
use App\Models\BillingPayment;
use App\Models\BillingPaymentMethod;
use App\Models\BillingRevenueRecognitionSchedule;
use App\Models\Currency;
use App\Models\LedgerAccount;
use App\Models\LedgerJournalEntry;
use App\Models\OutboxMessage;
use App\Models\LedgerJournalLine;
use App\Models\Network;
use App\Models\TreasuryAccount;
use App\Models\User;
use App\Services\Billing\BillingCreditNoteService;
use App\Services\Billing\BillingLedgerChartService;
use App\Services\Billing\BillingLedgerService;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\BillingReconciliationService;
use App\Services\Billing\BillingRefundService;
use App\Services\Billing\BillingSelfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingCreditNoteSettlementTest extends TestCase
{
    use DatabaseTransactions;

    private User $actor;

    private User $networkOwner;

    private Currency $currency;

    private BillingAccount $account;

    private BillingInvoice $invoice;

    private BillingInvoiceLine $line;

    /** @var array<int, LedgerAccount> */
    private array $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::query()
            ->whereHas('role', fn ($query) => $query->where('role_key', 'SUPER_ADMIN'))
            ->firstOrFail();
        $this->currency = Currency::query()->where('code', 'YER')->firstOrFail();

        $networkOwnerPhone = '74'.random_int(10000000, 99999999);
        $this->networkOwner = $this->createUserWithScope(RoleScope::NETWORK, [
            'role_id' => null,
            'user_full_name' => 'Credit Note Network Owner '.Str::random(6),
            'user_phone_number' => $networkOwnerPhone,
            'user_email' => Str::lower(Str::random(12)).'@credit-note.test',
            'user_password' => bcrypt('Secret@123'),
            'status' => 'ACTIVE',
        ]);

        $network = Network::query()->create([
            'owner_id' => $this->networkOwner->user_id,
            'network_uuid' => (string) Str::uuid(),
            'network_name' => 'Credit Note Test '.uniqid(),
            'network_phone_number' => $networkOwnerPhone,
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->account = BillingAccount::query()->create([
            'network_id' => $network->network_id,
            'account_number' => 'BA-'.Str::upper(Str::random(16)),
            'legal_name' => 'Credit Note Settlement Test',
            'default_currency_id' => $this->currency->currency_id,
            'payment_terms_days' => 0,
            'status' => 'ACTIVE',
            'created_by' => $this->actor->user_id,
        ]);

        $this->accounts = app(BillingLedgerChartService::class)->ensureForCurrency($this->currency);
    }

    public function test_credit_note_reverses_unearned_and_earned_subscription_revenue_automatically(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);
        $schedule = BillingRevenueRecognitionSchedule::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'invoice_line_id' => $this->line->invoice_line_id,
            'currency_id' => $this->currency->currency_id,
            'revenue_account_code' => '4100',
            'total_minor' => 1000,
            'recognized_minor' => 300,
            'service_start_on' => '2026-01-01',
            'service_end_on' => '2026-01-10',
            'last_recognized_on' => '2026-01-03',
            'status' => 'ACTIVE',
        ]);

        $note = app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 800]],
            'إلغاء جزء من خدمة الاشتراك',
            'credit-note-subscription-'.Str::uuid(),
            $this->actor
        );

        $this->assertSame('AUTOMATIC', $note->accounting_treatment);
        $this->assertSame(800, $note->total_minor);
        $this->assertSame(800, $note->applied_to_receivable_minor);
        $this->assertSame(0, $note->refundable_minor);
        $schedule->refresh();
        $this->assertSame(200, $schedule->total_minor);
        $this->assertSame(200, $schedule->recognized_minor);
        $this->assertSame('COMPLETED', $schedule->status);

        $this->invoice->refresh();
        $this->assertSame(800, $this->invoice->credited_minor);
        $this->assertSame(200, $this->invoice->remaining_minor);
        $this->assertSame(BillingInvoiceStatus::PARTIALLY_CREDITED, $this->invoice->status);

        $entry = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_CREDIT_NOTE')
            ->where('source_id', $note->credit_note_id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertLedgerEntryBalanced($entry);
        $this->assertLedgerAmount($entry, '2100', 'debit_minor', 700);
        $this->assertLedgerAmount($entry, '4100', 'debit_minor', 100);
        $this->assertLedgerAmount($entry, '1100', 'credit_minor', 800);
    }

    public function test_partially_credited_invoice_accepts_and_verifies_remaining_payment(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);
        BillingRevenueRecognitionSchedule::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'invoice_line_id' => $this->line->invoice_line_id,
            'currency_id' => $this->currency->currency_id,
            'revenue_account_code' => '4100',
            'total_minor' => 1000,
            'recognized_minor' => 300,
            'service_start_on' => '2026-01-01',
            'service_end_on' => '2026-01-10',
            'last_recognized_on' => '2026-01-03',
            'status' => 'ACTIVE',
        ]);

        app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 800]],
            'تخفيض جزئي قبل استكمال السداد',
            'credit-note-before-settlement-'.Str::uuid(),
            $this->actor
        );

        $this->invoice->refresh();
        $this->assertSame(BillingInvoiceStatus::PARTIALLY_CREDITED, $this->invoice->status);
        $this->assertSame(200, $this->invoice->remaining_minor);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $treasury = TreasuryAccount::query()->create([
            'account_code' => 'TR-CREDIT-'.Str::upper(Str::random(10)),
            'name' => 'Partially Credited Invoice Treasury',
            'account_type' => 'CASH',
            'currency_id' => $this->currency->currency_id,
            'status' => 'ACTIVE',
        ]);

        $payments = app(BillingPaymentService::class);
        $payment = $payments->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            200,
            'partial-credit-payment-'.Str::uuid(),
            $this->actor
        );

        $verified = $payments->verify(
            (int) $payment->payment_id,
            (int) $treasury->treasury_account_id,
            $this->actor
        );

        $this->assertSame('VERIFIED', $verified->status->value);
        $this->invoice->refresh();
        $this->assertSame(200, $this->invoice->paid_minor);
        $this->assertSame(800, $this->invoice->credited_minor);
        $this->assertSame(0, $this->invoice->remaining_minor);
        $this->assertSame(BillingInvoiceStatus::PAID, $this->invoice->status);
    }

    public function test_credit_note_keeps_initial_revenue_schedule_held_until_payment(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);
        $schedule = BillingRevenueRecognitionSchedule::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'invoice_line_id' => $this->line->invoice_line_id,
            'currency_id' => $this->currency->currency_id,
            'revenue_account_code' => '4100',
            'total_minor' => 1000,
            'recognized_minor' => 0,
            'service_start_on' => '2026-01-01',
            'service_end_on' => '2026-01-10',
            'status' => 'HOLD',
        ]);

        app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 200]],
            'تخفيض قبل تفعيل الاشتراك',
            'credit-note-before-payment-'.Str::uuid(),
            $this->actor
        );

        $schedule->refresh();
        $this->assertSame(800, $schedule->total_minor);
        $this->assertSame(0, $schedule->recognized_minor);
        $this->assertSame('HOLD', $schedule->status);
    }

    public function test_credit_note_allocates_tax_and_setup_revenue_without_manual_treatment(): void
    {
        $this->createInvoiceLine('SETUP_FEE', 1000, 100, 1100);

        $note = app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 550]],
            'تخفيض جزئي لرسوم التهيئة',
            'credit-note-tax-'.Str::uuid(),
            $this->actor
        );

        $this->assertSame('AUTOMATIC', $note->accounting_treatment);
        $this->assertSame(500, $note->subtotal_minor);
        $this->assertSame(50, $note->tax_minor);
        $this->assertSame(550, $note->total_minor);

        $entry = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_CREDIT_NOTE')
            ->where('source_id', $note->credit_note_id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertLedgerEntryBalanced($entry);
        $this->assertLedgerAmount($entry, '4120', 'debit_minor', 500);
        $this->assertLedgerAmount($entry, '2300', 'debit_minor', 50);
        $this->assertLedgerAmount($entry, '1100', 'credit_minor', 550);
    }

    public function test_full_customer_refund_settles_customer_credit_liability_against_treasury(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);
        BillingRevenueRecognitionSchedule::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'invoice_line_id' => $this->line->invoice_line_id,
            'currency_id' => $this->currency->currency_id,
            'revenue_account_code' => '4100',
            'total_minor' => 1000,
            'recognized_minor' => 0,
            'service_start_on' => '2026-01-01',
            'service_end_on' => '2026-01-10',
            'status' => 'PENDING',
        ]);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $treasury = TreasuryAccount::query()->create([
            'account_code' => 'TR-TEST-'.Str::upper(Str::random(10)),
            'name' => 'Test Treasury',
            'account_type' => 'CASH',
            'currency_id' => $this->currency->currency_id,
            'status' => 'ACTIVE',
        ]);

        $paymentService = app(BillingPaymentService::class);
        $payment = $paymentService->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            1000,
            'payment-'.Str::uuid(),
            $this->actor
        );
        $payment = $paymentService->verify(
            (int) $payment->payment_id,
            (int) $treasury->treasury_account_id,
            $this->actor
        );

        $this->assertSame('VERIFIED', $payment->status->value);
        $this->invoice->refresh();
        $this->assertSame(BillingInvoiceStatus::PAID, $this->invoice->status);

        $note = app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 1000]],
            'استرداد كامل بعد إلغاء الاشتراك',
            'credit-note-refund-'.Str::uuid(),
            $this->actor
        );

        $this->assertSame(0, $note->applied_to_receivable_minor);
        $this->assertSame(1000, $note->refundable_minor);

        $treasury->refresh()->load('ledgerAccount');

        $refund = app(BillingRefundService::class)->refund(
            (int) $payment->payment_id,
            (int) $note->credit_note_id,
            (int) $treasury->treasury_account_id,
            1000,
            'رد كامل إلى العميل',
            'refund-'.Str::uuid(),
            $this->actor
        );

        $this->assertSame('COMPLETED', $refund->status);
        $payment->refresh();
        $note->refresh();
        $this->assertSame('REFUNDED', $payment->status->value);
        $this->assertSame('REFUNDED', $note->status);

        $refundOutbox = OutboxMessage::query()
            ->where('aggregate_type', 'BILLING_REFUND')
            ->where('aggregate_id', $refund->refund_id)
            ->where('event_type', 'billing.lifecycle')
            ->firstOrFail();
        /** @var array<string, mixed> $refundPayload */
        $refundPayload = (array) $refundOutbox->payload;
        $networkOwnerId = DB::table('billing_accounts')
            ->join('networks', 'networks.network_id', '=', 'billing_accounts.network_id')
            ->where('billing_accounts.billing_account_id', $this->account->billing_account_id)
            ->value('networks.owner_id');
        $this->assertSame((int) $networkOwnerId, (int) $refundPayload['user_id']);
        $this->assertSame('BILLING_REFUND_COMPLETED', $refundPayload['notification_type']);
        $this->assertStringStartsWith('💸', $refundPayload['title']);
        $this->assertStringStartsWith('💸', $refundPayload['message']);

        $refundEntry = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_REFUND')
            ->where('source_id', $refund->refund_id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertLedgerEntryBalanced($refundEntry);
        $this->assertLedgerAmount($refundEntry, '2200', 'debit_minor', 1000);
        $this->assertLedgerAmount($refundEntry, $treasury->ledgerAccount->account_code, 'credit_minor', 1000);

        $reconciliation = app(BillingReconciliationService::class)->report((int) $this->currency->currency_id);
        /** @var array<int, array<string, mixed>> $currencyReports */
        $currencyReports = (array) $reconciliation['currencies'];
        $currencyReport = $currencyReports[0] ?? null;
        $this->assertIsArray($currencyReport);
        $this->assertSame('MATCH', $currencyReport['status'], json_encode($currencyReport, JSON_THROW_ON_ERROR));
        /** @var array<int, array<string, mixed>> $checks */
        $checks = (array) $currencyReport['checks'];
        $totalDifference = array_sum(array_map(
            static fn (array $check): int => (int) $check['difference_minor'],
            $checks
        ));
        $this->assertSame(0, $totalDifference);
    }


    public function test_refund_rolls_back_when_network_owner_cannot_be_notified(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $treasury = TreasuryAccount::query()->create([
            'account_code' => 'TR-ROLLBACK-'.Str::upper(Str::random(10)),
            'name' => 'Refund Rollback Treasury',
            'account_type' => 'CASH',
            'currency_id' => $this->currency->currency_id,
            'status' => 'ACTIVE',
        ]);

        $paymentService = app(BillingPaymentService::class);
        $payment = $paymentService->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            1000,
            'refund-rollback-payment-'.Str::uuid(),
            $this->actor
        );
        $payment = $paymentService->verify(
            (int) $payment->payment_id,
            (int) $treasury->treasury_account_id,
            $this->actor
        );

        $note = app(BillingCreditNoteService::class)->issue(
            (int) $this->invoice->invoice_id,
            [['invoice_line_id' => $this->line->invoice_line_id, 'amount_minor' => 1000]],
            'اختبار منع إتمام الاسترداد دون مالك صالح للإشعار',
            'refund-rollback-credit-note-'.Str::uuid(),
            $this->actor
        );

        DB::table('networks')
            ->where('network_id', $this->account->network_id)
            ->update(['owner_id' => 0]);

        $idempotencyKey = 'refund-missing-owner-'.Str::uuid();
        $refundLedgerCountBefore = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_REFUND')
            ->count();

        try {
            app(BillingRefundService::class)->refund(
                (int) $payment->payment_id,
                (int) $note->credit_note_id,
                (int) $treasury->treasury_account_id,
                1000,
                'يجب إلغاء العملية عند غياب مالك صالح',
                $idempotencyKey,
                $this->actor
            );

            $this->fail('A refund must not complete without a valid network-owner outbox event.');
        } catch (\LogicException $exception) {
            $this->assertSame('تعذر تحديد مالك الشبكة لتسجيل إشعار الاسترداد المالي.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('billing_refunds', ['idempotency_key' => $idempotencyKey]);
        $this->assertSame(
            $refundLedgerCountBefore,
            LedgerJournalEntry::query()->where('source_type', 'BILLING_REFUND')->count(),
            'A failed refund must not leave a financial ledger entry.'
        );

        $payment->refresh();
        $note->refresh();
        $this->assertSame('VERIFIED', $payment->status->value);
        $this->assertSame('ISSUED', $note->status);
        $this->assertSame(0, (int) $note->refunded_minor);
    }


    public function test_pending_payment_requests_are_reflected_in_invoice_list_and_detail_availability(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $payments = app(BillingPaymentService::class);

        foreach ([250, 300] as $index => $amount) {
            $payments->recordManualPayment(
                (int) $this->invoice->invoice_id,
                (int) $method->payment_method_id,
                $amount,
                'pending-exposure-'.$index.'-'.Str::uuid(),
                $this->actor
            );
        }

        $this->invoice->refresh();
        $this->assertSame(0, $this->invoice->paid_minor);
        $this->assertSame(1000, $this->invoice->remaining_minor);

        $selfService = app(BillingSelfService::class);
        $listedInvoice = $selfService
            ->invoices((int) $this->account->billing_account_id, [], $this->actor)
            ->items()[0];
        $listed = (new BillingInvoiceResource($listedInvoice))->resolve(request());

        $this->assertSame(550, $listed['pending_payment_minor']);
        $this->assertSame(450, $listed['available_to_submit_minor']);
        $this->assertTrue($listed['can_submit_payment']);

        $payments->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            450,
            'pending-exposure-final-'.Str::uuid(),
            $this->actor
        );

        $detail = $selfService->invoice((int) $this->invoice->invoice_id, $this->actor);
        $detailData = (new BillingInvoiceResource($detail))->resolve(request());

        $this->assertSame(1000, $detailData['pending_payment_minor']);
        $this->assertSame(0, $detailData['available_to_submit_minor']);
        $this->assertFalse($detailData['can_submit_payment']);
        $this->assertSame(0, $detailData['paid_minor']);
        $this->assertSame(1000, $detailData['remaining_minor']);

        $this->expectException(ValidationException::class);
        $payments->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            1,
            'pending-exposure-over-limit-'.Str::uuid(),
            $this->actor
        );
    }

    public function test_verifying_one_pending_payment_preserves_other_reservations_and_is_idempotent(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $payments = app(BillingPaymentService::class);
        $first = $payments->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            200,
            'verified-pending-first-'.Str::uuid(),
            $this->actor
        );
        $second = $payments->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            300,
            'verified-pending-second-'.Str::uuid(),
            $this->actor
        );

        $treasury = TreasuryAccount::query()->create([
            'account_code' => 'TR-PENDING-'.Str::upper(Str::random(10)),
            'name' => 'Pending Reservation Treasury',
            'account_type' => 'CASH',
            'currency_id' => $this->currency->currency_id,
            'status' => 'ACTIVE',
        ]);

        $verified = $payments->verify(
            (int) $first->payment_id,
            (int) $treasury->treasury_account_id,
            $this->actor
        );
        $payments->verify(
            (int) $first->payment_id,
            (int) $treasury->treasury_account_id,
            $this->actor
        );

        $this->invoice->refresh();
        $this->assertSame(200, $this->invoice->paid_minor);
        $this->assertSame(800, $this->invoice->remaining_minor);
        $this->assertSame('VERIFIED', $verified->status->value);

        $detail = app(BillingSelfService::class)->invoice((int) $this->invoice->invoice_id, $this->actor);
        $data = (new BillingInvoiceResource($detail))->resolve(request());
        $this->assertSame(300, $data['pending_payment_minor']);
        $this->assertSame(500, $data['available_to_submit_minor']);
        $this->assertTrue($data['can_submit_payment']);

        $payments->verify(
            (int) $second->payment_id,
            (int) $treasury->treasury_account_id,
            $this->actor
        );

        $this->invoice->refresh();
        $this->assertSame(500, $this->invoice->paid_minor);
        $this->assertSame(500, $this->invoice->remaining_minor);
        $this->assertSame(BillingInvoiceStatus::PARTIALLY_PAID, $this->invoice->status);

        $firstEntryQuery = LedgerJournalEntry::query()
            ->where('source_type', 'BILLING_PAYMENT')
            ->where('source_id', $first->payment_id);
        $this->assertSame(1, $firstEntryQuery->count());
        $this->assertLedgerEntryBalanced($firstEntryQuery->with('lines.account')->firstOrFail());
    }

    public function test_rejecting_a_pending_payment_releases_its_reserved_invoice_amount(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $payment = app(BillingPaymentService::class)->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            700,
            'rejected-reservation-'.Str::uuid(),
            $this->actor
        );

        $rejected = app(BillingPaymentService::class)->reject(
            (int) $payment->payment_id,
            $this->actor,
            'إثبات الدفع غير مطابق.'
        );

        $this->assertSame('REJECTED', $rejected->status->value);

        $detail = app(BillingSelfService::class)->invoice((int) $this->invoice->invoice_id, $this->actor);
        $data = (new BillingInvoiceResource($detail))->resolve(request());

        $this->assertSame(0, $data['pending_payment_minor']);
        $this->assertSame(1000, $data['available_to_submit_minor']);
        $this->assertTrue($data['can_submit_payment']);
        $this->assertSame(0, $data['paid_minor']);
        $this->assertSame(1000, $data['remaining_minor']);

        $this->assertDatabaseMissing('ledger_journal_entries', [
            'source_type' => 'BILLING_PAYMENT',
            'source_id' => $payment->payment_id,
        ]);
    }

    public function test_payment_idempotency_replays_the_same_record_and_rejects_changed_payloads(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);

        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $key = 'payment-idempotency-'.Str::uuid();
        $payments = app(BillingPaymentService::class);

        $first = $payments->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            400,
            $key,
            $this->actor
        );
        $replayed = $payments->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            400,
            $key,
            $this->actor
        );

        $this->assertSame($first->payment_id, $replayed->payment_id);
        $this->assertSame(
            1,
            BillingPayment::query()
                ->where('invoice_id', $this->invoice->invoice_id)
                ->count()
        );

        $this->expectException(ValidationException::class);
        $payments->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            401,
            $key,
            $this->actor
        );
    }

    public function test_platform_api_credit_note_and_refund_are_idempotent_and_reconciled(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);
        BillingRevenueRecognitionSchedule::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'invoice_line_id' => $this->line->invoice_line_id,
            'currency_id' => $this->currency->currency_id,
            'revenue_account_code' => '4100',
            'total_minor' => 1000,
            'recognized_minor' => 0,
            'service_start_on' => '2026-01-01',
            'service_end_on' => '2026-01-10',
            'status' => 'PENDING',
        ]);
        $method = BillingPaymentMethod::query()->where('method_key', 'CASH')->firstOrFail();
        $treasury = TreasuryAccount::query()->create([
            'account_code' => 'TR-API-'.Str::upper(Str::random(10)),
            'name' => 'Billing API Refund Treasury',
            'account_type' => 'CASH',
            'currency_id' => $this->currency->currency_id,
            'status' => 'ACTIVE',
        ]);
        app(BillingLedgerChartService::class)->ensureTreasuryAccount($treasury);
        $paymentService = app(BillingPaymentService::class);
        $payment = $paymentService->recordManualPayment((int) $this->invoice->invoice_id, (int) $method->payment_method_id, 1000, 'billing-api-refund-payment-'.Str::uuid(), $this->actor);
        $payment = $paymentService->verify((int) $payment->payment_id, (int) $treasury->treasury_account_id, $this->actor);
        $this->assertSame('VERIFIED', $payment->status->value);
        $this->assertSame(BillingInvoiceStatus::PAID, $this->invoice->fresh()->status);

        $headers = $this->createAuthenticatedSession($this->actor)['headers'];
        $creditPayload = [
            'lines' => [['invoice_line_id' => (int) $this->line->invoice_line_id, 'amount_minor' => 1000]],
            'reason' => 'Full service cancellation and customer refund',
            'idempotency_key' => 'billing-api-credit-note-'.Str::uuid(),
        ];
        $credit = $this->withHeaders($headers)->postJson('/api/v1/platform/billing/invoices/'.$this->invoice->invoice_id.'/credit-notes', $creditPayload);
        $credit->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.total_minor', 1000)->assertJsonPath('data.refundable_minor', 1000);
        $creditId = (int) $credit->json('data.credit_note_id');
        $this->withHeaders($headers)->postJson('/api/v1/platform/billing/invoices/'.$this->invoice->invoice_id.'/credit-notes', $creditPayload)
            ->assertCreated()->assertJsonPath('data.credit_note_id', $creditId);

        $refundPayload = [
            'credit_note_id' => $creditId,
            'treasury_account_id' => (int) $treasury->treasury_account_id,
            'amount_minor' => 1000,
            'reason' => 'Full customer refund through Billing API',
            'idempotency_key' => 'billing-api-refund-'.Str::uuid(),
        ];
        $refund = $this->withHeaders($headers)->postJson('/api/v1/platform/billing/payments/'.$payment->payment_id.'/refunds', $refundPayload);
        $refund->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.amount_minor', 1000)->assertJsonPath('data.status', 'COMPLETED');
        $refundId = (int) $refund->json('data.refund_id');
        $this->withHeaders($headers)->postJson('/api/v1/platform/billing/payments/'.$payment->payment_id.'/refunds', $refundPayload)
            ->assertCreated()->assertJsonPath('data.refund_id', $refundId);

        $this->assertSame(1, DB::table('billing_refunds')->where('idempotency_key', $refundPayload['idempotency_key'])->count());
        $this->assertSame(1, LedgerJournalEntry::query()->where('source_type', 'BILLING_REFUND')->where('source_id', $refundId)->count());
        $outbox = OutboxMessage::query()->where('aggregate_type', 'BILLING_REFUND')->where('aggregate_id', $refundId)->where('event_type', 'billing.lifecycle')->firstOrFail();
        /** @var array<string, mixed> $payload */
        $payload = (array) $outbox->payload;
        $this->assertSame('BILLING_REFUND_COMPLETED', $payload['notification_type']);
        $this->assertStringStartsWith('💸', $payload['title']);
        $this->assertStringStartsWith('💸', $payload['message']);

        $this->invoice->refresh();
        $this->assertSame(1000, (int) $this->invoice->paid_minor);
        $this->assertSame(1000, (int) $this->invoice->credited_minor);
        $this->assertSame(0, (int) $this->invoice->remaining_minor);
        $report = app(BillingReconciliationService::class)->report((int) $this->currency->currency_id);
        $currency = ((array) $report['currencies'])[0] ?? null;
        $this->assertIsArray($currency);
        $this->assertSame('MATCH', $currency['status'], json_encode($currency, JSON_THROW_ON_ERROR));
    }

    public function test_payment_methods_requiring_proof_reject_a_payment_without_proof(): void
    {
        $this->createInvoiceLine('SUBSCRIPTION', 1000, 0, 1000);

        $method = BillingPaymentMethod::query()
            ->where('is_active', true)
            ->where('requires_proof', true)
            ->whereHas('currencies', fn ($query) => $query
                ->where('currencies.currency_id', $this->currency->currency_id)
                ->where('billing_payment_method_currencies.is_active', true))
            ->firstOrFail();

        $this->expectException(ValidationException::class);
        app(BillingPaymentService::class)->recordManualPayment(
            (int) $this->invoice->invoice_id,
            (int) $method->payment_method_id,
            500,
            'payment-proof-required-'.Str::uuid(),
            $this->actor
        );
    }

    private function createInvoiceLine(string $type, int $subtotal, int $tax, int $total): void
    {
        $this->invoice = BillingInvoice::query()->create([
            'invoice_number' => 'INV-'.Str::upper(Str::random(16)),
            'billing_account_id' => $this->account->billing_account_id,
            'currency_id' => $this->currency->currency_id,
            'invoice_type' => 'STANDARD',
            'status' => BillingInvoiceStatus::DRAFT,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-10',
            'subtotal_minor' => $subtotal,
            'discount_minor' => 0,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'paid_minor' => 0,
            'credited_minor' => 0,
            'remaining_minor' => $total,
            'customer_snapshot' => ['billing_account_id' => $this->account->billing_account_id],
            'pricing_snapshot' => ['currency_code' => $this->currency->code],
            'created_by' => $this->actor->user_id,
        ]);

        $this->line = BillingInvoiceLine::query()->create([
            'invoice_id' => $this->invoice->invoice_id,
            'currency_id' => $this->currency->currency_id,
            'line_type' => $type,
            'description' => $type.' billing line',
            'quantity' => 1,
            'unit_amount_minor' => $subtotal,
            'subtotal_minor' => $subtotal,
            'discount_minor' => 0,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'source_snapshot' => ['billing_interval' => 'MONTH', 'interval_count' => 1],
        ]);

        $this->invoice->status = BillingInvoiceStatus::OPEN;
        $this->invoice->finalized_at = now('Asia/Aden');
        $this->invoice->save();

        $lines = [
            ['ledger_account_id' => $this->accounts[1100]->ledger_account_id, 'debit_minor' => $total, 'credit_minor' => 0],
        ];

        $accountCode = match ($type) {
            'SUBSCRIPTION' => '2100',
            'SETUP_FEE' => '4120',
            'METERED_USAGE' => '4110',
            default => throw new \LogicException('Unexpected test invoice line type.'),
        };

        if ($subtotal > 0) {
            $lines[] = ['ledger_account_id' => $this->accounts[$accountCode]->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $subtotal];
        }
        if ($tax > 0) {
            $lines[] = ['ledger_account_id' => $this->accounts[2300]->ledger_account_id, 'debit_minor' => 0, 'credit_minor' => $tax];
        }

        app(BillingLedgerService::class)->post(
            (int) $this->currency->currency_id,
            'Initial invoice ledger posting',
            'test-invoice-'.$this->invoice->invoice_id,
            $lines,
            $this->actor,
            'BILLING_INVOICE',
            (int) $this->invoice->invoice_id
        );
    }

    private function assertLedgerEntryBalanced(LedgerJournalEntry $entry): void
    {
        $this->assertSame((int) $entry->lines->sum('debit_minor'), (int) $entry->lines->sum('credit_minor'));
    }

    private function assertLedgerAmount(LedgerJournalEntry $entry, string $accountCode, string $side, int $expected): void
    {
        $actual = (int) $entry->lines
            ->filter(fn (LedgerJournalLine $line): bool => $line->account->account_code === $accountCode)
            ->sum($side);
        $this->assertSame($expected, $actual, "Unexpected {$side} balance for ledger account {$accountCode}.");
    }
}
