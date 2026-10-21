<?php

namespace App\Repositories;

use App\Interfaces\SystemLogRepositoryInterface;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * مستودع إدارة سجلات تدقيق النظام (System Log Repository).
 *
 * @property SystemLog $model
 */
class SystemLogRepository extends BaseRepository implements SystemLogRepositoryInterface
{
    public function __construct(SystemLog $model)
    {
        parent::__construct($model);
        // تعطيل التسجيل التلقائي الداخلي لمنع التكرار والحلقات المغلقة على جدول السجلات
        $this->enableLogging = false;
    }

    /**
     * جلب قائمة سجلات تدقيق النظام مع الفلترة والترقيم بالمؤشر.
     */
    public function getFiltered(array $filters = [], array $relations = ['user']): Collection|CursorPaginator
    {
        $query = $this->model->newQuery();

        if (! empty($relations)) {
            $query->with($relations);
        }

        if (! empty($filters['user_id'])) {
            $query->forUser((int) $filters['user_id']);
        }

        if (! empty($filters['module'])) {
            $query->forModule((string) $filters['module']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', (string) $filters['action']);
        }

        if (! empty($filters['entity_type']) && ! empty($filters['entity_id'])) {
            $query->forEntity((string) $filters['entity_type'], (int) $filters['entity_id']);
        } else {
            if (! empty($filters['entity_type'])) {
                $query->where('entity_type', (string) $filters['entity_type']);
            }

            if (! empty($filters['entity_id'])) {
                $query->where('entity_id', (int) $filters['entity_id']);
            }
        }

        if (! empty($filters['ip_address'])) {
            $query->where('ip_address', (string) $filters['ip_address']);
        }

        if (! empty($filters['from_date'])) {
            $query->where('log_date', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }

        if (! empty($filters['to_date'])) {
            $query->where('log_date', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('module', 'like', "%{$term}%")
                    ->orWhere('action', 'like', "%{$term}%")
                    ->orWhere('entity_type', 'like', "%{$term}%")
                    ->orWhere('ip_address', 'like', "%{$term}%")
                    ->orWhere('user_agent', 'like', "%{$term}%");
            });
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 100);

        if (! empty($filters['all'])) {
            return $query->orderBy('log_id', 'desc')->get();
        }

        return $query->orderBy('log_id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * جلب سجلات التدقيق لكيان محدد.
     */
    public function getByEntity(string $entityType, int $entityId, array $relations = ['user']): Collection
    {
        $query = $this->model->newQuery()
            ->forEntity($entityType, $entityId)
            ->orderBy('log_id', 'desc');

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->get();
    }

    /**
     * جلب سجلات تدقيق مستخدم محدد مع الفلترة والترقيم.
     */
    public function getByUser(int $userId, array $filters = [], array $relations = ['user']): Collection|CursorPaginator
    {
        $filters['user_id'] = $userId;

        return $this->getFiltered($filters, $relations);
    }

    /**
     * جلب قائمة أسماء الوحدات الفريدة المسجلة.
     */
    public function getDistinctModules(): array
    {
        return $this->model->newQuery()
            ->select('module')
            ->distinct()
            ->pluck('module')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * جلب قائمة الإجراءات الفريدة المسجلة.
     */
    public function getDistinctActions(): array
    {
        return $this->model->newQuery()
            ->select('action')
            ->distinct()
            ->pluck('action')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * إحصائيات النشاط العام وسجلات التدقيق.
     */
    public function getStats(array $filters = []): array
    {
        $query = $this->model->newQuery();

        if (! empty($filters['from_date'])) {
            $query->where('log_date', '>=', Carbon::parse($filters['from_date'])->startOfDay());
        }

        if (! empty($filters['to_date'])) {
            $query->where('log_date', '<=', Carbon::parse($filters['to_date'])->endOfDay());
        }

        $totalLogs = (clone $query)->count();

        $todayLogs = (clone $query)
            ->where('log_date', '>=', Carbon::today('Asia/Aden'))
            ->count();

        $moduleStats = (clone $query)
            ->select('module', DB::raw('count(*) as total'))
            ->groupBy('module')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'module')
            ->all();

        $actionStats = (clone $query)
            ->select('action', DB::raw('count(*) as total'))
            ->groupBy('action')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'action')
            ->all();

        return [
            'total_logs' => $totalLogs,
            'today_logs' => $todayLogs,
            'modules_count' => count($moduleStats),
            'logs_by_module' => $moduleStats,
            'logs_by_action' => $actionStats,
        ];
    }

    /**
     * حذف السجلات القديمة قبل تاريخ محدد
     */
    public function pruneOlderThan(\DateTimeInterface $cutoff): int
    {
        return $this->model->newQuery()->where('log_date', '<', $cutoff)->delete();
    }
}

