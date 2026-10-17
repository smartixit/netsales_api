<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = DB::table('roles')->pluck('role_id', 'role_key');

        // ضمان وجود حساب النظام الآلي (SYSTEM_USER_ID = 0) لعمليات النظام المؤتمتة وحماية قيود المفاتيح الأجنبية
        $superAdminRoleId = $roles['SUPER_ADMIN'] ?? 1;
        DB::statement('SET SESSION sql_mode = IFNULL(CONCAT(@@SESSION.sql_mode, ",NO_AUTO_VALUE_ON_ZERO"), "NO_AUTO_VALUE_ON_ZERO")');
        DB::table('users')->updateOrInsert(
            ['user_id' => 0],
            [
                'role_id' => $superAdminRoleId,
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

        $users = [
            [
                'role_key' => 'SUPER_ADMIN',
                'user_full_name' => 'حسام خالد سعيد علي الزريقي',
                'user_phone_number' => '772957881',
                'user_password' => 'Hh$2002Hh',
                'user_email' => 'alzuraikyhussam@gmail.com',
                'user_profile_logo' => null,
                'user_2fa_enabled' => false,
                'user_login_attempts' => 0,
                'status' => 'ACTIVE',
            ],
            // [
            //     'role_key'            => 'system_admin',
            //     'user_full_name'      => 'أدمن النظام الإداري',
            //     'user_phone_number'   => '770000002',
            //     'user_password'       => 'Admin@2026!',
            //     'user_email'          => 'admin@netsales.local',
            //     'user_profile_logo'   => null,
            //     'user_2fa_enabled'    => false,
            //     'user_login_attempts' => 0,
            //     'status'              => 'ACTIVE',
            // ],
            // [
            //     'role_key'            => 'accountant',
            //     'user_full_name'      => 'المسؤول المالي والمحاسبي',
            //     'user_phone_number'   => '770000003',
            //     'user_password'       => 'Finance@2026',
            //     'user_email'          => 'finance@netsales.local',
            //     'user_profile_logo'   => null,
            //     'user_2fa_enabled'    => false,
            //     'user_login_attempts' => 0,
            //     'status'              => 'ACTIVE',
            // ],
            // [
            //     'role_key'            => 'support_agent',
            //     'user_full_name'      => 'مسؤول خدمة العملاء والدعم',
            //     'user_phone_number'   => '770000004',
            //     'user_password'       => 'Support@2026',
            //     'user_email'          => 'support@netsales.local',
            //     'user_profile_logo'   => null,
            //     'user_2fa_enabled'    => false,
            //     'user_login_attempts' => 0,
            //     'status'              => 'ACTIVE',
            // ],
        ];

        foreach ($users as $user) {
            $roleId = $roles[$user['role_key']] ?? null;
            if (! $roleId) {
                continue;
            }

            DB::table('users')->updateOrInsert(
                ['user_phone_number' => $user['user_phone_number']],
                [
                    'role_id' => $roleId,
                    'user_full_name' => $user['user_full_name'],
                    'user_password' => Hash::make($user['user_password']),
                    'user_email' => $user['user_email'],
                    'user_profile_logo' => $user['user_profile_logo'],
                    'user_2fa_enabled' => $user['user_2fa_enabled'],
                    'user_login_attempts' => $user['user_login_attempts'],
                    'status' => $user['status'],
                    'updated_at' => now('Asia/Aden'),
                    'created_at' => now('Asia/Aden'),
                ]
            );
        }
    }
}
