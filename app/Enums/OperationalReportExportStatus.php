<?php

namespace App\Enums;

enum OperationalReportExportStatus: string
{
    case QUEUED = 'QUEUED';
    case PROCESSING = 'PROCESSING';
    case READY = 'READY';
    case FAILED = 'FAILED';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'قيد الانتظار',
            self::PROCESSING => 'قيد التجهيز',
            self::READY => 'جاهز للتنزيل',
            self::FAILED => 'فشل التجهيز',
            self::EXPIRED => 'انتهت صلاحية الملف',
        };
    }
}
