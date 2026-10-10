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
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id('user_permission_id');

            $table->foreignId('user_id')
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained('permissions', 'permission_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'permission_id'], 'uq_user_permissions_user_permission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
