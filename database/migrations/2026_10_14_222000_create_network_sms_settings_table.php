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
        Schema::create('network_sms_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('network_id')->unique('uq_network_sms_settings_network_id');
            $table->boolean('is_enabled')->default(true);

            // مفاتيح التحكم الدقيقة بأنواع الرسائل المالية والتشغيلية في NetSales
            $table->boolean('notify_receipt')->default(true);             // سند قبض لنقطة البيع (لكم سند قبض بمبلغ... رصيدكم...)
            $table->boolean('notify_payment')->default(true);             // سند دفع/قيد على نقطة البيع (عليكم سند دفع بمبلغ... رصيدكم...)
            $table->boolean('notify_pos_withdraw')->default(true);        // سحب نقطة البيع من رصيد المحفظة
            $table->boolean('notify_expense')->default(true);             // إشعار مدير الشبكة عند تسجيل أو تسوية مصروف يمس المحفظة
            $table->boolean('notify_sale_invoice')->default(true);        // إشعار فواتير بيع كروت الشبكة
            $table->boolean('notify_voucher_request')->default(true);     // إشعار طلبات الرصيد والبطائق لنقاط البيع
            $table->boolean('notify_low_balance')->default(true);         // تنبيه هبوط رصيد المحفظة تحت الحد الائتماني
            $table->boolean('notify_daily_summary')->default(true);       // إرسال الملخص المالي اليومي للشبكة
            $table->boolean('notify_monthly_summary')->default(true);     // إرسال التقرير المالي الختامي الشهري للشبكة
            $table->boolean('notify_subscription_expiry')->default(true); // تنبيهات اقتراب وانتهاء اشتراك الشبكة في النظام

            // وضع البوابة: false = استخدام بوابة النظام العامة، true = بوابة خاصة بالشبكة
            $table->boolean('use_custom_gateway')->default(false);

            // إعدادات بوابة SMSGate الخاصة بالشبكة
            $table->string('server_type', 20)->default('cloud');
            $table->string('base_url', 255)->default('https://api.sms-gate.app');
            $table->string('auth_type', 20)->default('bearer');
            $table->text('api_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('username', 100)->nullable();
            $table->text('password')->nullable();
            $table->string('device_id', 100)->nullable();
            $table->unsignedTinyInteger('sim_number')->default(1);
            $table->boolean('skip_phone_validation')->default(false);
            $table->unsignedInteger('device_active_within')->default(0);

            $table->timestamps();

            $table->foreign('network_id', 'fk_network_sms_settings_network_id')
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
        Schema::dropIfExists('network_sms_settings');
    }
};
