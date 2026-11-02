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
        Schema::create('pos_repayments', function (Blueprint $table) {
            $table->id('repayment_id');
            $table->string('repayment_uuid', 50)->unique('uq_pos_repayments_uuid');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('pos_id')
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 50)->default('CASH'); // CASH, BANK_TRANSFER, CHEQUE
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('repayment_date')->useCurrent();

            $table->string('status', 20)->default('COMPLETED')->index('idx_pos_repayments_status'); // COMPLETED, REVERSED

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamp('reversed_at')->nullable();

            $table->foreignId('reversed_by')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->text('reversal_reason')->nullable();
            $table->unsignedBigInteger('reversal_repayment_id')->nullable()->index('idx_pos_repayments_reversal_id');

            $table->timestamps();
            $table->softDeletes()->index('idx_pos_repayments_deleted_at');

            $table->index(['network_id', 'pos_id'], 'idx_pos_repayments_net_pos');
            $table->index(['network_id', 'status'], 'idx_pos_repayments_net_status');
            $table->index(['pos_id', 'status'], 'idx_pos_repayments_pos_status');
            $table->index('repayment_date', 'idx_pos_repayments_date');
        });

        Schema::create('pos_repayment_allocations', function (Blueprint $table) {
            $table->id('allocation_id');

            $table->foreignId('repayment_id')
                ->constrained('pos_repayments', 'repayment_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('invoice_id')
                ->constrained('sale_invoices', 'invoice_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('pos_id')
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('allocated_amount', 15, 2);
            $table->string('status', 20)->default('ACTIVE')->index('idx_allocations_status'); // ACTIVE, REVERSED
            $table->timestamp('allocated_at')->useCurrent();

            $table->timestamps();

            $table->index(['repayment_id', 'status'], 'idx_allocations_repayment_status');
            $table->index(['invoice_id', 'status'], 'idx_allocations_invoice_status');
            $table->index(['network_id', 'pos_id'], 'idx_allocations_net_pos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_repayment_allocations');
        Schema::dropIfExists('pos_repayments');
    }
};
