<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create idempotency_keys table
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('tenant_type', ['PLATFORM', 'NETWORK', 'POS']);
            $table->unsignedBigInteger('tenant_id');
            $table->string('endpoint', 255);
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED_TERMINAL', 'FAILED_RETRYABLE'])->default('PENDING');
            $table->integer('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->json('response_headers')->nullable();
            $table->unsignedBigInteger('fencing_token')->default(1);
            $table->timestamp('lease_expires_at');
            $table->timestamps();

            $table->unique(
                ['user_id', 'tenant_type', 'tenant_id', 'endpoint', 'idempotency_key'],
                'uq_idempotency_scope'
            );
            $table->index(['status', 'lease_expires_at'], 'idx_idempotency_status_lease');
        });

        // 2. Create outbox_messages table
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_uuid', 64)->unique('uq_outbox_uuid');
            $table->string('aggregate_type', 64);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type', 128);
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->enum('status', ['PENDING', 'PROCESSING', 'PUBLISHED', 'FAILED'])->default('PENDING');
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retries')->default(5);
            $table->string('lease_token', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['status', 'lease_expires_at', 'retry_count'], 'idx_outbox_claim');
            $table->index(['aggregate_type', 'aggregate_id'], 'idx_outbox_aggregate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
        Schema::dropIfExists('idempotency_keys');
    }
};
