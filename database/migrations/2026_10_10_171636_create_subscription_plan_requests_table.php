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
        Schema::create('subscription_plan_requests', function (Blueprint $table) {
            $table->id('request_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('plan_id')
                ->constrained('subscription_plans', 'plan_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('requested_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('request_uuid', 50)->unique('uq_subscription_plan_requests_request_uuid');
            $table->decimal('request_total_amount', 15, 2)->default(0.00);
            $table->string('request_payment_type', 20)->default('CASH')->index('idx_subscription_plan_requests_request_payment_type'); // CASH, BANK

            $table->foreignId('account_id')
                ->nullable()
                ->constrained('bank_accounts', 'account_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('transaction_reference', 50)->nullable();
            $table->text('transaction_receipt')->nullable();
            $table->string('status', 20)->default('PENDING')->index('idx_subscription_plan_requests_status');
            $table->string('rejection_reason', 150)->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('request_note', 150)->nullable();
            $table->timestamp('review_date')->nullable();

            $table->timestamps();
            $table->softDeletes()->index('idx_subscription_plan_requests_deleted_at');

            $table->index('network_id', 'idx_subscription_plan_requests_network_id');
            $table->index('plan_id', 'idx_subscription_plan_requests_plan_id');
            $table->index('requested_by', 'idx_subscription_plan_requests_requested_by');
            $table->index('reviewed_by', 'idx_subscription_plan_requests_reviewed_by');
            $table->index(['network_id', 'plan_id'], 'idx_subscription_plan_requests_network_plan');
            $table->index(['network_id', 'requested_by'], 'idx_subscription_plan_requests_network_requested');
            $table->index(['network_id', 'reviewed_by'], 'idx_subscription_plan_requests_network_reviewed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_requests');
    }
};
