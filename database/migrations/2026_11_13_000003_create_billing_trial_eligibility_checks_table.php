<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_trial_eligibility_checks', function (Blueprint $table): void {
            $table->id('trial_eligibility_check_id');
            $table->foreignId('network_id')->constrained('networks', 'network_id')->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained('billing_accounts', 'billing_account_id')->restrictOnDelete();
            $table->foreignId('price_id')->constrained('billing_prices', 'price_id')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('billing_plans', 'plan_id')->restrictOnDelete();
            $table->foreignId('trial_policy_version_id')->nullable()
                ->constrained('billing_trial_policy_versions', 'trial_policy_version_id')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->string('eligibility_status', 12);
            $table->string('reason_code', 60)->nullable();
            $table->string('source', 32);
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['network_id', 'checked_at'], 'idx_bt_ec_network_date');
            $table->index(['trial_policy_version_id', 'eligibility_status', 'checked_at'], 'idx_bt_ec_policy_status_date');
            $table->index(['billing_account_id', 'price_id', 'checked_at'], 'idx_bt_ec_account_price_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_trial_eligibility_checks');
    }
};
