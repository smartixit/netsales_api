<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id('invoice_id');
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('billing_account_id')->constrained('billing_accounts', 'billing_account_id')->restrictOnDelete();
            $table->foreignId('billing_subscription_id')->nullable()->constrained('billing_subscriptions', 'billing_subscription_id')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->string('invoice_type', 16)->default('STANDARD');
            $table->string('status', 20)->default('DRAFT')->index();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable()->index();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->unsignedBigInteger('remaining_minor')->default(0);
            $table->json('customer_snapshot');
            $table->json('pricing_snapshot');
            $table->text('memo')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->index(['billing_account_id', 'status', 'invoice_id']);
            $table->index(['status', 'due_on']);
            $table->index(['currency_id', 'issued_on']);
        });

        Schema::create('billing_invoice_lines', function (Blueprint $table): void {
            $table->id('invoice_line_id');
            $table->foreignId('invoice_id')->constrained('billing_invoices', 'invoice_id')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->string('line_type', 20)->default('SUBSCRIPTION');
            $table->string('description', 180);
            $table->decimal('quantity', 18, 6)->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->foreignId('plan_version_id')->nullable()->constrained('billing_plan_versions', 'plan_version_id')->restrictOnDelete();
            $table->foreignId('price_id')->nullable()->constrained('billing_prices', 'price_id')->restrictOnDelete();
            $table->json('source_snapshot')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'line_type']);
        });

        Schema::create('billing_credit_notes', function (Blueprint $table): void {
            $table->id('credit_note_id');
            $table->string('credit_note_number', 40)->unique();
            $table->foreignId('invoice_id')->constrained('billing_invoices', 'invoice_id')->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained('billing_accounts', 'billing_account_id')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->string('status', 16)->default('DRAFT')->index();
            $table->string('reason', 255);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });

        Schema::create('billing_credit_note_lines', function (Blueprint $table): void {
            $table->id('credit_note_line_id');
            $table->foreignId('credit_note_id')->constrained('billing_credit_notes', 'credit_note_id')->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained('billing_invoice_lines', 'invoice_line_id')->restrictOnDelete();
            $table->string('description', 180);
            $table->unsignedBigInteger('quantity_micros')->default(1000000);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
        });

        Schema::create('billing_payment_methods', function (Blueprint $table): void {
            $table->id('payment_method_id');
            $table->string('method_key', 40)->unique();
            $table->string('name', 100);
            $table->string('method_type', 20);
            $table->boolean('requires_proof')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->json('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_payment_method_currencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_method_id')->constrained('billing_payment_methods', 'payment_method_id')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['payment_method_id', 'currency_id']);
        });

        Schema::create('treasury_accounts', function (Blueprint $table): void {
            $table->id('treasury_account_id');
            $table->string('account_code', 40)->unique();
            $table->string('name', 120);
            $table->string('account_type', 20)->default('BANK');
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->string('institution_name', 120)->nullable();
            $table->string('account_reference', 120)->nullable();
            $table->string('status', 16)->default('ACTIVE')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['currency_id', 'status']);
        });

        Schema::create('billing_payment_intents', function (Blueprint $table): void {
            $table->id('payment_intent_id');
            $table->string('intent_uuid', 64)->unique();
            $table->foreignId('invoice_id')->constrained('billing_invoices', 'invoice_id')->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained('billing_accounts', 'billing_account_id')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('billing_payment_methods', 'payment_method_id')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->default('REQUIRES_PAYMENT_METHOD')->index();
            $table->string('idempotency_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });

        Schema::create('billing_payment_attempts', function (Blueprint $table): void {
            $table->id('payment_attempt_id');
            $table->foreignId('payment_intent_id')->constrained('billing_payment_intents', 'payment_intent_id')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 20)->default('PENDING')->index();
            $table->string('provider_reference', 120)->nullable();
            $table->text('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_intent_id', 'attempt_number']);
        });

        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->id('payment_id');
            $table->string('payment_reference', 50)->unique();
            $table->foreignId('payment_intent_id')->nullable()->constrained('billing_payment_intents', 'payment_intent_id')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('billing_invoices', 'invoice_id')->restrictOnDelete();
            $table->foreignId('billing_account_id')->constrained('billing_accounts', 'billing_account_id')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('billing_payment_methods', 'payment_method_id')->restrictOnDelete();
            $table->foreignId('treasury_account_id')->nullable()->constrained('treasury_accounts', 'treasury_account_id')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 20)->default('PENDING_REVIEW')->index();
            $table->string('external_reference', 120)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
            $table->index(['billing_account_id', 'received_at']);
        });

        Schema::create('billing_payment_proofs', function (Blueprint $table): void {
            $table->id('payment_proof_id');
            $table->foreignId('payment_id')->constrained('billing_payments', 'payment_id')->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->string('original_name', 180)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('sha256', 64)->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('billing_refunds', function (Blueprint $table): void {
            $table->id('refund_id');
            $table->string('refund_reference', 50)->unique();
            $table->foreignId('payment_id')->constrained('billing_payments', 'payment_id')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 16)->default('PENDING')->index();
            $table->string('reason', 255);
            $table->foreignId('processed_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_refunds');
        Schema::dropIfExists('billing_payment_proofs');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_payment_attempts');
        Schema::dropIfExists('billing_payment_intents');
        Schema::dropIfExists('treasury_accounts');
        Schema::dropIfExists('billing_payment_method_currencies');
        Schema::dropIfExists('billing_payment_methods');
        Schema::dropIfExists('billing_credit_note_lines');
        Schema::dropIfExists('billing_credit_notes');
        Schema::dropIfExists('billing_invoice_lines');
        Schema::dropIfExists('billing_invoices');
    }
};