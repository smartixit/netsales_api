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
        Schema::create('otp', function (Blueprint $table) {
            $table->id('otp_id');

            $table->foreignId('user_id')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('otp_code', 10);
            $table->string('otp_type', 50)->index('idx_otp_type');
            $table->integer('otp_failure_attempts')->default(0);
            $table->boolean('is_used')->default(false)->index('idx_otp_is_used');
            $table->timestamp('expires_at')->index('idx_otp_expires_at');

            $table->timestamps();

            $table->index('user_id', 'idx_otp_user_id');
            $table->index(['user_id', 'is_used', 'expires_at'], 'idx_otp_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp');
    }
};
