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

        // ترقية السجلات التاريخية السابقة بتعبئة نسب الخصم الملتقطة من البند الحالي
        // ملاحظة توثيقية وتوافقية: بالنسبة للطلبات التاريخية السابقة، لم يتم تسجيل سوى نسبة خصم واحدة (item_discount)
        // لنوع البيع المتفق عليه وقت تقديم الطلب. نسخ item_discount هو إجراء توافق رجعي (Legacy Fallback) صريح
        // ولا يدّعي استرجاع نسبتين منفصلتين بأثر رجعي. وحيث أن تعديل نوع البيع أثناء المراجعة محظور تماماً،
        // فإن الطلبات السابقة والجديدة تُفوتر حصراً وفق نوع البيع الأصلي المتفق عليه.
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
