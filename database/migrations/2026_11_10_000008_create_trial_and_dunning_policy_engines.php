<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->index(['billing_subscription_id', 'occurred_at']);
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

        // Preserve the behavior of existing plan versions before replacing trial_days.
        $now = now('Asia/Aden');
        $defaultTrialPolicyId = DB::table('billing_trial_policies')->insertGetId([
            'policy_key' => 'platform-default',
            'name' => 'سياسة التجربة الافتراضية للمنصة',
            'description' => 'السياسة الافتراضية التي تطبق على نسخ الخطط والأسعار التي لا تعيّن سياسة خاصة.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'trial_policy_id');

        $defaultTrialVersionId = DB::table('billing_trial_policy_versions')->insertGetId([
            'trial_policy_id' => $defaultTrialPolicyId,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'is_enabled' => true,
            'duration_value' => 14,
            'duration_unit' => 'DAY',
            'start_trigger' => 'PURCHASE',
            'redemption_scope' => 'PRODUCT',
            'eligibility_mode' => 'NEW_BILLING_ACCOUNT',
            'requires_manual_approval' => false,
            'end_behavior' => 'CREATE_INVOICE',
            'entitlement_mode' => 'FULL_PLAN',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'trial_policy_version_id');

        DB::table('billing_trial_policies')
            ->where('trial_policy_id', $defaultTrialPolicyId)
            ->update(['current_version_id' => $defaultTrialVersionId]);

        $noTrialPolicyId = DB::table('billing_trial_policies')->insertGetId([
            'policy_key' => 'legacy-no-trial',
            'name' => 'سياسة بدون تجربة',
            'description' => 'سياسة توافقية للخطط القديمة التي كانت trial_days فيها صفراً.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'trial_policy_id');

        $noTrialVersionId = DB::table('billing_trial_policy_versions')->insertGetId([
            'trial_policy_id' => $noTrialPolicyId,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'is_enabled' => false,
            'duration_value' => 0,
            'duration_unit' => 'DAY',
            'start_trigger' => 'PURCHASE',
            'redemption_scope' => 'PRODUCT',
            'eligibility_mode' => 'ANY',
            'requires_manual_approval' => false,
            'end_behavior' => 'CREATE_INVOICE',
            'entitlement_mode' => 'FULL_PLAN',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'trial_policy_version_id');

        DB::table('billing_trial_policies')
            ->where('trial_policy_id', $noTrialPolicyId)
            ->update(['current_version_id' => $noTrialVersionId]);

        $trialDaysValues = DB::table('billing_plan_versions')
            ->select('trial_days')
            ->distinct()
            ->orderBy('trial_days')
            ->pluck('trial_days');

        foreach ($trialDaysValues as $daysValue) {
            $days = (int) $daysValue;
            if ($days === 0) {
                DB::table('billing_plan_versions')
                    ->where('trial_days', 0)
                    ->update(['trial_policy_version_id' => $noTrialVersionId]);
                continue;
            }

            if ($days === 14) {
                DB::table('billing_plan_versions')
                    ->where('trial_days', 14)
                    ->update(['trial_policy_version_id' => $defaultTrialVersionId]);
                continue;
            }

            $legacyPolicyId = DB::table('billing_trial_policies')->insertGetId([
                'policy_key' => 'legacy-plan-trial-'.$days.'-days',
                'name' => 'سياسة تجربة قديمة - '.$days.' يوم',
                'description' => 'نسخة مرحّلة من trial_days للحفاظ على الشروط التجارية للخطط الموجودة.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'trial_policy_id');

            $legacyVersionId = DB::table('billing_trial_policy_versions')->insertGetId([
                'trial_policy_id' => $legacyPolicyId,
                'version_number' => 1,
                'status' => 'PUBLISHED',
                'is_enabled' => true,
                'duration_value' => min($days, 365),
                'duration_unit' => 'DAY',
                'start_trigger' => 'PURCHASE',
                'redemption_scope' => 'PRODUCT',
                'eligibility_mode' => 'ANY',
                'requires_manual_approval' => false,
                'end_behavior' => 'CREATE_INVOICE',
                'entitlement_mode' => 'FULL_PLAN',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'trial_policy_version_id');

            DB::table('billing_trial_policies')
                ->where('trial_policy_id', $legacyPolicyId)
                ->update(['current_version_id' => $legacyVersionId]);

            DB::table('billing_plan_versions')
                ->where('trial_days', $days)
                ->update(['trial_policy_version_id' => $legacyVersionId]);
        }

        $defaultDunningPolicyId = DB::table('billing_dunning_policies')->insertGetId([
            'policy_key' => 'platform-default',
            'name' => 'سياسة التحصيل الافتراضية للمنصة',
            'description' => 'فترة السماح والتذكيرات وتعليق الاشتراك بعد تأخر السداد.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'dunning_policy_id');

        $defaultDunningVersionId = DB::table('billing_dunning_policy_versions')->insertGetId([
            'dunning_policy_id' => $defaultDunningPolicyId,
            'version_number' => 1,
            'status' => 'PUBLISHED',
            'grace_period_days' => 7,
            'auto_suspend_after_grace' => true,
            'reminder_schedule_days' => json_encode([0, 1, 3, 7], JSON_THROW_ON_ERROR),
            'pre_renewal_alert_days' => json_encode([3], JSON_THROW_ON_ERROR),
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'dunning_policy_version_id');

        DB::table('billing_dunning_policies')
            ->where('dunning_policy_id', $defaultDunningPolicyId)
            ->update(['current_version_id' => $defaultDunningVersionId]);

        DB::table('billing_plan_versions')->whereNull('dunning_policy_version_id')
            ->update(['dunning_policy_version_id' => $defaultDunningVersionId]);

        DB::table('billing_subscriptions')->orderBy('billing_subscription_id')->get()->each(
            function (object $subscription) use ($defaultDunningVersionId): void {
                $planVersion = DB::table('billing_plan_versions')
                    ->where('plan_version_id', $subscription->plan_version_id)
                    ->first();

                DB::table('billing_subscriptions')
                    ->where('billing_subscription_id', $subscription->billing_subscription_id)
                    ->update([
                        'trial_policy_version_id' => $planVersion?->trial_policy_version_id,
                        'dunning_policy_version_id' => $planVersion?->dunning_policy_version_id ?? $defaultDunningVersionId,
                    ]);
            }
        );

        Schema::table('billing_plan_versions', function (Blueprint $table): void {
            $table->dropColumn('trial_days');
        });

        Schema::table('system_settings', function (Blueprint $table): void {
            // Historical subscription settings are removed by the following data deletion.
        });

        DB::table('system_settings')->where('setting_category', 'SUBSCRIPTION')->delete();
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
