<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id('currency_id');
            $table->char('code', 3)->unique();
            $table->char('numeric_code', 3)->nullable()->unique();
            $table->string('name', 80);
            $table->string('native_name', 80)->nullable();
            $table->string('symbol', 12)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('billing_products', function (Blueprint $table): void {
            $table->id('product_id');
            $table->string('product_key', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->id('plan_id');
            $table->foreignId('product_id')->constrained('billing_products', 'product_id')->restrictOnDelete();
            $table->string('plan_key', 80);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('is_public')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'plan_key']);
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('billing_plan_versions', function (Blueprint $table): void {
            $table->id('plan_version_id');
            $table->foreignId('plan_id')->constrained('billing_plans', 'plan_id')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16)->default('DRAFT')->index();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('terms_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->unique(['plan_id', 'version_number']);
            $table->index(['plan_id', 'status']);
        });

        Schema::table('billing_plans', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'fk_billing_plans_current_version')
                ->references('plan_version_id')->on('billing_plan_versions')
                ->nullOnDelete();
        });

        Schema::create('billing_prices', function (Blueprint $table): void {
            $table->id('price_id');
            $table->foreignId('plan_version_id')->constrained('billing_plan_versions', 'plan_version_id')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->string('billing_interval', 16);
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('setup_fee_minor')->default(0);
            $table->string('tax_behavior', 12)->default('EXCLUSIVE');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(
                ['plan_version_id', 'currency_id', 'billing_interval', 'interval_count'],
                'uq_billing_price_version_currency_interval'
            );
            $table->index(['currency_id', 'is_active']);
        });

        Schema::create('billing_plan_features', function (Blueprint $table): void {
            $table->id('plan_feature_id');
            $table->foreignId('plan_version_id')->constrained('billing_plan_versions', 'plan_version_id')->restrictOnDelete();
            $table->string('feature_key', 100);
            $table->string('name', 120);
            $table->string('feature_type', 24);
            $table->text('value')->nullable();
            $table->string('unit', 40)->nullable();
            $table->string('aggregation', 16)->nullable();
            $table->string('reset_period', 20)->nullable();
            $table->string('overage_behavior', 16)->default('BLOCK');
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['plan_version_id', 'feature_key']);
            $table->index(['feature_key', 'feature_type']);
        });

        Schema::create('billing_accounts', function (Blueprint $table): void {
            $table->id('billing_account_id');
            $table->foreignId('network_id')->unique()->constrained('networks', 'network_id')->restrictOnDelete();
            $table->string('account_number', 40)->unique();
            $table->string('legal_name', 160)->nullable();
            $table->string('billing_email', 160)->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->text('billing_address')->nullable();
            $table->foreignId('default_currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->string('status', 16)->default('ACTIVE')->index();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billing_account_contacts', function (Blueprint $table): void {
            $table->id('contact_id');
            $table->foreignId('billing_account_id')->constrained('billing_accounts', 'billing_account_id')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('receives_invoices')->default(true);
            $table->timestamps();
            $table->index(['billing_account_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_account_contacts');
        Schema::dropIfExists('billing_accounts');
        Schema::dropIfExists('billing_plan_features');
        Schema::dropIfExists('billing_prices');
        Schema::table('billing_plans', function (Blueprint $table): void {
            $table->dropForeign('fk_billing_plans_current_version');
        });
        Schema::dropIfExists('billing_plan_versions');
        Schema::dropIfExists('billing_plans');
        Schema::dropIfExists('billing_products');
        Schema::dropIfExists('currencies');
    }
};