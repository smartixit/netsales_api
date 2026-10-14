<?php

namespace App\Enums;

enum OtpType: string
{
    case RESET_PASSWORD = 'RESET_PASSWORD';
    case OTP_2FA = 'OTP_2FA';
    case VERIFY_EMAIL = 'VERIFY_EMAIL';
    case VERIFY_PHONE = 'VERIFY_PHONE';

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
            self::RESET_PASSWORD => 'إعادة تعيين كلمة المرور',
            self::OTP_2FA => 'التحقق بخطوتين',
            self::VERIFY_EMAIL => 'التحقق من البريد الإلكتروني',
            self::VERIFY_PHONE => 'التحقق من رقم الهاتف',
        };
    }

    public static function labels(): array
    {
        return [
            self::RESET_PASSWORD->value => self::RESET_PASSWORD->label(),
            self::OTP_2FA->value => self::OTP_2FA->label(),
            self::VERIFY_EMAIL->value => self::VERIFY_EMAIL->label(),
            self::VERIFY_PHONE->value => self::VERIFY_PHONE->label(),
        ];
    }
}
