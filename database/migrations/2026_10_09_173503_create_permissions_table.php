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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id('permission_id');

            $table->foreignId('module_id')
                ->constrained('modules', 'module_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('action_type_id')
                ->constrained('action_types', 'action_type_id')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('permission_key', 50)->unique('uq_permissions_permission_key');
            $table->string('permission_name', 50);
            $table->string('permission_desc', 150)->nullable();
            $table->boolean('status')->default(true)->index('idx_permissions_status');

            $table->timestamps();
            $table->softDeletes()->index('idx_permissions_deleted_at');

            $table->unique(['module_id', 'action_type_id'], 'uq_permissions_module_action_type');
            $table->index('module_id', 'idx_permissions_module_id');
            $table->index('action_type_id', 'idx_permissions_action_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
