<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_accounts', function (Blueprint $table): void {
            $table->foreignId('ledger_account_id')->nullable()->unique()
                ->after('currency_id')
                ->constrained('ledger_accounts', 'ledger_account_id')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treasury_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ledger_account_id');
        });
    }
};
