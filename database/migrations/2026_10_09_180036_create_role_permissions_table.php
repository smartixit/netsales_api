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
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id('role_permission_id');

            $table->foreignId('role_id')
                ->constrained('roles', 'role_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained('permissions', 'permission_id')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['role_id', 'permission_id'], 'uq_role_permissions_role_permission');
            $table->index('role_id', 'idx_role_permissions_role_id');
            $table->index('permission_id', 'idx_role_permissions_permission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
