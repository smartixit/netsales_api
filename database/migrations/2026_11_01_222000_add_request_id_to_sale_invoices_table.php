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
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->foreignId('request_id')
                ->nullable()
                ->after('pos_id')
                ->constrained('requests', 'request_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->unique('request_id', 'uq_sale_invoices_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->dropUnique('uq_sale_invoices_request_id');
            $table->dropColumn('request_id');
        });
    }
};
