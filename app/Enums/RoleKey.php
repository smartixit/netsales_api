<?php

namespace App\Enums;

enum RoleKey: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case NETWORK_OWNER = 'NETWORK_OWNER';
    case NETWORK_USER = 'NETWORK_USER';
    case POS_AGENT = 'POS_AGENT';
    case POS_USER = 'POS_USER';
    case SUPPORT_AGENT = 'SUPPORT_AGENT';
    case ACCOUNTANT = 'ACCOUNTANT';

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
            self::SUPER_ADMIN => 'مدير النظام العام',
            self::NETWORK_OWNER => 'مزود شبكة',
            self::NETWORK_USER => 'مستخدم شبكة',
            self::POS_AGENT => 'وكيل نقطة بيع',
            self::POS_USER => 'مستخدم نقطة بيع',
            self::SUPPORT_AGENT => 'دعم فني',
            self::ACCOUNTANT => 'محاسب',
        };
    }

    public static function labels(): array
    {
        return [
            self::SUPER_ADMIN->value => self::SUPER_ADMIN->label(),
            self::NETWORK_OWNER->value => self::NETWORK_OWNER->label(),
            self::NETWORK_USER->value => self::NETWORK_USER->label(),
            self::POS_AGENT->value => self::POS_AGENT->label(),
            self::POS_USER->value => self::POS_USER->label(),
            self::SUPPORT_AGENT->value => self::SUPPORT_AGENT->label(),
            self::ACCOUNTANT->value => self::ACCOUNTANT->label(),
        ];
    }
}
