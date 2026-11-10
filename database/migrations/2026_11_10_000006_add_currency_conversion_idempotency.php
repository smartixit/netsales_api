<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currency_conversions', function (Blueprint $table): void {
            $table->unique(['source_type', 'source_id'], 'uq_currency_conversion_source');
        });
    }

    public function down(): void
    {
        Schema::table('currency_conversions', function (Blueprint $table): void {
            $table->dropUnique('uq_currency_conversion_source');
        });
    }
};
