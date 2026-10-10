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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id('setting_id');

            $table->string('setting_category', 50)->default('GENERAL')->index('idx_system_settings_setting_category');
            $table->string('setting_key', 50)->unique('uq_system_settings_setting_key');
            $table->string('setting_value_type', 20)->default('TEXT')->index('idx_system_settings_setting_value_type');
            $table->string('setting_value', 150);
            $table->string('setting_desc', 150)->nullable();

            $table->timestamps();
            $table->softDeletes()->index('idx_system_settings_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
