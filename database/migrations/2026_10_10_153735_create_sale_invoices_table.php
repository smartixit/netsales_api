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
        Schema::create('sale_invoices', function (Blueprint $table) {
            $table->id('invoice_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('pos_id')
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('invoice_uuid', 50)->unique('uq_sale_invoices_invoice_uuid');
            $table->string('invoice_sale_type', 20)->default('CASH')->index('idx_sale_invoices_invoice_sale_type'); // CASH, CREDIT
            $table->decimal('invoice_total_amount', 15, 2)->default(0.00);
            $table->timestamp('invoice_date')->useCurrent();

            $table->foreignId('created_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes()->index('idx_sale_invoices_deleted_at');

            $table->index('network_id', 'idx_sale_invoices_network_id');
            $table->index('pos_id', 'idx_sale_invoices_pos_id');
            $table->index('created_by', 'idx_sale_invoices_created_by');
            $table->index(['network_id', 'pos_id'], 'idx_sale_invoices_network_pos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_invoices');
    }
};
