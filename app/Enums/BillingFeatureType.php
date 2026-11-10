<?php

namespace App\Enums;

enum BillingFeatureType: string
{
    case BOOLEAN = 'BOOLEAN';
    case RESOURCE_LIMIT = 'RESOURCE_LIMIT';
    case METERED = 'METERED';
    case TEXT = 'TEXT';
    case JSON = 'JSON';

    public function label(): string
    {
        return match ($this) {
            self::BOOLEAN => 'ميزة تفعيل أو تعطيل',
            self::RESOURCE_LIMIT => 'حد موارد',
            self::METERED => 'استهلاك مقاس',
            self::TEXT => 'قيمة نصية',
            self::JSON => 'إعدادات مركبة',
        };
    }
}