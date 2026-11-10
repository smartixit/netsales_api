<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_revenue_recognition_schedules', function (Blueprint $table): void {
            $table->id('revenue_schedule_id');
            $table->foreignId('invoice_id')
                ->constrained('billing_invoices', 'invoice_id', 'fk_brrs_invoice')
                ->restrictOnDelete();
            $table->foreignId('invoice_line_id')
                ->unique('uq_brrs_invoice_line')
                ->constrained('billing_invoice_lines', 'invoice_line_id', 'fk_brrs_invoice_line')
                ->restrictOnDelete();
            $table->foreignId('currency_id')
                ->constrained('currencies', 'currency_id', 'fk_brrs_currency')
                ->restrictOnDelete();
            $table->string('revenue_account_code', 4)->default('4100');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('recognized_minor')->default(0);
            $table->date('service_start_on');
            $table->date('service_end_on');
            $table->date('last_recognized_on')->nullable();
            $table->string('status', 12)->default('PENDING')->index();
            $table->timestamps();
            $table->index(['status', 'service_start_on'], 'idx_brrs_status_start');
        });

        Schema::create('billing_revenue_recognition_entries', function (Blueprint $table): void {
            $table->id('revenue_recognition_entry_id');
            $table->foreignId('revenue_schedule_id')
                ->constrained('billing_revenue_recognition_schedules', 'revenue_schedule_id', 'fk_brre_schedule')
                ->restrictOnDelete();
            $table->foreignId('journal_entry_id')
                ->unique('uq_brre_journal_entry')
                ->constrained('ledger_journal_entries', 'journal_entry_id', 'fk_brre_journal')
                ->restrictOnDelete();
            $table->date('recognized_through');
            $table->unsignedBigInteger('amount_minor');
            $table->string('idempotency_key', 128)->unique('uq_brre_idempotency');
            $table->timestamps();
            $table->unique(['revenue_schedule_id', 'recognized_through'], 'uq_brre_schedule_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_revenue_recognition_entries');
        Schema::dropIfExists('billing_revenue_recognition_schedules');
    }
};
