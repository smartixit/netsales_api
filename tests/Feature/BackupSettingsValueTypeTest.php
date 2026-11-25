<?php

namespace Tests\\Feature;

use App\\Enums\\ValueType;
use App\\Interfaces\\BackupJobRepositoryInterface;
use App\\Interfaces\\SystemSettingRepositoryInterface;
use App\\Models\\SystemSetting;
use App\\Services\\Backup\\BackupSettingsService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Mockery;
use Tests\\TestCase;

class BackupSettingsValueTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_settings_default_to_a_valid_text_enum(): void
    {
        $repository = Mockery::mock(SystemSettingRepositoryInterface::class);
        $repository->shouldReceive('updateOrCreateByKey')
            ->once()
            ->with('backup.runtime', Mockery::on(
                fn (array $data): bool => $data['setting_value_type'] === ValueType::TEXT->value
            ));

        $service = new BackupSettingsService(
            $repository,
            Mockery::mock(BackupJobRepositoryInterface::class)
        );

        $service->set('backup.runtime', '{}');
    }

    public function test_legacy_string_type_is_normalized_to_text(): void
    {
        $repository = Mockery::mock(SystemSettingRepositoryInterface::class);
        $repository->shouldReceive('updateOrCreateByKey')
            ->once()
            ->with('backup.runtime', Mockery::on(
                fn (array $data): bool => $data['setting_value_type'] === ValueType::TEXT->value
            ));

        $service = new BackupSettingsService(
            $repository,
            Mockery::mock(BackupJobRepositoryInterface::class)
        );

        $service->set('backup.runtime', '{}', 'string');
    }

    public function test_unsupported_backup_setting_type_is_rejected(): void
    {
        $repository = Mockery::mock(SystemSettingRepositoryInterface::class);
        $service = new BackupSettingsService(
            $repository,
            Mockery::mock(BackupJobRepositoryInterface::class)
        );

        $this->expectException(\\InvalidArgumentException::class);

        $service->set('backup.runtime', '{}', 'unsupported');
    }
}
