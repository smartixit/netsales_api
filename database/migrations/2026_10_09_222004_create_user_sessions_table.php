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
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id('session_id');

            $table->foreignId('user_id')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('ip_address', 20)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index('user_id', 'idx_user_sessions_user_id');
            $table->index('last_activity_at', 'idx_user_sessions_last_activity_at');
            $table->index('expires_at', 'idx_user_sessions_expires_at');
            $table->index('revoked_at', 'idx_user_sessions_revoked_at');
            $table->index(['user_id', 'expires_at'], 'idx_user_sessions_user_active');
            $table->index(['user_id', 'revoked_at'], 'idx_user_sessions_user_revoked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
