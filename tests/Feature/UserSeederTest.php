<?php

namespace Tests\Feature;

use App\Enums\RoleScope;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_platform_admin_is_seeded_with_the_configured_super_admin_identity(): void
    {
        config([
            'netsales.platform_admin.phone' => '772957881',
            'netsales.platform_admin.email' => 'alzuraikyhussam@gmail.com',
            'netsales.platform_admin.name' => 'Platform Administrator',
            'netsales.platform_admin.password' => 'TestOnly-Platform-Admin-Password!',
        ]);

        app(RoleSeeder::class)->run();
        app(UserSeeder::class)->run();

        $admin = DB::table('users')
            ->where('user_phone_number', '772957881')
            ->first();

        $this->assertNotNull($admin);
        $this->assertSame('alzuraikyhussam@gmail.com', $admin->user_email);
        $this->assertSame('ACTIVE', $admin->status);
        $this->assertTrue(Hash::check('TestOnly-Platform-Admin-Password!', $admin->user_password));

        $superAdminRole = DB::table('roles')
            ->where('role_key', 'SUPER_ADMIN')
            ->first();

        $this->assertSame($superAdminRole->role_id, $admin->role_id);
        $this->assertSame(RoleScope::PLATFORM->value, $admin->role_scope);

        app(UserSeeder::class)->run();

        $this->assertSame(
            1,
            DB::table('users')->where('user_phone_number', '772957881')->count(),
            'Rerunning the seeder must not create duplicate platform administrator accounts.'
        );
    }

    public function test_platform_admin_is_not_created_without_a_configured_password(): void
    {
        config([
            'netsales.platform_admin.phone' => '772957881',
            'netsales.platform_admin.email' => 'alzuraikyhussam@gmail.com',
            'netsales.platform_admin.password' => null,
        ]);

        app(RoleSeeder::class)->run();
        app(UserSeeder::class)->run();

        $this->assertDatabaseMissing('users', [
            'user_phone_number' => '772957881',
        ]);
    }
}
