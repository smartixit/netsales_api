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
        Schema::create('roles', function (Blueprint $table) {
            $table->id('role_id');
            $table->string('role_key', 50)->unique('uq_roles_role_key');
            $table->string('role_name', 50);
            $table->string('role_desc', 150)->nullable();
            $table->boolean('is_system')->default(false)->index('idx_roles_is_system');
            $table->boolean('status')->default(true)->index('idx_roles_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_roles_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
