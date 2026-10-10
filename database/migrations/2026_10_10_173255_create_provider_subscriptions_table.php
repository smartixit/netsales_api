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
        Schema::create('provider_subscriptions', function (Blueprint $table) {
            $table->id('subscription_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('plan_id')
                ->constrained('subscription_plans', 'plan_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamp('subscription_start_date')->useCurrent();
            $table->timestamp('subscription_end_date')->nullable();
            $table->string('status', 20)->default('ACTIVE')->index('idx_provider_subscriptions_status');

            $table->foreignId('created_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes()->index('idx_provider_subscriptions_deleted_at');

            $table->index('network_id', 'idx_provider_subscriptions_network_id');
            $table->index('plan_id', 'idx_provider_subscriptions_plan_id');
            $table->index('created_by', 'idx_provider_subscriptions_created_by');
            $table->index('subscription_start_date', 'idx_provider_subscriptions_start_date');
            $table->index('subscription_end_date', 'idx_provider_subscriptions_end_date');
            $table->index(['network_id', 'plan_id'], 'idx_provider_subscriptions_network_plan');
            $table->index(['network_id', 'status'], 'idx_provider_subscriptions_network_status');
            $table->index(['plan_id', 'status'], 'idx_provider_subscriptions_plan_status');
            $table->index(['network_id', 'plan_id', 'status'], 'idx_provider_subscriptions_network_plan_status');
            $table->index(['network_id', 'plan_id', 'status', 'created_by'], 'idx_provider_subscriptions_network_plan_status_created_by');
            // $table->index(['network_id', 'plan_id', 'status', 'created_by', 'subscription_start_date'], 'idx_provider_subscriptions_network_plan_status_created_by_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_subscriptions');
    }
};
