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
        Schema::table('pos_wallets', function (Blueprint $table) {
            $table->decimal('credit_limit', 15, 2)->nullable()->default(null)->after('wallet_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_wallets', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }
};
