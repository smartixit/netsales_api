<?php

namespace App\Enums;

enum BillingPlanVersionStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case ARCHIVED = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::PUBLISHED => 'منشورة',
            self::ARCHIVED => 'مؤرشفة',
        };
    }
}