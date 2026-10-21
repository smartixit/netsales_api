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

    /**
     * التحقق من وجود مهمة نسخ احتياطي نشطة حالياً
     */
    public function hasActiveJob(): bool;

    /**
     * إنهاء المهام المعلقة التي تجاوزت مهلة التشغيل وتحديث حالتها إلى فشل
     */
    public function markStaleJobsFailed(\DateTimeInterface $threshold): int;

    /**
     * التحقق من وجود نسخة احتياطية تلقائية ناجحة في تاريخ محدد
     */
    public function hasSuccessfulAutoBackupOnDate(string $dateStr): bool;
}

