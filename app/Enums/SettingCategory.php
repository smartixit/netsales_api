<?php

namespace App\Enums;

enum SettingCategory: string
{
    case GENERAL = 'GENERAL';
    case FINANCIAL = 'FINANCIAL';
    case SECURITY = 'SECURITY';
    case NOTIFICATIONS = 'NOTIFICATIONS';
    case SUBSCRIPTION = 'SUBSCRIPTION';

    public static function value(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'عام',
            self::FINANCIAL => 'مالي',
            self::SECURITY => 'امني',
            self::NOTIFICATIONS => 'اشعارات',
            self::SUBSCRIPTION => 'اشتراكات',
        };
    }

    public static function labels(): array
    {
        return [
            self::GENERAL->value => self::GENERAL->label(),
            self::FINANCIAL->value => self::FINANCIAL->label(),
            self::SECURITY->value => self::SECURITY->label(),
            self::NOTIFICATIONS->value => self::NOTIFICATIONS->label(),
            self::SUBSCRIPTION->value => self::SUBSCRIPTION->label(),
        ];
    }
}
