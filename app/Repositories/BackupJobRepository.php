<?php

namespace App\Repositories;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Interfaces\BackupJobRepositoryInterface;
use App\Models\BackupJob;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * مستودع إدارة مهام النسخ الاحتياطي (Backup Job Repository).
 *
 * @property BackupJob $model
 */
class BackupJobRepository extends BaseRepository implements BackupJobRepositoryInterface
{
    public function __construct(BackupJob $model)
    {
        parent::__construct($model);
    }

    /**
     * جلب قائمة مهام النسخ الاحتياطي مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $defaultRelations = ['creator'];
        $withRelations = ! empty($relations) ? $relations : $defaultRelations;
        $query = $this->model->newQuery()->with($withRelations);

        if (! empty($filters['status'])) {
            $query->status($filters['status']);
        }

        if (! empty($filters['backup_type'])) {
            $query->where('backup_type', (string) $filters['backup_type']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', (int) $filters['created_by']);
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 50);

        if (! empty($filters['all'])) {
            return $query->orderBy('backup_job_id', 'desc')->get();
        }

        return $query->orderBy('backup_job_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * البحث عن وظيفة نسخ احتياطي بواسطة UUID
     */
    public function findByUuid(string $uuid): ?BackupJob
    {
        /** @var BackupJob|null $job */
        $job = $this->model->newQuery()->where('job_uuid', $uuid)->first();

        return $job;
    }

    /**
     * جلب أحدث مهمة نسخ احتياطي ناجحة
     */
    public function getLatestSuccessful(?string $type = null): ?BackupJob
    {
        $query = $this->model->newQuery()->successful();

        if ($type) {
            $query->where('backup_type', $type);
        }

        /** @var BackupJob|null $job */
        $job = $query->orderBy('finished_at', 'desc')->first();

        return $job;
    }

    /**
     * التحقق من وجود مهمة نسخ احتياطي نشطة حالياً
     */
    public function hasActiveJob(): bool
    {
        return $this->model->newQuery()->active()->exists();
    }

    /**
     * إنهاء المهام المعلقة التي تجاوزت مهلة التشغيل وتحديث حالتها إلى فشل
     */
    public function markStaleJobsFailed(\DateTimeInterface $threshold): int
    {
        return $this->model->newQuery()
            ->stale($threshold)
            ->update([
                'status' => BackupStatus::FAILED->value,
                'finished_at' => now('Asia/Aden'),
                'error_message' => 'انقطع تنفيذ النسخة قبل اكتمالها (تجاوز مهلة التشغيل).',
            ]);
    }

    /**
     * التحقق من وجود نسخة احتياطية تلقائية ناجحة في تاريخ محدد
     */
    public function hasSuccessfulAutoBackupOnDate(string $dateStr): bool
    {
        return $this->model->newQuery()
            ->where('backup_type', BackupType::AUTO->value)
            ->successful()
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('finished_at', $dateStr)
                    ->orWhereDate('created_at', $dateStr);
            })
            ->exists();
    }
}

