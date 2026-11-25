<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos', function (Blueprint $table): void {
            $table->dropUnique('uq_pos_owner_id');
        });

        Schema::table('network_membership_audit_logs', function (Blueprint $table): void {
            $table->dropForeign('network_membership_audit_logs_network_id_user_id_foreign');
            $table->foreign('network_id')
                ->references('network_id')
                ->on('networks')
                ->cascadeOnDelete();
        });

        Schema::table('pos_membership_audit_logs', function (Blueprint $table): void {
            $table->dropForeign('pos_membership_audit_logs_pos_id_user_id_foreign');
            $table->foreign('pos_id')
                ->references('pos_id')
                ->on('pos')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_membership_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['pos_id']);
            $table->foreign(['pos_id', 'user_id'])
                ->references(['pos_id', 'user_id'])
                ->on('pos_users')
                ->cascadeOnDelete();
        });

        Schema::table('network_membership_audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['network_id']);
            $table->foreign(['network_id', 'user_id'])
                ->references(['network_id', 'user_id'])
                ->on('network_users')
                ->cascadeOnDelete();
        });

        Schema::table('pos', function (Blueprint $table): void {
            $table->unique('owner_id', 'uq_pos_owner_id');
        });
    }
};
