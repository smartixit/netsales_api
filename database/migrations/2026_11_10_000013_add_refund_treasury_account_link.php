<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_refunds', function (Blueprint $table): void {
            $table->foreignId('treasury_account_id')
                ->nullable()
                ->after('credit_note_id')
                ->constrained('treasury_accounts', 'treasury_account_id', 'fk_brf_treasury')
                ->restrictOnDelete();
            $table->index(['treasury_account_id', 'status'], 'idx_brf_treasury_status');
        });
    }

    public function down(): void
    {
        Schema::table('billing_refunds', function (Blueprint $table): void {
            $table->dropIndex('idx_brf_treasury_status');
            $table->dropForeign('fk_brf_treasury');
            $table->dropColumn('treasury_account_id');
        });
    }
};
