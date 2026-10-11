<?php

namespace App\Enums;

enum RoleKey: string
{
    case SUPER_ADMIN = 'super_admin';
    case NETWORK_ADMIN = 'network_admin';
    case POS_AGENT = 'pos_agent';
    case SUPPORT_AGENT = 'support_agent';
    case ACCOUNTANT = 'accountant';

    public static function value(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'مدير النظام العام',
            self::NETWORK_ADMIN => 'مدير الشبكة',
            self::POS_AGENT => 'وكيل نقطة البيع',
            self::SUPPORT_AGENT => 'الدعم الفني',
            self::ACCOUNTANT => 'المحاسب',
        };
    }

    public static function labels(): array
    {
        return [
            self::SUPER_ADMIN->value => self::SUPER_ADMIN->label(),
            self::NETWORK_ADMIN->value => self::NETWORK_ADMIN->label(),
            self::POS_AGENT->value => self::POS_AGENT->label(),
            self::SUPPORT_AGENT->value => self::SUPPORT_AGENT->label(),
            self::ACCOUNTANT->value => self::ACCOUNTANT->label(),
        ];
    }
}
