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
        Schema::create('security_security_bans', function (Blueprint $table) {
            $table->id('ban_id');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('device_id')
                ->nullable()
                ->constrained('user_devices', 'device_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('banned_by')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('ban_type', 20)->default('TEMPORARY')->index('idx_security_bans_ban_type'); // TEMPORARY, PERMANENT
            $table->string('ban_reason', 150)->nullable();
            $table->timestamp('start_at')->useCurrent();
            $table->timestamp('end_at')->nullable();

            $table->timestamps();
            $table->softDeletes()->index('idx_security_bans_deleted_at');

            $table->index('user_id', 'idx_security_bans_user_id');
            $table->index('device_id', 'idx_security_bans_device_id');
            $table->index('banned_by', 'idx_security_bans_banned_by');
            $table->index(['start_at', 'end_at'], 'idx_security_bans_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_security_bans');
    }
};
