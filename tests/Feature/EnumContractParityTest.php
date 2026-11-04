<?php

namespace Tests\Feature;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\BankAccountType;
use App\Enums\BanType;
use App\Enums\DeviceType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\NetworkStatus;
use App\Enums\NotificationType;
use App\Enums\OtpType;
use App\Enums\PaymentType;
use App\Enums\PosStatus;
use App\Enums\ProviderSubscriptionStatus;
use App\Enums\RequestStatus;
use App\Enums\RoleKey;
use App\Enums\RoleScope;
use App\Enums\SaleType;
use App\Enums\ScheduledTaskStatus;
use App\Enums\ScheduledTaskType;
use App\Enums\SettingCategory;
use App\Enums\SmsMessageType;
use App\Enums\UserDeviceStatus;
use App\Enums\UserStatus;
use App\Enums\ValueType;
use App\Enums\WalletOperationType;
use App\Http\Resources\API\V1\Backup\BackupJobResource;
use App\Http\Resources\API\V1\Network\NetworkResource;
use App\Http\Resources\API\V1\Network\NetworkWalletLogResource;
use App\Http\Resources\API\V1\Pos\PosResource;
use App\Http\Resources\API\V1\Pos\PosWalletLogResource;
use App\Http\Resources\API\V1\User\UserDeviceResource;
use App\Models\BackupJob;
use App\Models\Network;
use App\Models\NetworkWalletLog;
use App\Models\Pos;
use App\Models\PosWalletLog;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnumContractParityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Exact 24 Canonical PHP Backed Enums in app/Enums/
     */
    public const ALL_24_ENUMS = [
        BackupStatus::class,
        BackupType::class,
        BankAccountType::class,
        BanType::class,
        DeviceType::class,
        InvoicePaymentStatus::class,
        NetworkStatus::class,
        NotificationType::class,
        OtpType::class,
        PaymentType::class,
        PosStatus::class,
        ProviderSubscriptionStatus::class,
        RequestStatus::class,
        RoleKey::class,
        RoleScope::class,
        SaleType::class,
        ScheduledTaskStatus::class,
        ScheduledTaskType::class,
        SettingCategory::class,
        SmsMessageType::class,
        UserDeviceStatus::class,
        UserStatus::class,
        ValueType::class,
        WalletOperationType::class,
    ];

    public function test_all_24_enums_exist_and_are_backed(): void
    {
        $this->assertCount(24, self::ALL_24_ENUMS);

        foreach (self::ALL_24_ENUMS as $enumClass) {
            $this->assertTrue(enum_exists($enumClass), "Enum {$enumClass} must exist.");
            $reflection = new \ReflectionEnum($enumClass);
            $this->assertTrue($reflection->isBacked(), "Enum {$enumClass} must be backed.");
            $this->assertSame('string', $reflection->getBackingType()->getName(), "Enum {$enumClass} must be string backed.");
        }
    }

    public function test_all_24_enums_implement_non_empty_arabic_label(): void
    {
        foreach (self::ALL_24_ENUMS as $enumClass) {
            $cases = $enumClass::cases();
            $this->assertNotEmpty($cases, "Enum {$enumClass} must have cases.");

            foreach ($cases as $case) {
                $this->assertTrue(method_exists($case, 'label'), "Enum case {$enumClass}::{$case->name} must implement label().");
                $label = $case->label();
                $this->assertIsString($label);
                $this->assertNotEmpty($label, "Label for {$enumClass}::{$case->name} cannot be empty.");
            }
        }
    }

    public function test_exact_wire_casing_rules(): void
    {
        // 1. Lowercase Enums
        foreach (ScheduledTaskStatus::cases() as $case) {
            $this->assertSame(strtolower($case->value), $case->value, 'ScheduledTaskStatus must be strictly lowercase.');
        }

        foreach (SmsMessageType::cases() as $case) {
            $this->assertSame(strtolower($case->value), $case->value, 'SmsMessageType must be strictly lowercase.');
        }

        // 2. Snake_case Enums
        foreach (ScheduledTaskType::cases() as $case) {
            $this->assertSame(strtolower($case->value), $case->value, 'ScheduledTaskType must be strictly lowercase snake_case.');
            $this->assertMatchesRegularExpression('/^[a-z]+(_[a-z]+)*$/', $case->value);
        }

        // 3. UPPERCASE Enums
        $uppercaseEnums = [
            NetworkStatus::class,
            PosStatus::class,
            UserStatus::class,
            UserDeviceStatus::class,
            DeviceType::class,
            RoleKey::class,
            RoleScope::class,
            SaleType::class,
            RequestStatus::class,
            InvoicePaymentStatus::class,
            PaymentType::class,
            WalletOperationType::class,
            OtpType::class,
            BanType::class,
            BankAccountType::class,
            ProviderSubscriptionStatus::class,
            NotificationType::class,
            SettingCategory::class,
            ValueType::class,
            BackupStatus::class,
            BackupType::class,
        ];

        foreach ($uppercaseEnums as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                $this->assertSame(strtoupper($case->value), $case->value, "Enum {$enumClass}::{$case->name} must be strictly UPPERCASE.");
            }
        }
    }

    public function test_otp_type_exact_canonical_cases(): void
    {
        $expected = [
            'RESET_PASSWORD' => 'RESET_PASSWORD',
            'OTP_2FA' => 'OTP_2FA',
            'VERIFY_EMAIL' => 'VERIFY_EMAIL',
            'VERIFY_PHONE' => 'VERIFY_PHONE',
        ];

        $actual = [];
        foreach (OtpType::cases() as $case) {
            $actual[$case->name] = $case->value;
        }

        $this->assertSame($expected, $actual);
    }

    public function test_model_casts_and_resource_serialization_contract(): void
    {
        // 1. NetworkResource
        $network = new Network([
            'status' => NetworkStatus::ACTIVE,
        ]);
        $networkRes = (new NetworkResource($network))->toArray(Request::create('/'));
        $this->assertSame('ACTIVE', $networkRes['status']);
        $this->assertSame('مفعّل', $networkRes['status_label']);

        // 2. PosResource
        $pos = new Pos([
            'status' => PosStatus::PENDING,
        ]);
        $posRes = (new PosResource($pos))->toArray(Request::create('/'));
        $this->assertSame('PENDING', $posRes['status']);
        $this->assertSame('قيد الانتظار', $posRes['status_label']);

        // 3. UserDeviceResource
        $device = new UserDevice([
            'device_type' => DeviceType::ANDROID,
            'status' => UserDeviceStatus::ACTIVE,
        ]);
        $deviceRes = (new UserDeviceResource($device))->toArray(Request::create('/'));
        $this->assertSame('ANDROID', $deviceRes['device_type']);
        $this->assertSame('أندرويد', $deviceRes['device_type_label']);
        $this->assertSame('ACTIVE', $deviceRes['status']);
        $this->assertSame('مفعّل', $deviceRes['status_label']);

        // 4. BackupJobResource
        $backupJob = new BackupJob([
            'backup_type' => BackupType::AUTO,
            'status' => BackupStatus::SUCCESS,
        ]);
        $backupRes = (new BackupJobResource($backupJob))->toArray(Request::create('/'));
        $this->assertSame('AUTO', $backupRes['backup_type']);
        $this->assertSame('تلقائي', $backupRes['backup_type_label']);
        $this->assertSame('SUCCESS', $backupRes['status']);
        $this->assertSame('تم بنجاح', $backupRes['status_label']);

        // 5. WalletLog Resources field name preservation
        $netLog = new NetworkWalletLog([
            'log_operation' => WalletOperationType::DEPOSIT,
            'log_amount' => 500.0,
        ]);
        $netLogRes = (new NetworkWalletLogResource($netLog))->toArray(Request::create('/'));
        $this->assertArrayHasKey('log_operation', $netLogRes);
        $this->assertArrayHasKey('log_operation_label', $netLogRes);
        $this->assertSame('DEPOSIT', $netLogRes['log_operation']);
        $this->assertSame('إيداع', $netLogRes['log_operation_label']);

        $posLog = new PosWalletLog([
            'log_operation_type' => WalletOperationType::WITHDRAW,
            'log_amount' => 150.0,
        ]);
        $posLogRes = (new PosWalletLogResource($posLog))->toArray(Request::create('/'));
        $this->assertArrayHasKey('log_operation_type', $posLogRes);
        $this->assertArrayHasKey('log_operation_type_label', $posLogRes);
        $this->assertSame('WITHDRAW', $posLogRes['log_operation_type']);
        $this->assertSame('سحب', $posLogRes['log_operation_type_label']);
    }
}
