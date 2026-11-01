<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop old single-column foreign key if exists
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign('users_role_id_foreign');
            });
        } catch (Throwable) {
            // Already dropped
        }

        // 2. Make role_id nullable FIRST so rows can be updated to null
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->change();
        });

        // 3. Set role_id = NULL for all non-PLATFORM tenant users
        $tenantRoleIds = DB::table('roles')->where('scope', '!=', 'PLATFORM')->pluck('role_id')->toArray();
        if (! empty($tenantRoleIds)) {
            DB::table('users')->whereIn('role_id', $tenantRoleIds)->update(['role_id' => null]);
        }

        // 4. Add stored role_scope generated column with composite FK
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_scope', 20)
                ->storedAs("CASE WHEN role_id IS NOT NULL THEN 'PLATFORM' ELSE NULL END")
                ->after('role_id');

            $table->foreign(['role_id', 'role_scope'])
                ->references(['role_id', 'scope'])
                ->on('roles')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id', 'role_scope']);
            $table->dropColumn('role_scope');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
            $table->foreign('role_id')->references('role_id')->on('roles')->cascadeOnUpdate()->restrictOnDelete();
        });
    }
};
