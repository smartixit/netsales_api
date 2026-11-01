<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->decimal('item_cash_discount', 5, 2)->nullable()->after('item_discount')
                ->comment('نسبة خصم النقد المجمدة لحظة تقديم الطلب');
            $table->decimal('item_credit_discount', 5, 2)->nullable()->after('item_cash_discount')
                ->comment('نسبة خصم الآجل المجمدة لحظة تقديم الطلب');
        });

        // ترقية السجلات السابقة بتعبئة نسب الخصم الملتقطة من البند الحالي
        DB::table('request_items')->update([
            'item_cash_discount' => DB::raw('item_discount'),
            'item_credit_discount' => DB::raw('item_discount'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_items', function (Blueprint $table) {
            $table->dropColumn(['item_cash_discount', 'item_credit_discount']);
        });
    }
};

