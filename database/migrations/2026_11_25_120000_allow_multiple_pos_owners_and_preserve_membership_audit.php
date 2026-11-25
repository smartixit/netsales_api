<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos', function (Blueprint $table): void {
            // MySQL requires the referenced owner_id index to remain available
            // while the foreign key exists. Recreate the FK after removing
            // the one-to-one unique constraint so the POS can have multiple owners.
            $table->dropForeign(['owner_id']);
            $table->dropUnique('uq_pos_owner_id');
            $table->foreign('owner_id')
                ->references('user_id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
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
        $hasMultiplePosOwners = DB::table('pos')
            ->select('owner_id')
            ->groupBy('owner_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        $hasOrphanedNetworkAudit = DB::table('network_membership_audit_logs as audit')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('network_users')
                    ->whereColumn('network_users.network_id', 'audit.network_id')
                    ->whereColumn('network_users.user_id', 'audit.user_id');
            })
            ->exists();

        $hasOrphanedPosAudit = DB::table('pos_membership_audit_logs as audit')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('pos_users')
                    ->whereColumn('pos_users.pos_id', 'audit.pos_id')
                    ->whereColumn('pos_users.user_id', 'audit.user_id');
            })
            ->exists();

        if ($hasMultiplePosOwners || $hasOrphanedNetworkAudit || $hasOrphanedPosAudit) {
            throw new \RuntimeException(
                'Cannot safely roll back entity ownership and membership audit constraints: data now depends on the new schema.'
            );
        }

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
