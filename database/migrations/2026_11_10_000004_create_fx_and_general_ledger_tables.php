<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_exchange_rates', function (Blueprint $table): void {
            $table->id('exchange_rate_id');
            $table->foreignId('base_currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->foreignId('quote_currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->unsignedBigInteger('rate_numerator');
            $table->unsignedBigInteger('rate_denominator');
            $table->string('source', 80);
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamps();
            $table->index(['base_currency_id', 'quote_currency_id', 'effective_at']);
        });

        Schema::create('currency_conversions', function (Blueprint $table): void {
            $table->id('conversion_id');
            $table->foreignId('exchange_rate_id')->constrained('currency_exchange_rates', 'exchange_rate_id')->restrictOnDelete();
            $table->foreignId('from_currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->foreignId('to_currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->unsignedBigInteger('source_amount_minor');
            $table->unsignedBigInteger('converted_amount_minor');
            $table->unsignedBigInteger('fee_amount_minor')->default(0);
            $table->string('rounding_mode', 20)->default('HALF_UP');
            $table->decimal('rate_snapshot', 24, 12);
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('converted_at')->useCurrent();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id('ledger_account_id');
            $table->string('account_code', 40);
            $table->string('name', 120);
            $table->string('account_class', 16);
            $table->string('normal_balance', 6);
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->boolean('is_control_account')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['account_code', 'currency_id']);
            $table->index(['account_class', 'currency_id']);
        });

        Schema::create('ledger_journal_entries', function (Blueprint $table): void {
            $table->id('journal_entry_id');
            $table->string('entry_number', 50)->unique();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->dateTime('entry_date');
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 16)->default('POSTED')->index();
            $table->string('idempotency_key', 128)->unique();
            $table->string('description', 255);
            $table->foreignId('reversal_of_id')->nullable()->constrained('ledger_journal_entries', 'journal_entry_id')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users', 'user_id')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['currency_id', 'entry_date']);
        });

        Schema::create('ledger_journal_lines', function (Blueprint $table): void {
            $table->id('journal_line_id');
            $table->foreignId('journal_entry_id')->constrained('ledger_journal_entries', 'journal_entry_id')->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts', 'ledger_account_id')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies', 'currency_id')->restrictOnDelete();
            $table->unsignedBigInteger('debit_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);
            $table->string('description', 180)->nullable();
            $table->timestamps();
            $table->index(['ledger_account_id', 'currency_id']);
        });

        Schema::create('billing_dunning_attempts', function (Blueprint $table): void {
            $table->id('dunning_attempt_id');
            $table->foreignId('invoice_id')->constrained('billing_invoices', 'invoice_id')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 16)->default('PENDING')->index();
            $table->string('channel', 20);
            $table->timestamp('scheduled_at');
            $table->timestamp('attempted_at')->nullable();
            $table->text('result_message')->nullable();
            $table->timestamps();
            $table->unique(['invoice_id', 'attempt_number']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('billing_webhook_inbox', function (Blueprint $table): void {
            $table->id('webhook_id');
            $table->string('provider', 60);
            $table->string('provider_event_id', 160);
            $table->string('event_type', 120);
            $table->json('payload');
            $table->string('status', 16)->default('RECEIVED')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::create('billing_automation_jobs', function (Blueprint $table): void {
            $table->id('billing_job_id');
            $table->string('job_type', 80);
            $table->string('aggregate_type', 80);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('status', 16)->default('PENDING')->index();
            $table->timestamp('scheduled_at')->index();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['aggregate_type', 'aggregate_id', 'job_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_automation_jobs');
        Schema::dropIfExists('billing_webhook_inbox');
        Schema::dropIfExists('billing_dunning_attempts');
        Schema::dropIfExists('ledger_journal_lines');
        Schema::dropIfExists('ledger_journal_entries');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('currency_conversions');
        Schema::dropIfExists('currency_exchange_rates');
    }
};