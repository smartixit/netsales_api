<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IdempotencyService;
use App\Services\OutboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdempotencyAndOutboxIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected IdempotencyService $idempotencyService;

    protected OutboxService $outboxService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();
        $this->idempotencyService = app(IdempotencyService::class);
        $this->outboxService = app(OutboxService::class);
    }

    /**
     * Test 1: Canonical request hashing is identical regardless of payload key order.
     */
    public function test_canonical_hash_is_order_insensitive(): void
    {
        $payload1 = ['amount' => 100, 'note' => 'test', 'details' => ['b' => 2, 'a' => 1]];
        $payload2 = ['note' => 'test', 'details' => ['a' => 1, 'b' => 2], 'amount' => 100];

        $hash1 = $this->idempotencyService->computeCanonicalHash('/api/v1/test', $payload1);
        $hash2 = $this->idempotencyService->computeCanonicalHash('/api/v1/test', $payload2);

        $this->assertEquals($hash1, $hash2);
    }

    /**
     * Test 2: Two-phase reservation and completion lifecycle.
     */
    public function test_reservation_and_completion_lifecycle(): void
    {
        $key = 'test-key-'.uniqid();
        $endpoint = '/api/v1/sale-invoices';
        $payload = ['pos_id' => 1, 'amount' => 5000];
        $hash = $this->idempotencyService->computeCanonicalHash($endpoint, $payload);

        // 1. Initial reservation
        $res = $this->idempotencyService->reserve(
            $this->user->user_id,
            'POS',
            1,
            $endpoint,
            $key,
            $hash,
            30
        );

        $this->assertEquals('RESERVED', $res['status']);
        $this->assertEquals(1, $res['fencing_token']);
        $recordId = $res['id'];

        // 2. Concurrent/duplicate attempt while in flight returns IN_FLIGHT
        $concurrentRes = $this->idempotencyService->reserve(
            $this->user->user_id,
            'POS',
            1,
            $endpoint,
            $key,
            $hash,
            30
        );
        $this->assertEquals('IN_FLIGHT', $concurrentRes['status']);

        // 3. Complete the request
        $completed = $this->idempotencyService->complete(
            $recordId,
            $res['fencing_token'],
            201,
            ['invoice_id' => 999, 'status' => 'success']
        );
        $this->assertTrue($completed);

        // 4. Repeated request returns COMPLETED with cached response
        $replayRes = $this->idempotencyService->reserve(
            $this->user->user_id,
            'POS',
            1,
            $endpoint,
            $key,
            $hash,
            30
        );

        $this->assertEquals('COMPLETED', $replayRes['status']);
        $this->assertEquals(201, $replayRes['response_code']);
        $this->assertEquals(999, $replayRes['response_body']['invoice_id']);
    }

    /**
     * Test 3: Reusing idempotency key with tampered payload is rejected.
     */
    public function test_reusing_key_with_different_payload_throws_validation_exception(): void
    {
        $key = 'tamper-key-'.uniqid();
        $endpoint = '/api/v1/requests';
        $payloadA = ['package_id' => 1, 'count' => 10];
        $payloadB = ['package_id' => 1, 'count' => 999]; // Tampered!

        $hashA = $this->idempotencyService->computeCanonicalHash($endpoint, $payloadA);
        $hashB = $this->idempotencyService->computeCanonicalHash($endpoint, $payloadB);

        $this->idempotencyService->reserve(
            $this->user->user_id,
            'NETWORK',
            10,
            $endpoint,
            $key,
            $hashA,
            30
        );

        $this->expectException(ValidationException::class);

        $this->idempotencyService->reserve(
            $this->user->user_id,
            'NETWORK',
            10,
            $endpoint,
            $key,
            $hashB,
            30
        );
    }

    /**
     * Test 4: Expired lease takeover increments fencing token, and stale worker is rejected.
     */
    public function test_lease_takeover_and_stale_worker_rejection(): void
    {
        $key = 'lease-key-'.uniqid();
        $endpoint = '/api/v1/pos-wallets/transfer';
        $payload = ['amount' => 500];
        $hash = $this->idempotencyService->computeCanonicalHash($endpoint, $payload);

        // 1. Initial reservation with expired lease (0 seconds)
        $res1 = $this->idempotencyService->reserve(
            $this->user->user_id,
            'NETWORK',
            5,
            $endpoint,
            $key,
            $hash,
            -1 // Already expired!
        );
        $this->assertEquals(1, $res1['fencing_token']);

        // 2. Worker 2 takes over the expired lease
        $res2 = $this->idempotencyService->reserve(
            $this->user->user_id,
            'NETWORK',
            5,
            $endpoint,
            $key,
            $hash,
            60
        );

        $this->assertEquals('RESERVED', $res2['status']);
        $this->assertEquals(2, $res2['fencing_token']); // Incremented fencing token!

        // 3. Stale Worker 1 attempts to complete with fencing_token = 1: MUST FAIL (0 affected rows)
        $staleCompleted = $this->idempotencyService->complete(
            $res1['id'],
            1, // Stale token!
            200,
            ['stale' => true]
        );
        $this->assertFalse($staleCompleted);

        // 4. Worker 2 completes with valid fencing_token = 2: MUST SUCCEED
        $validCompleted = $this->idempotencyService->complete(
            $res2['id'],
            2,
            200,
            ['stale' => false]
        );
        $this->assertTrue($validCompleted);
    }

    /**
     * Test 5: Outbox recording and lease claiming lifecycle.
     */
    public function test_outbox_message_lifecycle(): void
    {
        // 1. Record an outbox event
        $msg = $this->outboxService->record(
            'SaleInvoice',
            101,
            'INVOICE_GENERATED',
            ['total' => 25000, 'network_id' => 1]
        );

        $this->assertNotNull($msg->id);
        $this->assertEquals('PENDING', $msg->status);

        // 2. Claim batch
        $batch = $this->outboxService->claimBatch(10, 60);
        $claimed = $batch->firstWhere('id', $msg->id);

        $this->assertNotNull($claimed);
        $this->assertEquals('PROCESSING', $claimed->status);
        $this->assertNotNull($claimed->lease_token);

        // 3. Mark published
        $published = $this->outboxService->markPublished($claimed->id, $claimed->lease_token);
        $this->assertTrue($published);

        $msg->refresh();
        $this->assertEquals('PUBLISHED', $msg->status);
    }

    /**
     * Test 6: Outbox retry and terminal failure.
     */
    public function test_outbox_retry_and_terminal_failure(): void
    {
        $msg = $this->outboxService->record(
            'Transfer',
            55,
            'TRANSFER_COMPLETED',
            ['amount' => 1000]
        );

        $batch = $this->outboxService->claimBatch(10, 60);
        $claimed = $batch->firstWhere('id', $msg->id);

        // Fail 1st time
        $this->outboxService->markFailed($claimed->id, $claimed->lease_token, 'Network timeout');

        $msg->refresh();
        $this->assertEquals('PENDING', $msg->status);
        $this->assertEquals(1, $msg->retry_count);
        $this->assertEquals('Network timeout', $msg->last_error);

        // Simulate reaching max retries
        $msg->update(['retry_count' => 4]);

        $batch2 = $this->outboxService->claimBatch(10, 60);
        $claimed2 = $batch2->firstWhere('id', $msg->id);

        $this->outboxService->markFailed($claimed2->id, $claimed2->lease_token, 'Final fatal error');

        $msg->refresh();
        $this->assertEquals('FAILED', $msg->status);
        $this->assertEquals(5, $msg->retry_count);
    }
}
