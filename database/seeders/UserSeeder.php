<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = DB::table('roles')
            ->get(['role_id', 'role_key'])
            ->keyBy('role_key');

        $superAdminRole = $roles->get('SUPER_ADMIN');

        if ($superAdminRole === null) {
            throw new RuntimeException('The SUPER_ADMIN role must be seeded before users.');
        }

        // ضمان وجود حساب النظام الآلي (SYSTEM_USER_ID = 0) لعمليات النظام المؤتمتة وحماية قيود المفاتيح الأجنبية
        DB::statement('SET SESSION sql_mode = IFNULL(CONCAT(@@SESSION.sql_mode, ",NO_AUTO_VALUE_ON_ZERO"), "NO_AUTO_VALUE_ON_ZERO")');
        DB::table('users')->updateOrInsert(
            ['user_id' => 0],
            [
                'role_id' => $superAdminRole->role_id,
                'user_full_name' => 'النظام الآلي - System Daemon',
                'user_phone_number' => '000000000',
                'user_password' => Hash::make('locked_system_daemon_'.config('app.key')),
                'user_email' => 'system@netsales.local',
                'user_profile_logo' => null,
                'user_2fa_enabled' => false,
                'user_login_attempts' => 0,
                'status' => 'ACTIVE',
                'updated_at' => now('Asia/Aden'),
                'created_at' => now('Asia/Aden'),
            ]
        );

        $adminPhone = config('netsales.platform_admin.phone');
        $adminPassword = config('netsales.platform_admin.password');

        // Never create or overwrite a platform administrator without an explicitly configured password.
        if (is_string($adminPhone) && trim($adminPhone) !== ''
            && is_string($adminPassword) && trim($adminPassword) !== '') {
            DB::table('users')->updateOrInsert(
                ['user_phone_number' => $adminPhone],
                [
                    'role_id' => $superAdminRole->role_id,
                    'user_full_name' => (string) config('netsales.platform_admin.name'),
                    'user_password' => Hash::make($adminPassword),
                    'user_email' => config('netsales.platform_admin.email'),
                    'user_profile_logo' => null,
                    'user_2fa_enabled' => false,
                    'user_login_attempts' => 0,
                    'status' => 'ACTIVE',
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ]
            );
        }
    }
}
