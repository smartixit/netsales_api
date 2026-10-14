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
        Schema::create('scheduled_task_runs', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->autoIncrement();
            $table->string('task_type', 50); // daily_summary | monthly_summary | backup_auto | etc.
            $table->unsignedBigInteger('network_id')->nullable(); // null يعني المهمة عامة لكامل النظام
            $table->date('target_date'); // التاريخ المستهدف للتشغيل
            $table->string('status', 20)->default('sent'); // sent | failed | partially_sent
            $table->json('extra')->nullable(); // بيانات إضافية مثل الهواتف أو تفاصيل الأخطاء
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // قيد فريد مركب يضمن عدم تكرار سجلات نفس المهمة لنفس الشبكة والتاريخ (Idempotent)
            $table->unique(['task_type', 'network_id', 'target_date'], 'scheduled_task_network_date_unique');

            // فهارس للبحث السريع والتنظيف الدوري
            $table->index(['task_type', 'target_date'], 'scheduled_task_type_date_idx');
            $table->index(['task_type', 'status'], 'scheduled_task_type_status_idx');
            $table->index('created_at', 'scheduled_task_created_idx');

            $table->foreign('network_id', 'fk_scheduled_task_network_id')
                ->references('network_id')
                ->on('networks')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
