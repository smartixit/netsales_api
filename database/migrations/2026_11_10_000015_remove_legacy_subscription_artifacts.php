<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove the retired subscription-plan schema and its catalogue data.
        foreach ([
            'subscription_plan_feature_consumptions',
            'provider_subscriptions',
            'subscription_plan_requests',
            'subscription_plan_features',
            'subscription_plans',
            'features',
        ] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        if (Schema::hasTable('role_permissions') && Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->where('permission_key', 'like', 'subscriptions.%')
                ->pluck('permission_id');

            if ($permissionIds->isNotEmpty()) {
                DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
                DB::table('permissions')->whereIn('permission_id', $permissionIds)->delete();
            }
        }

        if (Schema::hasTable('modules')) {
            DB::table('modules')->where('module_key', 'subscriptions')->delete();
        }

        if (Schema::hasTable('scheduled_task_runs')) {
            DB::table('scheduled_task_runs')
                ->whereIn('task_type', ['subscriptions_expire_outdated', 'subscriptions_notify_expiring'])
                ->delete();
        }

        if (Schema::hasTable('network_sms_settings')
            && Schema::hasColumn('network_sms_settings', 'notify_subscription_expiry')) {
            Schema::table('network_sms_settings', function (Blueprint $table): void {
                $table->dropColumn('notify_subscription_expiry');
            });
        }
    }

    public function down(): void
    {
        // Retired legacy subscription data and permissions must never be resurrected.
    }
};
