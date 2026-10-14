<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\WalletOperationType;
use App\Jobs\SendSmsNotificationJob;
use App\Models\Network;
use App\Models\NetworkWallet;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected Network $network;

    protected NetworkWallet $networkWallet;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::firstOrFail();

        $this->session = UserSession::create([
            'user_id' => $this->user->user_id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'logged_at' => Carbon::now('Asia/Aden'),
            'last_activity_at' => Carbon::now('Asia/Aden'),
            'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
        ]);

        $this->headers = [
            'X-Session-Id' => (string) $this->session->session_id,
            'X-Bypass-Encryption' => 'true',
        ];

        $suffix = uniqid();

        $this->network = Network::create([
            'network_name' => 'Net Exp '.$suffix,
            'network_holder_name' => 'Holder Exp '.$suffix,
            'network_phone_number' => '77'.rand(1000000, 9999999),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->networkWallet = NetworkWallet::create([
            'network_id' => $this->network->network_id,
            'wallet_balance' => 100000.00,
            'status' => true,
        ]);
    }

    /**
     * اختبار تسجيل مصروف تشغيلي مع بنود ومرفق وخصم إلزامي من محفظة الشبكة.
     */
    public function test_can_create_expense_with_items_and_mandatory_wallet_deduction(): void
    {
        $file = UploadedFile::fake()->create('bill.pdf', 500, 'application/pdf');

        $payload = [
            'network_id' => $this->network->network_id,
            'expense_date' => now('Asia/Aden')->toDateString(),
            'items' => [
                ['item_statement' => 'إيجار برج الإرسال', 'item_amount' => 12000.00],
                ['item_statement' => 'صيانة كوابل فايبر', 'item_amount' => 8000.00],
            ],
            'attachments' => [$file],
        ];

        $response = $this->withHeaders($this->headers)->postJson('/api/v1/expenses', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expense_total_amount', 20000)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(1, 'data.attachments');

        $expenseId = (int) $response->json('data.expense_id');

        // 1. التحقق من خصم الرصيد من المحفظة (100,000 - 20,000 = 80,000)
        $this->networkWallet->refresh();
        $this->assertEquals(80000.00, (float) $this->networkWallet->wallet_balance);

        // 2. التحقق من تسجيل قيد الصرف في سجلات محفظة الشبكة
        $this->assertDatabaseHas('network_wallet_logs', [
            'wallet_id' => $this->networkWallet->wallet_id,
            'log_operation' => WalletOperationType::WITHDRAW->value,
            'log_amount' => 20000.00,
        ]);

        // 3. التحقق من وجود المصروف في قاعدة البيانات
        $this->assertDatabaseHas('expenses', [
            'expense_id' => $expenseId,
            'expense_total_amount' => 20000.00,
            'network_id' => $this->network->network_id,
        ]);
    }

    /**
     * اختبار جلب قائمة وتفاصيل المصروفات.
     */
    public function test_can_get_expenses_list_and_details(): void
    {
        $payload = [
            'network_id' => $this->network->network_id,
            'items' => [
                ['item_statement' => 'بترول للمولد', 'item_amount' => 5000.00],
            ],
        ];

        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/expenses', $payload);
        $expenseId = (int) $createRes->json('data.expense_id');

        // قائمة المصروفات
        $listRes = $this->withHeaders($this->headers)->getJson('/api/v1/expenses');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // تفاصيل المصروف
        $showRes = $this->withHeaders($this->headers)->getJson("/api/v1/expenses/{$expenseId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expense_id', $expenseId);
    }

    /**
     * اختبار تحديث المصروف وتسوية الفروقات المالية على المحفظة آلياً.
     */
    public function test_can_update_expense_and_adjust_wallet_difference(): void
    {
        // 1. إنشاء مصروف بقيمة 10,000 (الرصيد يصبح 90,000)
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/expenses', [
            'network_id' => $this->network->network_id,
            'items' => [
                ['item_statement' => 'تجديد اشتراك خدمة', 'item_amount' => 10000.00],
            ],
        ]);
        $expenseId = (int) $createRes->json('data.expense_id');

        $this->networkWallet->refresh();
        $this->assertEquals(90000.00, (float) $this->networkWallet->wallet_balance);

        // 2. تحديث المصروف ليصبح إجماليه 15,000 (زيادة 5,000 -> خصم إضافي)
        $updateRes = $this->withHeaders($this->headers)->putJson("/api/v1/expenses/{$expenseId}", [
            'items' => [
                ['item_statement' => 'تجديد اشتراك خدمة محدث', 'item_amount' => 15000.00],
            ],
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expense_total_amount', 15000);

        $this->networkWallet->refresh();
        $this->assertEquals(85000.00, (float) $this->networkWallet->wallet_balance);

        // 3. تحديث المصروف ليصبح إجماليه 7,000 (تخفيض 8,000 -> استرداد وإيداع)
        $updateRes2 = $this->withHeaders($this->headers)->putJson("/api/v1/expenses/{$expenseId}", [
            'items' => [
                ['item_statement' => 'تجديد اشتراك خدمة مخفض', 'item_amount' => 7000.00],
            ],
        ]);
        $updateRes2->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expense_total_amount', 7000);

        $this->networkWallet->refresh();
        $this->assertEquals(93000.00, (float) $this->networkWallet->wallet_balance);
    }

    /**
     * اختبار إضافة وحذف بند منفرد لمصروف قائم مع تسوية المحفظة.
     */
    public function test_can_add_and_delete_single_item_with_wallet_settlement(): void
    {
        // إنشاء مصروف بـ 10,000 (رصيد المحفظة 90,000)
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/expenses', [
            'network_id' => $this->network->network_id,
            'items' => [
                ['item_statement' => 'بند أساسي', 'item_amount' => 10000.00],
            ],
        ]);
        $expenseId = (int) $createRes->json('data.expense_id');

        // إضافة بند منفرد بـ 4,000 (يجب أن يُخصم من المحفظة ويصبح الرصيد 86,000)
        $addItemRes = $this->withHeaders($this->headers)->postJson("/api/v1/expenses/{$expenseId}/items", [
            'item_statement' => 'صيانة شاحن طاقة',
            'item_amount' => 4000.00,
        ]);
        $addItemRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item_amount', 4000);

        $this->networkWallet->refresh();
        $this->assertEquals(86000.00, (float) $this->networkWallet->wallet_balance);

        $itemId = (int) $addItemRes->json('data.item_id');

        // حذف البند المنفرد (يجب أن يُعاد 4,000 للمحفظة ويصبح الرصيد 90,000)
        $deleteItemRes = $this->withHeaders($this->headers)->deleteJson("/api/v1/expenses/{$expenseId}/items/{$itemId}");
        $deleteItemRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->networkWallet->refresh();
        $this->assertEquals(90000.00, (float) $this->networkWallet->wallet_balance);
    }

    /**
     * اختبار الحذف الناعم والاستعادة والحذف النهائي والعمليات المجمعة.
     */
    public function test_can_perform_soft_delete_restore_and_batch_operations(): void
    {
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/expenses', [
            'network_id' => $this->network->network_id,
            'items' => [
                ['item_statement' => 'مصروف تجريبي 1', 'item_amount' => 2000.00],
            ],
        ]);
        $expenseId = (int) $createRes->json('data.expense_id');

        // 1. حذف ناعم
        $deleteRes = $this->withHeaders($this->headers)->deleteJson("/api/v1/expenses/{$expenseId}");
        $deleteRes->assertStatus(200)->assertJsonPath('success', true);

        // 2. التحقق من وجوده في قائمة المحذوفات
        $trashedRes = $this->withHeaders($this->headers)->getJson('/api/v1/expenses/trashed');
        $trashedRes->assertStatus(200)->assertJsonPath('success', true);

        // 3. استعادة
        $restoreRes = $this->withHeaders($this->headers)->postJson("/api/v1/expenses/{$expenseId}/restore");
        $restoreRes->assertStatus(200)->assertJsonPath('success', true);

        // 4. حذف مجمع واستعادة وحذف نهائي
        $batchDelRes = $this->withHeaders($this->headers)->postJson('/api/v1/expenses/batch-delete', [
            'ids' => [$expenseId],
        ]);
        $batchDelRes->assertStatus(200)->assertJsonPath('success', true);

        $batchRestRes = $this->withHeaders($this->headers)->postJson('/api/v1/expenses/batch-restore', [
            'ids' => [$expenseId],
        ]);
        $batchRestRes->assertStatus(200)->assertJsonPath('success', true);

        $batchForceRes = $this->withHeaders($this->headers)->postJson('/api/v1/expenses/batch-force-delete', [
            'ids' => [$expenseId],
        ]);
        $batchForceRes->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseMissing('expenses', ['expense_id' => $expenseId]);
    }

    public function test_sms_notification_is_sent_to_network_manager_on_expense_creation_and_adjustment(): void
    {
        Queue::fake([SendSmsNotificationJob::class]);

        $payload = [
            'network_id' => $this->network->network_id,
            'items' => [
                ['item_statement' => 'إيجار موقع', 'item_amount' => 15000.00],
            ],
        ];

        // 1. إنشاء مصروف
        $createRes = $this->withHeaders($this->headers)->postJson('/api/v1/expenses', $payload);
        $createRes->assertStatus(201);

        $expectedCreateMsg = "شبكة {$this->network->network_name}: عليكم سند صرف (مصروف تشغيلي) بمبلغ 15,000.00YER رصيدكم 85,000.00YER";

        Queue::assertPushed(SendSmsNotificationJob::class, function ($job) use ($expectedCreateMsg) {
            return $job->phoneNumbers === $this->network->network_phone_number && $job->message === $expectedCreateMsg;
        });

        $expenseId = $createRes->json('data.expense_id');

        // 2. تعديل المصروف بالزيادة (من 15,000 إلى 20,000 -> فرق 5,000 خصم إضافي)
        $updateRes = $this->withHeaders($this->headers)->putJson("/api/v1/expenses/{$expenseId}", [
            'network_id' => $this->network->network_id,
            'items' => [
                ['item_statement' => 'إيجار موقع وصيانة', 'item_amount' => 20000.00],
            ],
        ]);
        $updateRes->assertStatus(200);

        $expectedUpdateMsg = "شبكة {$this->network->network_name}: عليكم سند صرف (تسوية زيادة مصروف) بمبلغ 5,000.00YER رصيدكم 80,000.00YER";

        Queue::assertPushed(SendSmsNotificationJob::class, function ($job) use ($expectedUpdateMsg) {
            return $job->phoneNumbers === $this->network->network_phone_number && $job->message === $expectedUpdateMsg;
        });
    }
}
