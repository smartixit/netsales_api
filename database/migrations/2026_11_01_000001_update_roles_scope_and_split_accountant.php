<?php

use App\Enums\RoleKey;
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
        // 1. Add scope column to roles
        Schema::table('roles', function (Blueprint $table) {
            $table->string('scope', 20)
                ->default('PLATFORM')
                ->after('role_name');
        });

        // 2. Set scopes for existing system roles
        DB::table('roles')->whereIn('role_key', ['SUPER_ADMIN', 'SUPPORT_AGENT'])->update(['scope' => 'PLATFORM']);
        DB::table('roles')->whereIn('role_key', ['NETWORK_OWNER', 'NETWORK_USER'])->update(['scope' => 'NETWORK']);
        DB::table('roles')->whereIn('role_key', ['POS_AGENT', 'POS_USER'])->update(['scope' => 'POS']);

        // 3. Update existing Role 6 (ACCOUNTANT) to NETWORK_ACCOUNTANT
        $accountant = DB::table('roles')->where('role_key', 'ACCOUNTANT')->first();
        if ($accountant) {
            DB::table('roles')->where('role_id', $accountant->role_id)->update([
                'role_key' => RoleKey::NETWORK_ACCOUNTANT->value,
                'role_name' => 'محاسب شبكة',
                'role_desc' => 'إدارة ومتابعة العمليات المالية والمصروفات وفواتير مبيعات كروت الشبكة',
                'scope' => 'NETWORK',
            ]);
        }

        // 4. Create POS_ACCOUNTANT role (Role 8)
        $posAccountantId = DB::table('roles')->insertGetId([
            'role_key' => RoleKey::POS_ACCOUNTANT->value,
            'role_name' => 'محاسب نقطة بيع',
            'role_desc' => 'إدارة العمليات المالية والمصروفات والمبيعات ومتابعة رصيد نقطة البيع',
            'scope' => 'POS',
            'is_system' => true,
            'status' => true,
            'created_at' => now('Asia/Aden'),
            'updated_at' => now('Asia/Aden'),
        ]);

        // 5. Seed POS_ACCOUNTANT permissions
        $posPermissionKeys = [
            'pos_wallets.view',
            'pos_wallets.report',
            'pos_wallets.export',
            'sales.view',
            'sales.report',
            'sales.export',
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.report',
            'expenses.export',
            'notifications.view',
        ];

        $permissions = DB::table('permissions')->whereIn('permission_key', $posPermissionKeys)->get();
        foreach ($permissions as $p) {
            DB::table('role_permissions')->updateOrInsert([
                'role_id' => $posAccountantId,
                'permission_id' => $p->permission_id,
            ], [
                'created_at' => now('Asia/Aden'),
                'updated_at' => now('Asia/Aden'),
            ]);
        }

        // 6. Add composite unique constraint (role_id, scope)
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['role_id', 'scope'], 'uq_roles_id_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('uq_roles_id_scope');
        });

        $posAcc = DB::table('roles')->where('role_key', RoleKey::POS_ACCOUNTANT->value)->first();
        if ($posAcc) {
            DB::table('role_permissions')->where('role_id', $posAcc->role_id)->delete();
            DB::table('roles')->where('role_id', $posAcc->role_id)->delete();
        }

        DB::table('roles')->where('role_key', RoleKey::NETWORK_ACCOUNTANT->value)->update([
            'role_key' => 'ACCOUNTANT',
            'role_name' => 'محاسب',
            'scope' => 'PLATFORM',
        ]);

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
