<?php

namespace App\Enums;

enum NotificationType: string
{
    case SYSTEM = 'SYSTEM';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ACCOUNT_APPROVAL = 'ACCOUNT_APPROVAL';
    case ACCOUNT_REJECTION = 'ACCOUNT_REJECTION';
    case TRANSACTION = 'TRANSACTION';
    case SECURITY = 'SECURITY';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function value(): array
    {
        return self::values();
    }

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'نظام',
            self::INFO => 'معلومة',
            self::WARNING => 'تحذير',
            self::ACCOUNT_APPROVAL => 'موافقة على الحساب',
            self::ACCOUNT_REJECTION => 'رفض الحساب',
            self::TRANSACTION => 'عملية مالية',
            self::SECURITY => 'أمان وحماية',
        };
    }

    public static function labels(): array
    {
        return [
            self::SYSTEM->value => self::SYSTEM->label(),
            self::INFO->value => self::INFO->label(),
            self::WARNING->value => self::WARNING->label(),
            self::ACCOUNT_APPROVAL->value => self::ACCOUNT_APPROVAL->label(),
            self::ACCOUNT_REJECTION->value => self::ACCOUNT_REJECTION->label(),
            self::TRANSACTION->value => self::TRANSACTION->label(),
            self::SECURITY->value => self::SECURITY->label(),
        ];
    }
}
