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
        Schema::create('sale_invoice_items', function (Blueprint $table) {
            $table->id('item_id');

            $table->foreignId('invoice_id')
                ->constrained('sale_invoices', 'invoice_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('package_id')
                ->constrained('voucher_packages', 'package_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('voucher_price', 8, 2)->default(0.00);
            $table->integer('voucher_count')->default(0);
            $table->decimal('item_discount', 5, 2)->default(0.00);
            $table->decimal('item_total_amount', 15, 2)->default(0.00);

            $table->timestamps();
            $table->softDeletes()->index('idx_sale_invoice_items_deleted_at');

            $table->index('invoice_id', 'idx_sale_invoice_items_invoice_id');
            $table->index('package_id', 'idx_sale_invoice_items_package_id');
            $table->index(['invoice_id', 'package_id'], 'idx_sale_invoice_items_invoice_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_invoice_items');
    }
};
