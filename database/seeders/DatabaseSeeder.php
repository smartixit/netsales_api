<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database in a strict enterprise dependency order.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting NetSales Enterprise Core Seeding...');

        DB::transaction(function () {
            // 1. RBAC & Foundation Infrastructure
            $this->command->line('➜ Seeding Modules...');
            $this->call(ModuleSeeder::class);

            $this->command->line('➜ Seeding Action Types...');
            $this->call(ActionTypeSeeder::class);

            $this->command->line('➜ Seeding System Roles...');
            $this->call(RoleSeeder::class);

            $this->command->line('➜ Seeding Permissions Matrix...');
            $this->call(PermissionSeeder::class);

            $this->command->line('➜ Assigning Role Permissions...');
            $this->call(RolePermissionSeeder::class);

            // 2. Geographic Foundation
            $this->command->line('➜ Seeding Governorates...');
            $this->call(GovernorateSeeder::class);

            $this->command->line('➜ Seeding Directorates...');
            $this->call(DirectorateSeeder::class);

            // 3. Administrative Users
            $this->command->line('➜ Seeding Core Users...');
            $this->call(UserSeeder::class);

            // 4. System Settings & Configurations
            $this->command->line('➜ Seeding System Settings...');
            $this->call(SystemSettingSeeder::class);

            // 5. Multi-currency billing foundation
            $this->command->line('➜ Seeding supported billing currencies...');
            $this->call(CurrencySeeder::class);

            $this->command->line('➜ Seeding NetSales launch subscription catalog and trial policy...');
            $this->call(BillingLaunchCatalogSeeder::class);

            $this->command->line('➜ Seeding billing payment methods...');
            $this->call(BillingPaymentMethodSeeder::class);
        });

        $this->command->info('✅ NetSales Enterprise Database Seeding Completed Successfully!');
    }
}
