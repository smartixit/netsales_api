<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_usage_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_minor')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('billing_usage_events', function (Blueprint $table): void {
            $table->dropColumn('amount_minor');
        });
    }
};
