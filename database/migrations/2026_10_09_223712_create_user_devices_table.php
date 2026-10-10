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
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id('device_id');

            $table->foreignId('user_id')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('device_uuid', 150);
            $table->string('device_name', 50);
            $table->string('device_type', 50);
            $table->boolean('is_primary')->default(false)->index('idx_user_devices_is_primary');
            $table->string('status', 20)->default('PENDING')->index('idx_user_devices_status');
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();
            $table->softDeletes()->index('idx_user_devices_deleted_at');

            $table->index('user_id', 'idx_user_devices_user_id');
            $table->unique(['user_id', 'device_uuid'], 'uq_user_devices_user_device');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
