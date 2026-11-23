<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('scope', 20)->default('SHARED')->after('permission_key');
            $table->index(['scope', 'status'], 'idx_permissions_scope_status');
        });

        $moduleScopes = [
            'PLATFORM' => [
                'auth',
                'users',
                'roles',
                'geography',
                'billing',
                'bank_accounts',
                'settings',
                'system_logs',
                'backups',
                'sms_settings',
                'scheduled_tasks',
                'notifications',
                'support',
            ],
            'NETWORK' => [
                'networks',
                'network_wallets',
                'expenses',
                'voucher_packages',
            ],
            'POS' => [
                'pos',
            ],
            'SHARED' => [
                'pos_wallets',
                'requests',
                'sales',
                'network_pos',
                'package_discounts',
                'support_tickets',
            ],
        ];

        foreach ($moduleScopes as $scope => $moduleKeys) {
            $moduleIds = DB::table('modules')
                ->whereIn('module_key', $moduleKeys)
                ->pluck('module_id');

            DB::table('permissions')
                ->whereIn('module_id', $moduleIds)
                ->update(['scope' => $scope]);
        }
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropIndex('idx_permissions_scope_status');
            $table->dropColumn('scope');
        });
    }
};
