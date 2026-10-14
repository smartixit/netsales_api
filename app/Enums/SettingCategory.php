<?php

namespace App\Enums;

enum SettingCategory: string
{
    case GENERAL = 'GENERAL';
    case FINANCIAL = 'FINANCIAL';
    case SECURITY = 'SECURITY';
    case NOTIFICATION = 'NOTIFICATION';
    case SUBSCRIPTION = 'SUBSCRIPTION';
    case SYSTEM = 'SYSTEM';

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
            self::GENERAL => 'عام',
            self::FINANCIAL => 'مالي',
            self::SECURITY => 'أمني',
            self::NOTIFICATION => 'إشعارات',
            self::SUBSCRIPTION => 'اشتراكات',
            self::SYSTEM => 'نظام',
        };
    }

    public static function labels(): array
    {
        return [
            self::GENERAL->value => self::GENERAL->label(),
            self::FINANCIAL->value => self::FINANCIAL->label(),
            self::SECURITY->value => self::SECURITY->label(),
            self::NOTIFICATION->value => self::NOTIFICATION->label(),
            self::SUBSCRIPTION->value => self::SUBSCRIPTION->label(),
            self::SYSTEM->value => self::SYSTEM->label(),
        ];
    }
}
