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
        Schema::create('users', function (Blueprint $table) {
            $table->id('user_id');

            $table->foreignId('role_id')
                ->constrained('roles', 'role_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('user_full_name', 50);
            $table->string('user_profile_logo', 150)->nullable();
            $table->string('user_phone_number', 20)->unique('uq_users_phone_number');
            $table->string('user_password', 100);
            $table->string('user_email', 50)->nullable();
            $table->boolean('user_2fa_enabled')->default(false)->index('idx_users_2fa_enabled');
            $table->integer('user_login_attempts')->default(0)->index('idx_users_login_attempts');
            $table->timestamp('last_login_at')->nullable();
            $table->string('status', 20)->default('PENDING')->index('idx_users_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_users_deleted_at');

            $table->index('role_id', 'idx_users_role_id');
            $table->index(['role_id', 'user_phone_number'], 'idx_users_role_phone_number');
            $table->index(['user_phone_number', 'status'], 'idx_users_phone_number_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
