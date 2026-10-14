<?php

namespace App\Repositories;

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
            $query->where('status', (string) $filters['status']);
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
        $query = $this->model->newQuery()->where('status', BackupJob::STATUS_SUCCESS);

        if ($type) {
            $query->where('backup_type', $type);
        }

        /** @var BackupJob|null $job */
        $job = $query->orderBy('finished_at', 'desc')->first();

        return $job;
    }
}
