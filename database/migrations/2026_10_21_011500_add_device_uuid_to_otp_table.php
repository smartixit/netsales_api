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
        Schema::table('otp', function (Blueprint $table) {
            $table->string('device_uuid', 255)->after('otp_type');
            $table->index(['user_id', 'device_uuid'], 'idx_otp_user_device');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp', function (Blueprint $table) {
            $table->dropIndex('idx_otp_user_device');
            $table->dropColumn('device_uuid');
        });
    }
};
