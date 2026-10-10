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
        Schema::create('voucher_packages', function (Blueprint $table) {
            $table->id('package_id');

            $table->foreignId('network_id')
                ->constrained('networks', 'network_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('package_name', 50);
            $table->string('package_color', 20)->default('0xFF4A263F');
            $table->string('package_balance', 20)->nullable();
            $table->string('package_uptime', 20)->nullable();
            $table->string('package_validity', 20)->nullable();
            $table->integer('package_voucher_count')->default(51);
            $table->decimal('package_voucher_price', 8, 2)->default(0.00);
            $table->decimal('package_default_cash_discount', 5, 2)->default(0.00);
            $table->decimal('package_default_credit_discount', 5, 2)->default(0.00);
            $table->boolean('status')->default(true)->index('idx_voucher_packages_status');

            $table->foreignId('created_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes()->index('idx_voucher_packages_deleted_at');

            $table->unique(['network_id', 'package_name'], 'uq_voucher_packages_network_package_name');
            $table->index('network_id', 'idx_voucher_packages_network_id');
            $table->index('created_by', 'idx_voucher_packages_created_by');
            $table->index(['network_id', 'status'], 'idx_voucher_packages_network_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_packages');
    }
};
