<?php

namespace Tests\Feature;

use App\Enums\SettingCategory;
use App\Enums\ValueType;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SystemSettingSystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();

        $auth = $this->createAuthenticatedSession($this->user);
        $this->session = $auth['session'];
        $this->headers = $auth['headers'];
    }

    public function test_can_create_setting_and_list_and_show(): void
    {
        $payload = [
            'setting_category' => SettingCategory::SECURITY->value,
            'setting_key' => 'test_feature_max_login_attempts',
            'setting_value_type' => ValueType::INTEGER->value,
            'setting_value' => '5',
            'setting_desc' => 'الحد الأقصى لمحاولات تسجيل الدخول الفاشلة قبل القفل',
        ];

        $response = $this->postJson('/api/v1/system-settings', $payload, $this->headers);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.setting_key', 'test_feature_max_login_attempts')
            ->assertJsonPath('data.typed_value', 5);

        $settingId = $response->json('data.setting_id');

        // Test List API
        $listResponse = $this->getJson('/api/v1/system-settings?search=test_feature', $this->headers);
        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($listResponse->json('data'));

        // Test Show by ID
        $showResponse = $this->getJson("/api/v1/system-settings/{$settingId}", $this->headers);
        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.setting_id', $settingId);

        // Test Show by Key
        $byKeyResponse = $this->getJson('/api/v1/system-settings/key/test_feature_max_login_attempts', $this->headers);
        $byKeyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.setting_key', 'test_feature_max_login_attempts');

        // Test By Category
        $byCatResponse = $this->getJson('/api/v1/system-settings/category/SECURITY', $this->headers);
        $byCatResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_system_setting_helper_function_and_types(): void
    {
        SystemSetting::create([
            'setting_category' => SettingCategory::GENERAL->value,
            'setting_key' => 'test_maintenance_mode',
            'setting_value_type' => ValueType::BOOLEAN->value,
            'setting_value' => 'true',
            'setting_desc' => 'حالة وضع الصيانة',
        ]);

        SystemSetting::create([
            'setting_category' => SettingCategory::FINANCIAL->value,
            'setting_key' => 'test_tax_rate',
            'setting_value_type' => ValueType::DECIMAL->value,
            'setting_value' => '15.5',
            'setting_desc' => 'نسبة الضريبة المضافة',
        ]);

        SystemSetting::create([
            'setting_category' => SettingCategory::SYSTEM->value,
            'setting_key' => 'test_supported_currencies',
            'setting_value_type' => ValueType::JSON->value,
            'setting_value' => json_encode(['YER', 'SAR', 'USD']),
            'setting_desc' => 'العملات المعتمدة',
        ]);

        // Clear cache so newly added items load properly
        $this->postJson('/api/v1/system-settings/clear-cache', [], $this->headers)->assertStatus(200);

        // Test helper function
        $this->assertTrue(systemSetting('test_maintenance_mode'));
        $this->assertEquals(15.5, systemSetting('test_tax_rate'));
        $this->assertEquals(['YER', 'SAR', 'USD'], systemSetting('test_supported_currencies'));
        $this->assertEquals('fallback_val', systemSetting('non_existing_key_xyz', 'fallback_val'));
    }

    public function test_can_update_setting_and_batch_update(): void
    {
        $setting = SystemSetting::create([
            'setting_category' => SettingCategory::GENERAL->value,
            'setting_key' => 'test_app_title',
            'setting_value_type' => ValueType::TEXT->value,
            'setting_value' => 'NetSales Initial',
            'setting_desc' => 'اسم المنصة العام',
        ]);

        // Update single setting
        $updatePayload = [
            'setting_value' => 'NetSales Enterprise Platform',
            'setting_desc' => 'اسم المنصة المحدث',
        ];

        $updateResponse = $this->putJson("/api/v1/system-settings/{$setting->setting_id}", $updatePayload, $this->headers);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.setting_value', 'NetSales Enterprise Platform')
            ->assertJsonPath('data.setting_desc', 'اسم المنصة المحدث');

        // Batch update
        $batchPayload = [
            'settings' => [
                [
                    'key' => 'test_app_title',
                    'value' => 'NetSales Pro Suite',
                ],
            ],
        ];

        $batchResponse = $this->postJson('/api/v1/system-settings/batch-update', $batchPayload, $this->headers);
        $batchResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.updated_count', 1);

        $this->assertEquals('NetSales Pro Suite', systemSetting('test_app_title'));
    }

    public function test_soft_delete_and_restore_and_force_delete(): void
    {
        $setting = SystemSetting::create([
            'setting_category' => SettingCategory::NOTIFICATION->value,
            'setting_key' => 'test_temp_notification_flag',
            'setting_value_type' => ValueType::BOOLEAN->value,
            'setting_value' => 'false',
            'setting_desc' => 'إعداد مؤقت للاختبار',
        ]);

        $id = $setting->setting_id;

        // Delete soft
        $delResponse = $this->deleteJson("/api/v1/system-settings/{$id}", [], $this->headers);
        $delResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Check trashed
        $trashedResponse = $this->getJson('/api/v1/system-settings/trashed?search=test_temp', $this->headers);
        $trashedResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($trashedResponse->json('data'));

        // Restore
        $restoreResponse = $this->postJson("/api/v1/system-settings/{$id}/restore", [], $this->headers);
        $restoreResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Force delete
        $forceResponse = $this->deleteJson("/api/v1/system-settings/{$id}/force", [], $this->headers);
        $forceResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNull(SystemSetting::withTrashed()->find($id));
    }

    public function test_bulk_operations(): void
    {
        $s1 = SystemSetting::create([
            'setting_category' => SettingCategory::GENERAL->value,
            'setting_key' => 'test_bulk_1',
            'setting_value_type' => ValueType::TEXT->value,
            'setting_value' => 'val1',
            'setting_desc' => 'bulk test 1',
        ]);

        $s2 = SystemSetting::create([
            'setting_category' => SettingCategory::GENERAL->value,
            'setting_key' => 'test_bulk_2',
            'setting_value_type' => ValueType::TEXT->value,
            'setting_value' => 'val2',
            'setting_desc' => 'bulk test 2',
        ]);

        $ids = [$s1->setting_id, $s2->setting_id];

        // Batch delete
        $batchDel = $this->postJson('/api/v1/system-settings/batch-delete', ['ids' => $ids], $this->headers);
        $batchDel->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 2);

        // Batch restore
        $batchRestore = $this->postJson('/api/v1/system-settings/batch-restore', ['ids' => $ids], $this->headers);
        $batchRestore->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.restored_count', 2);

        // Batch force delete
        $batchForce = $this->postJson('/api/v1/system-settings/batch-force-delete', ['ids' => $ids], $this->headers);
        $batchForce->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.force_deleted_count', 2);

        $this->assertEmpty(SystemSetting::withTrashed()->whereIn('setting_id', $ids)->get());
    }

    public function test_can_fetch_public_settings_without_authentication(): void
    {
        $response = $this->getJson('/api/v1/system-settings/public');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'app_name',
                    'app_company_name',
                    'app_support_phone',
                    'app_support_whatsapp',
                    'app_support_email',
                    'default_locale',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.app_support_phone'));
        $this->assertNotEmpty($response->json('data.app_support_whatsapp'));
    }
}
