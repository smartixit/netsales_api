<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->id('billing_subscription_id');
            $table->foreignId('billing_account_id')->constrained('billing_accounts', 'billing_account_id')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('billing_plans', 'plan_id')->restrictOnDelete();
            $table->foreignId('plan_version_id')->constrained('billing_plan_versions', 'plan_version_id')->restrictOnDelete();
            $table->foreignId('price_id')->constrained('billing_prices', 'price_id')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->string('status', 20)->default('INCOMPLETE')->index();
            $table->string('collection_method', 16)->default('MANUAL');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable()->index();
            $table->timestamp('trial_start')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false)->index();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->json('terms_snapshot');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['billing_account_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('billing_subscription_items', function (Blueprint $table): void {
            $table->id('subscription_item_id');
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions', 'billing_subscription_id')->cascadeOnDelete();
            $table->foreignId('plan_feature_id')->nullable()->constrained('billing_plan_features', 'plan_feature_id')->restrictOnDelete();
            $table->string('item_type', 16)->default('PLAN');
            $table->string('description', 160);
            $table->decimal('quantity', 18, 6)->default(1);
            $table->unsignedBigInteger('unit_amount_minor')->default(0);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->json('terms_snapshot')->nullable();
            $table->timestamps();
            $table->index(['billing_subscription_id', 'item_type'], 'idx_bsi_sub_itemtype');
        });

        Schema::create('billing_subscription_events', function (Blueprint $table): void {
            $table->id('event_id');
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions', 'billing_subscription_id')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->index(['billing_subscription_id', 'occurred_at'], 'idx_bse_sub_occurred');
            $table->index(['event_type', 'occurred_at']);
        });

        Schema::create('billing_usage_meters', function (Blueprint $table): void {
            $table->id('meter_id');
            $table->string('meter_key', 100)->unique();
            $table->string('name', 120);
            $table->string('unit', 40);
            $table->string('aggregation', 16)->default('SUM');
            $table->string('reset_period', 20)->nullable();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('billing_usage_events', function (Blueprint $table): void {
            $table->id('usage_event_id');
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions', 'billing_subscription_id')->restrictOnDelete();
            $table->foreignId('meter_id')->constrained('billing_usage_meters', 'meter_id')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->decimal('quantity', 24, 6);
            $table->timestamp('occurred_at');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('dimensions')->nullable();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->unique(['billing_subscription_id', 'meter_id', 'idempotency_key'], 'uq_billing_usage_idempotency');
            $table->index(['billing_subscription_id', 'meter_id', 'occurred_at'], 'idx_bue_sub_meter_time');
            $table->index(['meter_id', 'period_start', 'period_end']);
        });

        Schema::create('billing_usage_aggregates', function (Blueprint $table): void {
            $table->id('usage_aggregate_id');
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions', 'billing_subscription_id')->restrictOnDelete();
            $table->foreignId('meter_id')->constrained('billing_usage_meters', 'meter_id')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('quantity', 24, 6)->default(0);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->foreignId('currency_id')->nullable()->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
            $table->unique(['billing_subscription_id', 'meter_id', 'period_start', 'period_end'], 'uq_billing_usage_aggregate_period');
        });

        Schema::create('billing_usage_reservations', function (Blueprint $table): void {
            $table->id('reservation_id');
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions', 'billing_subscription_id')->restrictOnDelete();
            $table->foreignId('meter_id')->constrained('billing_usage_meters', 'meter_id')->restrictOnDelete();
            $table->string('reservation_key', 128)->unique();
            $table->decimal('quantity', 24, 6);
            $table->string('status', 16)->default('RESERVED')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            $table->index(['billing_subscription_id', 'meter_id', 'status'], 'idx_bur_sub_meter_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_usage_reservations');
        Schema::dropIfExists('billing_usage_aggregates');
        Schema::dropIfExists('billing_usage_events');
        Schema::dropIfExists('billing_usage_meters');
        Schema::dropIfExists('billing_subscription_events');
        Schema::dropIfExists('billing_subscription_items');
        Schema::dropIfExists('billing_subscriptions');
    }
};