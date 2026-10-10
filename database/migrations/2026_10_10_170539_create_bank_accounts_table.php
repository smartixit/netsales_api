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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id('account_id');
            $table->string('bank_name', 50);
            $table->string('bank_logo', 100)->nullable();
            $table->string('account_holder_name', 50);
            $table->string('account_number', 50);
            $table->string('account_type', 20)->default('SAVING')->index('idx_bank_accounts_account_type'); // SAVING, CURRENT, WALLET
            $table->string('account_currency', 4)->default('YER')->index('idx_bank_accounts_account_currency'); // YER, USD, SAR
            $table->decimal('account_balance', 15, 2)->default(0.00);
            $table->boolean('status')->default(true)->index('idx_bank_accounts_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_bank_accounts_deleted_at');

            $table->unique(['account_number', 'account_currency', 'account_type'], 'uq_bank_accounts_account_number_currency_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
