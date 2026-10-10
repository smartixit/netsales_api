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
        Schema::create('voucher_package_discounts', function (Blueprint $table) {
            $table->id('discount_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('package_id')
                ->constrained('voucher_packages', 'package_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('pos_id')
                ->constrained('pos', 'pos_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->decimal('discount_cash', 5, 2)->default(0.00);
            $table->decimal('discount_credit', 5, 2)->default(0.00);
            $table->boolean('status')->default(true)->index('idx_voucher_package_discounts_status');

            $table->foreignId('created_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes()->index('idx_voucher_package_discounts_deleted_at');

            // $table->unique(['network_id', 'pos_id', 'package_id'], 'uq_voucher_package_discounts_network_pos_package');

            $table->index('network_id', 'idx_voucher_package_discounts_network_id');
            $table->index('pos_id', 'idx_voucher_package_discounts_pos_id');
            $table->index('package_id', 'idx_voucher_package_discounts_package_id');
            $table->index('created_by', 'idx_voucher_package_discounts_created_by');
            $table->index(['network_id', 'status'], 'idx_voucher_package_discounts_network_status');
            $table->index(['network_id', 'pos_id'], 'idx_voucher_package_discounts_network_pos');
            $table->index(['network_id', 'package_id'], 'idx_voucher_package_discounts_network_package');
            $table->index(['pos_id', 'status'], 'idx_voucher_package_discounts_pos_status');
            $table->index(['pos_id', 'package_id'], 'idx_voucher_package_discounts_pos_package');
            $table->index(['package_id', 'status'], 'idx_voucher_package_discounts_package_status');
            $table->index(['network_id', 'pos_id', 'package_id', 'status'], 'idx_voucher_package_discounts_network_pos_package_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_package_discounts');
    }
};
