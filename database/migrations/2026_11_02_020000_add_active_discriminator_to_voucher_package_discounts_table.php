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
        // 1. فحص سلامة البيانات الحالية والتأكد من عدم وجود تكرار نشط مسبقاً
        $duplicates = DB::table('voucher_package_discounts')
            ->select('network_id', 'pos_id', 'package_id', DB::raw('COUNT(*) as count'))
            ->whereNull('deleted_at')
            ->groupBy('network_id', 'pos_id', 'package_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'تعذر تطبيق القيد الفريد: تم العثور على سجلات مكررة نشطة لنفس توليفة (الشبكة، نقطة البيع، الباقة): '.
                json_encode($duplicates->toArray(), JSON_UNESCAPED_UNICODE)
            );
        }

        // 2. إضافة العمود المحسوب والقيد الفريد المتوافق مع الحذف المرن (SoftDeletes)
        Schema::table('voucher_package_discounts', function (Blueprint $table) {
            $table->tinyInteger('active_discriminator')
                ->nullable()
                ->storedAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END')
                ->after('deleted_at')
                ->comment('مميّز أحادي للسجلات غير المحذوفة فقط لضمان قيد فريد قطعي في MySQL 8');

            $table->unique(
                ['network_id', 'pos_id', 'package_id', 'active_discriminator'],
                'uq_vpd_network_pos_package_active'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_package_discounts', function (Blueprint $table) {
            $table->dropUnique('uq_vpd_network_pos_package_active');
            $table->dropColumn('active_discriminator');
        });
    }
};
