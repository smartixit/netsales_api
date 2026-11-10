<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_credit_notes', function (Blueprint $table): void {
            $table->string('idempotency_key', 128)->unique()->after('credit_note_number');
            $table->string('accounting_treatment', 24)->default('DEFERRED_REVENUE')->after('reason');
            $table->unsignedBigInteger('applied_to_receivable_minor')->default(0)->after('total_minor');
            $table->unsignedBigInteger('refundable_minor')->default(0)->after('applied_to_receivable_minor');
            $table->unsignedBigInteger('refunded_minor')->default(0)->after('refundable_minor');
            $table->string('status', 24)->default('DRAFT')->change();
        });

        Schema::table('billing_refunds', function (Blueprint $table): void {
            $table->foreignId('credit_note_id')->nullable()
                ->after('payment_id')
                ->constrained('billing_credit_notes', 'credit_note_id')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_refunds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credit_note_id');
        });

        Schema::table('billing_credit_notes', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'idempotency_key',
                'accounting_treatment',
                'applied_to_receivable_minor',
                'refundable_minor',
                'refunded_minor',
            ]);
            $table->string('status', 16)->default('DRAFT')->change();
        });
    }
};
