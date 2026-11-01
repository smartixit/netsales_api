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
        Schema::table('voucher_package_discounts', function (Blueprint $table) {
            $table->timestamp('discount_start_date')->nullable()->after('status');
            $table->timestamp('discount_end_date')->nullable()->after('discount_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_package_discounts', function (Blueprint $table) {
            $table->dropColumn(['discount_start_date', 'discount_end_date']);
        });
    }
};
