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
        // 1. Create network_membership_audit_logs
        Schema::create('network_membership_audit_logs', function (Blueprint $table) {
            $table->id('audit_id');
            $table->unsignedBigInteger('network_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('event_seq');
            $table->string('action', 50);
            $table->unsignedBigInteger('actor_id');
            $table->json('old_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['network_id', 'event_seq'], 'uq_net_audit_seq');
            $table->index(['network_id', 'user_id'], 'idx_net_audit_user');

            $table->foreign(['network_id', 'user_id'])
                ->references(['network_id', 'user_id'])
                ->on('network_users')
                ->onDelete('cascade');

            $table->foreign('actor_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('restrict');
        });

        // 2. Create pos_membership_audit_logs
        Schema::create('pos_membership_audit_logs', function (Blueprint $table) {
            $table->id('audit_id');
            $table->unsignedBigInteger('pos_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('event_seq');
            $table->string('action', 50);
            $table->unsignedBigInteger('actor_id');
            $table->json('old_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['pos_id', 'event_seq'], 'uq_pos_audit_seq');
            $table->index(['pos_id', 'user_id'], 'idx_pos_audit_user');

            $table->foreign(['pos_id', 'user_id'])
                ->references(['pos_id', 'user_id'])
                ->on('pos_users')
                ->onDelete('cascade');

            $table->foreign('actor_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_membership_audit_logs');
        Schema::dropIfExists('network_membership_audit_logs');
    }
};

