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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id('log_id');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('module', 50);
            $table->string('action', 50);

            $table->string('entity_type', 50)->nullable();
            $table->integer('entity_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('log_date')->useCurrent();

            $table->index('user_id', 'idx_system_logs_user_id');
            $table->index('log_date', 'idx_system_logs_log_date');
            $table->index(['module', 'action'], 'idx_system_logs_module_action');
            $table->index(['entity_type', 'entity_id'], 'idx_system_logs_entity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
