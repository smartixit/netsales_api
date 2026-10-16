<?php

namespace Tests\Feature;

use App\Enums\NetworkStatus;
use App\Enums\PosStatus;
use App\Enums\UserStatus;
use App\Models\Network;
use App\Models\Pos;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SupportTicketSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected Network $network;

    protected Pos $pos;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

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

         $posUser = User::create([
            'role_id' => $this->user->role_id,
            'user_full_name' => 'POS Agent '.$suffix,
            'user_phone_number' => '73'.rand(1000000, 9999999),
            'user_password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $this->network = Network::create([
            'owner_id' => $this->user->user_id,
            'network_name' => 'تيكت نت '.$suffix,
            'network_phone_number' => '733111222',
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => NetworkStatus::ACTIVE,
        ]);

        $this->pos = Pos::create([
            'owner_id' => $this->user->user_id,
            'pos_name' => 'نقطة بيع تيكت '.$suffix,
            'pos_phone_number' => $posUser->user_phone_number,
            'pos_device_uid' => 'POS-DEV-'.uniqid(),
            'governorate_id' => 1,
            'directorate_id' => 1,
            'status' => PosStatus::ACTIVE,
        ]);
    }

    /**
     * اختبار إنشاء تذكرة دعم فني جديدة مع رسالتها الافتتاحية.
     */
    public function test_can_create_support_ticket_with_initial_message(): void
    {
        $response = $this->withHeaders($this->headers)->postJson('/api/v1/support-tickets', [
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'ticket_subject' => 'مشكلة في طباعة كروت الشبكة',
            'message_body' => 'يرجى مراجعة مشكلة توقف طابعة كروت الباقة لدى نقطة البيع.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket_subject', 'مشكلة في طباعة كروت الشبكة')
            ->assertJsonPath('data.network_id', $this->network->network_id)
            ->assertJsonPath('data.pos_id', $this->pos->pos_id);

        $ticketId = (int) $response->json('data.ticket_id');

        $this->assertDatabaseHas('support_tickets', [
            'ticket_id' => $ticketId,
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
        ]);

        $this->assertDatabaseHas('support_ticket_messages', [
            'ticket_id' => $ticketId,
            'sent_by' => $this->user->user_id,
            'message_body' => 'يرجى مراجعة مشكلة توقف طابعة كروت الباقة لدى نقطة البيع.',
            'is_read' => 0,
        ]);
    }

    /**
     * اختبار استرجاع قائمة التذاكر وتفاصيل تذكرة معينة ورسائلها.
     */
    public function test_can_list_and_get_support_ticket_details(): void
    {
        $ticket = SupportTicket::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'ticket_subject' => 'طلب مساعدة فنية تجريبية',
        ]);

        SupportTicketMessage::create([
            'ticket_id' => $ticket->ticket_id,
            'sent_by' => $this->user->user_id,
            'message_body' => 'الرسالة الأولى للتذكرة',
            'sent_at' => now('Asia/Aden'),
            'is_read' => false,
        ]);

        // جلب قائمة التذاكر
        $listResponse = $this->withHeaders($this->headers)->getJson("/api/v1/support-tickets?network_id={$this->network->network_id}");
        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // جلب تفاصيل التذكرة
        $showResponse = $this->withHeaders($this->headers)->getJson("/api/v1/support-tickets/{$ticket->ticket_id}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket_id', $ticket->ticket_id)
            ->assertJsonPath('data.ticket_subject', 'طلب مساعدة فنية تجريبية');

        // جلب رسائل التذكرة
        $messagesResponse = $this->withHeaders($this->headers)->getJson("/api/v1/support-tickets/{$ticket->ticket_id}/messages");
        $messagesResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * اختبار إضافة رد / رسالة جديدة إلى التذكرة وتمييزها كمقروءة.
     */
    public function test_can_add_message_and_mark_as_read(): void
    {
        $ticket = SupportTicket::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'ticket_subject' => 'استفسار عن رصيد المحفظة',
        ]);

        // إضافة رسالة
        $addResponse = $this->withHeaders($this->headers)->postJson("/api/v1/support-tickets/{$ticket->ticket_id}/messages", [
            'message_body' => 'تم حل المشكلة وتغذية المحفظة بنجاح، شكراً لكم.',
        ]);

        $addResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message_body', 'تم حل المشكلة وتغذية المحفظة بنجاح، شكراً لكم.')
            ->assertJsonPath('data.ticket_id', $ticket->ticket_id);

        $messageId = (int) $addResponse->json('data.message_id');

        // إنشاء مستخدم آخر نشط لمحاكاة قراءة الرسائل الواردة
        $otherUser = User::where('user_id', '!=', $this->user->user_id)
            ->where('status', 'ACTIVE')
            ->first();
        if ($otherUser) {
            $otherSession = UserSession::create([
                'user_id' => $otherUser->user_id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'logged_at' => Carbon::now('Asia/Aden'),
                'last_activity_at' => Carbon::now('Asia/Aden'),
                'expires_at' => Carbon::now('Asia/Aden')->addHours(24),
            ]);

            $readResponse = $this->withHeaders([
                'X-Session-Id' => (string) $otherSession->session_id,
                'X-Bypass-Encryption' => 'true',
            ])->postJson("/api/v1/support-tickets/{$ticket->ticket_id}/read");

            $readResponse->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.marked_read_count', 1);

            $this->assertDatabaseHas('support_ticket_messages', [
                'message_id' => $messageId,
                'is_read' => 1,
            ]);
        }
    }

    /**
     * اختبار تحديث موضوع التذكرة.
     */
    public function test_can_update_support_ticket(): void
    {
        $ticket = SupportTicket::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'ticket_subject' => 'موضوع أولي قبل التعديل',
        ]);

        $updateResponse = $this->withHeaders($this->headers)->putJson("/api/v1/support-tickets/{$ticket->ticket_id}", [
            'ticket_subject' => 'موضوع معدل ومحدث',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ticket_subject', 'موضوع معدل ومحدث');

        $this->assertDatabaseHas('support_tickets', [
            'ticket_id' => $ticket->ticket_id,
            'ticket_subject' => 'موضوع معدل ومحدث',
        ]);
    }

    /**
     * اختبار الحذف الناعم والاستعادة والحذف النهائي والعمليات المجمعة.
     */
    public function test_can_perform_soft_delete_restore_and_batch_operations(): void
    {
        $ticket = SupportTicket::create([
            'network_id' => $this->network->network_id,
            'pos_id' => $this->pos->pos_id,
            'ticket_subject' => 'تذكرة قابلة للحذف والاستعادة',
        ]);

        // 1. الحذف الناعم الفردي
        $deleteResponse = $this->withHeaders($this->headers)->deleteJson("/api/v1/support-tickets/{$ticket->ticket_id}");
        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('support_tickets', ['ticket_id' => $ticket->ticket_id]);

        // 2. الاسترجاع الفردي
        $restoreResponse = $this->withHeaders($this->headers)->postJson("/api/v1/support-tickets/{$ticket->ticket_id}/restore");
        $restoreResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotSoftDeleted('support_tickets', ['ticket_id' => $ticket->ticket_id]);

        // 3. الحذف المجمع
        $batchDeleteResponse = $this->withHeaders($this->headers)->postJson('/api/v1/support-tickets/batch-delete', [
            'ids' => [$ticket->ticket_id],
        ]);
        $batchDeleteResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 1);

        $this->assertSoftDeleted('support_tickets', ['ticket_id' => $ticket->ticket_id]);

        // 4. الاستعادة المجمعة
        $batchRestoreResponse = $this->withHeaders($this->headers)->postJson('/api/v1/support-tickets/batch-restore', [
            'ids' => [$ticket->ticket_id],
        ]);
        $batchRestoreResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.restored_count', 1);

        $this->assertNotSoftDeleted('support_tickets', ['ticket_id' => $ticket->ticket_id]);

        // 5. الحذف النهائي المجمع بعد الحذف الناعم
        $this->withHeaders($this->headers)->postJson('/api/v1/support-tickets/batch-delete', [
            'ids' => [$ticket->ticket_id],
        ]);

        $batchForceDeleteResponse = $this->withHeaders($this->headers)->postJson('/api/v1/support-tickets/batch-force-delete', [
            'ids' => [$ticket->ticket_id],
        ]);
        $batchForceDeleteResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.force_deleted_count', 1);

        $this->assertDatabaseMissing('support_tickets', ['ticket_id' => $ticket->ticket_id]);
    }
}
