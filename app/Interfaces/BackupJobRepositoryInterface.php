<?php

namespace App\Interfaces;

use App\Models\BackupJob;

interface BackupJobRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * البحث عن وظيفة نسخ احتياطي بواسطة UUID
     */
    public function findByUuid(string $uuid): ?BackupJob;

    /**
     * جلب أحدث مهمة نسخ احتياطي ناجحة
     */
    public function getLatestSuccessful(?string $type = null): ?BackupJob;
}
