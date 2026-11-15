<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "Testing Seed Script via DB Transaction (Dry Run)...\n";

try {
    DB::beginTransaction();

    $now = date('Y-m-d H:i:s');
    $testPhone = '771122334';
    $passwordHash = Hash::make('Password@123');

    // 1. Roles
    $netOwnerRole = DB::table('roles')->where('role_key', 'NETWORK_OWNER')->first();
    $posAgentRole = DB::table('roles')->where('role_key', 'POS_AGENT')->first();
    if (!$netOwnerRole || !$posAgentRole) {
        throw new Exception("Roles NETWORK_OWNER or POS_AGENT missing in roles table");
    }

    // 2. Governorates & Directorates
    $gov = DB::table('governorates')->first();
    if (!$gov) {
        $govId = DB::table('governorates')->insertGetId([
            'governorate_name' => 'أمانة العاصمة',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } else {
        $govId = $gov->governorate_id;
    }

    $dir = DB::table('directorates')->where('governorate_id', $govId)->first();
    if (!$dir) {
        $dirId = DB::table('directorates')->insertGetId([
            'governorate_id' => $govId,
            'directorate_name' => 'التحرير',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } else {
        $dirId = $dir->directorate_id;
    }

    // 3. User
    DB::table('users')->where('user_phone_number', $testPhone)->delete();
    $userId = DB::table('users')->insertGetId([
        'user_full_name' => 'م. عمار علي غالب الشميري',
        'user_phone_number' => $testPhone,
        'user_email' => 'ammar.test@netsales.local',
        'user_password' => $passwordHash,
        'status' => 'ACTIVE',
        'user_2fa_enabled' => false,
        'user_login_attempts' => 0,
        'role_id' => null,
        'role_scope' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // POS 2 owner user (because owner_id is unique on pos table)
    $pos2OwnerPhone = '772233445';
    DB::table('users')->where('user_phone_number', $pos2OwnerPhone)->delete();
    $pos2OwnerId = DB::table('users')->insertGetId([
        'user_full_name' => 'مالك نقطة المستقبل',
        'user_phone_number' => $pos2OwnerPhone,
        'user_email' => 'future.pos@netsales.local',
        'user_password' => $passwordHash,
        'status' => 'ACTIVE',
        'user_2fa_enabled' => false,
        'user_login_attempts' => 0,
        'role_id' => null,
        'role_scope' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // 4. Networks
    $netId1 = DB::table('networks')->insertGetId([
        'network_name' => 'شبكة الأفق الذكية',
        'network_uuid' => 'NET-HORIZON-001',
        'network_phone_number' => '771122334',
        'owner_id' => $userId,
        'governorate_id' => $govId,
        'directorate_id' => $dirId,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $netId2 = DB::table('networks')->insertGetId([
        'network_name' => 'شبكة النجم السريع',
        'network_uuid' => 'NET-STAR-002',
        'network_phone_number' => '771122335',
        'owner_id' => $userId,
        'governorate_id' => $govId,
        'directorate_id' => $dirId,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // 5. Network Wallets
    DB::table('network_wallets')->insert([
        [
            'network_id' => $netId1,
            'wallet_uuid' => 'NWL-HORIZON-001',
            'wallet_balance' => 850000.00,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'network_id' => $netId2,
            'wallet_uuid' => 'NWL-STAR-002',
            'wallet_balance' => 320000.00,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    // 6. Network Memberships
    DB::table('network_users')->insert([
        [
            'network_id' => $netId1,
            'user_id' => $userId,
            'role_id' => $netOwnerRole->role_id,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'network_id' => $netId2,
            'user_id' => $userId,
            'role_id' => $netOwnerRole->role_id,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    // 7. POS
    $posId1 = DB::table('pos')->insertGetId([
        'pos_name' => 'مركز الأمل للاتصالات والخدمات',
        'pos_uuid' => 'POS-AMAL-001',
        'pos_phone_number' => '773344556',
        'owner_id' => $userId,
        'governorate_id' => $govId,
        'directorate_id' => $dirId,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $posId2 = DB::table('pos')->insertGetId([
        'pos_name' => 'معرض المستقبل التقني',
        'pos_uuid' => 'POS-FUTURE-002',
        'pos_phone_number' => $pos2OwnerPhone,
        'owner_id' => $pos2OwnerId,
        'governorate_id' => $govId,
        'directorate_id' => $dirId,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // 8. POS Memberships
    DB::table('pos_users')->insert([
        [
            'pos_id' => $posId1,
            'user_id' => $userId,
            'role_id' => $posAgentRole->role_id,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'pos_id' => $posId2,
            'user_id' => $userId,
            'role_id' => $posAgentRole->role_id,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    // 9. Network POS Links & POS Wallets
    DB::table('network_pos')->insert([
        [
            'network_id' => $netId1,
            'pos_id' => $posId1,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'network_id' => $netId2,
            'pos_id' => $posId1,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'network_id' => $netId1,
            'pos_id' => $posId2,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('pos_wallets')->insert([
        [
            'network_id' => $netId1,
            'pos_id' => $posId1,
            'wallet_uuid' => 'PWL-AMAL-HORIZON-01',
            'wallet_balance' => 75000.00,
            'credit_limit' => 500000.00,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'network_id' => $netId2,
            'pos_id' => $posId1,
            'wallet_uuid' => 'PWL-AMAL-STAR-02',
            'wallet_balance' => 38000.00,
            'credit_limit' => 300000.00,
            'status' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    // 10. Voucher Packages
    $pkgId1 = DB::table('voucher_packages')->insertGetId([
        'network_id' => $netId1,
        'package_name' => 'باقة الأفق الفضية 1 جيجا',
        'package_color' => '0xFF0D7A5F',
        'package_voucher_price' => 500.00,
        'package_voucher_count' => 50,
        'package_default_cash_discount' => 5.00,
        'package_default_credit_discount' => 2.00,
        'status' => true,
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // 11. Requests & Items
    $reqId1 = DB::table('requests')->insertGetId([
        'network_id' => $netId1,
        'pos_id' => $posId1,
        'request_uuid' => 'REQ-20261115-001',
        'sale_type' => 'CREDIT',
        'request_total_amount' => 25000.00,
        'request_date' => $now,
        'status' => 'PENDING',
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('request_items')->insert([
        'request_id' => $reqId1,
        'package_id' => $pkgId1,
        'voucher_price' => 500.00,
        'voucher_count' => 50,
        'item_discount' => 0.00,
        'item_total_amount' => 25000.00,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // 12. Sale Invoices & Items
    $invId1 = DB::table('sale_invoices')->insertGetId([
        'network_id' => $netId1,
        'pos_id' => $posId1,
        'invoice_uuid' => 'INV-20261115-001',
        'sale_type' => 'CASH',
        'invoice_total_amount' => 50000.00,
        'paid_amount' => 50000.00,
        'remaining_amount' => 0.00,
        'payment_status' => 'PAID',
        'invoice_date' => $now,
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('sale_invoice_items')->insert([
        'invoice_id' => $invId1,
        'package_id' => $pkgId1,
        'voucher_price' => 500.00,
        'voucher_count' => 100,
        'item_discount' => 0.00,
        'item_total_amount' => 50000.00,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // 13. Expenses & Items
    $expId1 = DB::table('expenses')->insertGetId([
        'network_id' => $netId1,
        'expense_uuid' => 'EXP-20261115-001',
        'expense_total_amount' => 15000.00,
        'expense_date' => $now,
        'created_by' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('expense_items')->insert([
        'expense_id' => $expId1,
        'item_statement' => 'صيانة المولد وخط الإنترنت الرئيسي',
        'item_amount' => 15000.00,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "SUCCESS: All tables, columns, constraints and relationships validated successfully!\n";
    DB::rollBack();
    echo "Transaction rolled back cleanly.\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
