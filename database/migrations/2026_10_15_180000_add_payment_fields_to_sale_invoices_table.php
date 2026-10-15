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
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0.00)->after('invoice_total_amount');
            $table->decimal('remaining_amount', 15, 2)->default(0.00)->after('paid_amount');
            $table->string('payment_status', 20)->default('UNPAID')->index('idx_sale_invoices_payment_status')->after('remaining_amount');
        });

        // تحديث البيانات القديمة لتطابق المنطق المحاسبي
        // DB::table('sale_invoices')
        //     ->where('invoice_sale_type', 'CASH')
        //     ->update([
        //         'paid_amount' => DB::raw('invoice_total_amount'),
        //         'remaining_amount' => 0.00,
        //         'payment_status' => 'PAID',
        //     ]);

        // DB::table('sale_invoices')
        //     ->where('invoice_sale_type', 'CREDIT')
        //     ->update([
        //         'paid_amount' => 0.00,
        //         'remaining_amount' => DB::raw('invoice_total_amount'),
        //         'payment_status' => 'UNPAID',
        //     ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_sale_invoices_payment_status');
            $table->dropColumn(['paid_amount', 'remaining_amount', 'payment_status']);
        });
    }
};
