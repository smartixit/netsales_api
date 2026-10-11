<?php

namespace App\Enums;

enum DeviceType: string
{
    case ANDROID = 'ANDROID';
    case IOS = 'IOS';
    case WEB = 'WEB';
    case WINDOWS = 'WINDOWS';
    case LINUX = 'LINUX';
    case MAC = 'MAC';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::ANDROID => 'أندرويد',
            self::IOS => 'آيفون',
            self::WEB => 'متصفح ويب',
            self::WINDOWS => 'ويندوز',
            self::LINUX => 'لينكس',
            self::MAC => 'ماك',
        };
    }

    public static function labels(): array
    {
        return [
            self::ANDROID->value => self::ANDROID->label(),
            self::IOS->value => self::IOS->label(),
            self::WEB->value => self::WEB->label(),
            self::WINDOWS->value => self::WINDOWS->label(),
            self::LINUX->value => self::LINUX->label(),
            self::MAC->value => self::MAC->label(),
        ];
    }
}
