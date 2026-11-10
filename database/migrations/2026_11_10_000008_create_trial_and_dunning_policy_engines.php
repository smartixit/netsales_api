<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_trial_policies', function (Blueprint $table): void {
            $table->id('trial_policy_id');
            $table->string('policy_key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billing_trial_policy_versions', function (Blueprint $table): void {
            $table->id('trial_policy_version_id');
            $table->foreignId('trial_policy_id')->constrained('billing_trial_policies', 'trial_policy_id')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16)->default('DRAFT')->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedSmallInteger('duration_value')->default(14);
            $table->string('duration_unit', 8)->default('DAY');
            $table->string('start_trigger', 24)->default('PURCHASE');
            $table->string('redemption_scope', 24)->default('NETWORK');
            $table->string('eligibility_mode', 32)->default('NEW_BILLING_ACCOUNT');
            $table->boolean('requires_manual_approval')->default(false);
            $table->string('end_behavior', 24)->default('CREATE_INVOICE');
            $table->string('entitlement_mode', 24)->default('FULL_PLAN');
            $table->json('entitlement_config')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->unique(['trial_policy_id', 'version_number'], 'uq_trial_policy_version_number');
            $table->index(['trial_policy_id', 'status']);
        });

        Schema::table('billing_trial_policies', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'fk_trial_policy_current_version')
                ->references('trial_policy_version_id')
                ->on('billing_trial_policy_versions')
                ->nullOnDelete();
        });

        Schema::create('billing_dunning_policies', function (Blueprint $table): void {
            $table->id('dunning_policy_id');
            $table->string('policy_key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billing_dunning_policy_versions', function (Blueprint $table): void {
            $table->id('dunning_policy_version_id');
            $table->foreignId('dunning_policy_id')->constrained('billing_dunning_policies', 'dunning_policy_id')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16)->default('DRAFT')->index();
            $table->unsignedSmallInteger('grace_period_days')->default(7);
            $table->boolean('auto_suspend_after_grace')->default(true);
            $table->json('reminder_schedule_days');
            $table->json('pre_renewal_alert_days');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->unique(['dunning_policy_id', 'version_number'], 'uq_dunning_policy_version_number');
            $table->index(['dunning_policy_id', 'status']);
        });

        Schema::table('billing_dunning_policies', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'fk_dunning_policy_current_version')
                ->references('dunning_policy_version_id')
                ->on('billing_dunning_policy_versions')
                ->nullOnDelete();
        });

        Schema::table('billing_plan_versions', function (Blueprint $table): void {
            $table->foreignId('trial_policy_version_id')->nullable()
                ->after('status')
                ->constrained('billing_trial_policy_versions', 'trial_policy_version_id')
                ->restrictOnDelete();
            $table->foreignId('dunning_policy_version_id')->nullable()
                ->after('trial_policy_version_id')
                ->constrained('billing_dunning_policy_versions', 'dunning_policy_version_id')
                ->restrictOnDelete();
        });

        Schema::table('billing_prices', function (Blueprint $table): void {
            $table->foreignId('trial_policy_version_id')->nullable()
                ->after('tax_behavior')
                ->constrained('billing_trial_policy_versions', 'trial_policy_version_id')
                ->restrictOnDelete();
            $table->foreignId('dunning_policy_version_id')->nullable()
                ->after('trial_policy_version_id')
                ->constrained('billing_dunning_policy_versions', 'dunning_policy_version_id')
                ->restrictOnDelete();
        });

        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->foreignId('trial_policy_version_id')->nullable()
                ->after('price_id')
                ->constrained('billing_trial_policy_versions', 'trial_policy_version_id')
                ->restrictOnDelete();
            $table->foreignId('dunning_policy_version_id')->nullable()
                ->after('trial_policy_version_id')
                ->constrained('billing_dunning_policy_versions', 'dunning_policy_version_id')
                ->restrictOnDelete();
            $table->json('trial_policy_snapshot')->nullable()->after('terms_snapshot');
            $table->json('dunning_policy_snapshot')->nullable()->after('trial_policy_snapshot');
        });

        Schema::create('billing_trial_redemptions', function (Blueprint $table): void {
            $table->id('trial_redemption_id');
            $table->foreignId('trial_policy_id')->constrained('billing_trial_policies', 'trial_policy_id')->restrictOnDelete();
            $table->foreignId('trial_policy_version_id')->constrained('billing_trial_policy_versions', 'trial_policy_version_id')->restrictOnDelete();
            $table->foreignId('billing_subscription_id')->unique()->constrained('billing_subscriptions', 'billing_subscription_id')->restrictOnDelete();
            $table->foreignId('network_id')->constrained('networks', 'network_id')->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained('billing_accounts', 'billing_account_id')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('billing_products', 'product_id')->restrictOnDelete();
            $table->string('redemption_scope', 24);
            $table->string('redemption_subject_key', 120);
            $table->string('status', 24)->default('RESERVED')->index();
            $table->timestamp('reserved_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(
                ['trial_policy_id', 'redemption_scope', 'redemption_subject_key'],
                'uq_trial_policy_redemption_subject'
            );
            $table->index(['network_id', 'status']);
            $table->index(['billing_account_id', 'status']);
        });

        Schema::create('billing_trial_adjustments', function (Blueprint $table): void {
            $table->id('trial_adjustment_id');
            $table->foreignId('trial_redemption_id')->constrained('billing_trial_redemptions', 'trial_redemption_id')->restrictOnDelete();
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions', 'billing_subscription_id')->restrictOnDelete();
            $table->string('adjustment_type', 16);
            $table->timestamp('previous_trial_end')->nullable();
            $table->timestamp('new_trial_end')->nullable();
            $table->string('reason', 500);
            $table->foreignId('actor_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->index(['billing_subscription_id', 'occurred_at'], 'idx_bta_sub_occurred');
        });

        Schema::create('billing_subscription_alerts', function (Blueprint $table): void {
            $table->id('subscription_alert_id');
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions', 'billing_subscription_id')->restrictOnDelete();
            $table->foreignId('dunning_policy_version_id')->constrained('billing_dunning_policy_versions', 'dunning_policy_version_id')->restrictOnDelete();
            $table->timestamp('period_end');
            $table->unsignedSmallInteger('alert_days');
            $table->timestamp('scheduled_at')->index();
            $table->string('status', 16)->default('PENDING')->index();
            $table->timestamp('queued_at')->nullable();
            $table->string('message_uuid', 64)->nullable()->unique();
            $table->string('result_message', 255)->nullable();
            $table->timestamps();
            $table->unique(
                ['billing_subscription_id', 'period_end', 'alert_days'],
                'uq_billing_subscription_period_alert'
            );
        });

        // Policy and catalog records are owned by explicit seeders and Platform Billing workflows.
        // Migrations remain schema-focused and do not publish commercial or policy versions.

        Schema::table('billing_plan_versions', function (Blueprint $table): void {
            $table->dropColumn('trial_days');
        });

        Cache::forget('system_settings_cache_all');
    }

    public function down(): void
    {
        Schema::table('billing_plan_versions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('trial_days')->default(0)->after('status');
        });

        DB::table('billing_plan_versions')
            ->join('billing_trial_policy_versions', 'billing_plan_versions.trial_policy_version_id', '=', 'billing_trial_policy_versions.trial_policy_version_id')
            ->update([
                'billing_plan_versions.trial_days' => DB::raw(
                    "CASE WHEN billing_trial_policy_versions.is_enabled = 1 AND billing_trial_policy_versions.duration_unit = 'DAY' THEN billing_trial_policy_versions.duration_value ELSE 0 END"
                ),
            ]);

        Schema::table('billing_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dunning_policy_version_id');
            $table->dropConstrainedForeignId('trial_policy_version_id');
            $table->dropColumn(['trial_policy_snapshot', 'dunning_policy_snapshot']);
        });

        Schema::table('billing_prices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dunning_policy_version_id');
            $table->dropConstrainedForeignId('trial_policy_version_id');
        });

        Schema::table('billing_plan_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dunning_policy_version_id');
            $table->dropConstrainedForeignId('trial_policy_version_id');
        });

        Schema::dropIfExists('billing_subscription_alerts');
        Schema::dropIfExists('billing_trial_adjustments');
        Schema::dropIfExists('billing_trial_redemptions');

        Schema::table('billing_dunning_policies', function (Blueprint $table): void {
            $table->dropForeign('fk_dunning_policy_current_version');
        });
        Schema::dropIfExists('billing_dunning_policy_versions');
        Schema::dropIfExists('billing_dunning_policies');

        Schema::table('billing_trial_policies', function (Blueprint $table): void {
            $table->dropForeign('fk_trial_policy_current_version');
        });
        Schema::dropIfExists('billing_trial_policy_versions');
        Schema::dropIfExists('billing_trial_policies');
    }
};
