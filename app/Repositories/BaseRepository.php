<?php

namespace App\Repositories;

use App\Interfaces\BaseRepositoryInterface;
use App\Services\SystemLoggerService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseRepository implements BaseRepositoryInterface
{
    /**
     * @var Model
     */
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function all(array $columns = ['*'])
    {
        return $this->model->all($columns);
    }

    public function getAll(array $columns = ['*'])
    {
        return $this->model->all($columns);
    }

    public function getAllPaginated(int $perPage = 15)
    {
        $perPage = min(max(1, $perPage), 50);
        $keyName = $this->model->getKeyName();

        return $this->model->orderBy($keyName, 'desc')->cursorPaginate($perPage);
    }

    public function findById($id, array $columns = ['*'], bool $lock = false)
    {
        $query = $this->model->newQuery();

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id, $columns);
    }

    public function findByIdForUpdate($id, array $columns = ['*'])
    {
        return $this->findById($id, $columns, true);
    }

    /**
     * Whether database mutations in this repository should be logged.
     */
    protected bool $enableLogging = true;

    public function create(array $data)
    {
        $record = $this->model->create($data);
        if ($record) {
            $this->recordAddition($record);
        }

        return $record;
    }

    public function update($id, array $data)
    {
        $record = $this->findByIdForUpdate($id);
        $oldData = $record->toArray();
        $record->update($data);

        $this->recordModification($record->fresh(), $oldData, $record->fresh()->toArray());

        return $record;
    }

    public function patch($id, array $data)
    {
        $record = $this->findByIdForUpdate($id);
        $oldData = $record->toArray();

        $record->update(array_intersect_key($data, array_flip($record->getFillable())));

        $this->recordModification($record->fresh(), $oldData, $record->fresh()->toArray());

        return $record;
    }

    public function delete($id)
    {
        $record = $this->findByIdForUpdate($id);
        $oldData = $record->toArray();
        $deleted = $record->delete();

        if ($deleted) {
            $this->recordDeletion($record, $oldData);
        }

        return $deleted;
    }

    public function bulkDelete(array $ids)
    {
        if (empty($ids)) {
            return 0;
        }

        // Lock targeted rows before performing deletion
        $this->model->whereIn($this->model->getKeyName(), $ids)->lockForUpdate()->get();

        $count = $this->model->whereIn($this->model->getKeyName(), $ids)->delete();

        if ($count > 0) {
            $this->recordBulkDeletion($ids);
        }

        return $count;
    }

    public function restore($id)
    {
        if (! $this->usesSoftDeletes()) {
            return false;
        }

        $record = $this->model->newQuery()->onlyTrashed()->lockForUpdate()->findOrFail($id);

        $restored = (bool) $record->restore();

        if ($restored) {
            $this->recordRestore($record);
        }

        return $restored;
    }

    public function forceDelete($id)
    {
        $query = $this->usesSoftDeletes()
            ? $this->model->newQuery()->withTrashed()
            : $this->model->newQuery();

        $record = $query->lockForUpdate()->findOrFail($id);
        $oldData = $record->toArray();
        $deleted = $this->usesSoftDeletes() ? $record->forceDelete() : $record->delete();

        if ($deleted) {
            $this->recordForceDeletion($record, $oldData);
        }

        return (bool) $deleted;
    }

    public function bulkRestore(array $ids)
    {
        if (! $this->usesSoftDeletes() || empty($ids)) {
            return 0;
        }

        $keyName = $this->model->getKeyName();

        // Lock targeted rows before restoring
        $this->model->newQuery()->onlyTrashed()->whereIn($keyName, $ids)->lockForUpdate()->get();

        $count = (int) $this->model->newQuery()->onlyTrashed()->whereIn($keyName, $ids)->restore();

        if ($count > 0) {
            $this->recordBulkRestore($ids);
        }

        return $count;
    }

    public function bulkForceDelete(array $ids)
    {
        if (empty($ids)) {
            return 0;
        }

        $keyName = $this->model->getKeyName();
        $query = $this->usesSoftDeletes()
            ? $this->model->newQuery()->withTrashed()
            : $this->model->newQuery();

        // Lock targeted rows before force deleting
        $query->whereIn($keyName, $ids)->lockForUpdate()->get();

        $count = $this->usesSoftDeletes()
            ? (int) $this->model->newQuery()->withTrashed()->whereIn($keyName, $ids)->forceDelete()
            : (int) $this->model->newQuery()->whereIn($keyName, $ids)->delete();

        if ($count > 0) {
            $this->recordBulkForceDelete($ids);
        }

        return $count;
    }

    public function findTrashedById($id, array $columns = ['*'], bool $lock = false)
    {
        if (! $this->usesSoftDeletes()) {
            return null;
        }

        $query = $this->model->newQuery()->onlyTrashed();

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->find($id, $columns);
    }

    public function getTrashed(int $perPage = 15)
    {
        if (! $this->usesSoftDeletes()) {
            return collect();
        }

        $perPage = min(max(1, $perPage), 50);
        $keyName = $this->model->getKeyName();

        return $this->model->newQuery()->onlyTrashed()->orderBy($keyName, 'desc')->cursorPaginate($perPage);
    }

    /**
     * Get filtered records with relations and cursor pagination.
     */
    public function getFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        $query = $this->buildBaseFilteredQuery($filters, $relations, false);

        return $this->paginateOrAll($query, $filters);
    }

    /**
     * Get filtered soft-deleted records with relations and cursor pagination.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = []): Collection|CursorPaginator
    {
        if (! $this->usesSoftDeletes()) {
            return collect();
        }

        $query = $this->buildBaseFilteredQuery($filters, $relations, true);

        return $this->paginateOrAll($query, $filters);
    }

    /**
     * Build the query for getFiltered and getTrashedFiltered.
     */
    protected function buildBaseFilteredQuery(array $filters = [], array $relations = [], bool $onlyTrashed = false): Builder
    {
        $query = $onlyTrashed ? $this->model->newQuery()->onlyTrashed() : $this->model->newQuery();

        $with = ! empty($relations) ? $relations : ($filters['with'] ?? $filters['relations'] ?? []);
        if (! empty($with)) {
            $query->with((array) $with);
        }

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Hook to apply filters to the query. Child repositories can override or extend this method.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search']) && method_exists($this->model, 'scopeSearch')) {
            $query->search(trim((string) $filters['search']));
        }

        if (isset($filters['status']) && method_exists($this->model, 'scopeStatus')) {
            $query->status($filters['status']);
        } elseif (isset($filters['active']) && method_exists($this->model, 'scopeActive') && $filters['active']) {
            $query->active();
        }

        if (! empty($filters['network_id']) && method_exists($this->model, 'scopeForNetwork')) {
            $query->forNetwork((int) $filters['network_id']);
        }

        if (! empty($filters['pos_id']) && method_exists($this->model, 'scopeForPos')) {
            $query->forPos((int) $filters['pos_id']);
        }

        if (! empty($filters['user_id']) && method_exists($this->model, 'scopeForUser')) {
            $query->forUser((int) $filters['user_id']);
        }
    }

    /**
     * Paginate using cursor or fetch all records.
     */
    protected function paginateOrAll(Builder $query, array $filters, ?string $orderColumn = null): Collection|CursorPaginator
    {
        $keyName = $orderColumn ?? $this->model->getKeyName();

        if (! empty($filters['all'])) {
            return $query->orderBy($keyName, 'desc')->get();
        }

        $perPage = min(max(1, (int) ($filters['per_page'] ?? 15)), 100);

        return $query->orderBy($keyName, 'desc')->cursorPaginate($perPage);
    }

    public function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model));
    }

    /**
     * Logging hooks that can be overridden or disabled in subclasses.
     */
    protected function recordAddition(Model|array $record): void
    {
        if ($this->enableLogging && class_exists(SystemLoggerService::class)) {
            if ($record instanceof Model) {
                SystemLoggerService::logModel($record, 'create', null, $record->toArray());
            } else {
                SystemLoggerService::logAddition($record);
            }
        }
    }

    protected function recordModification(Model|array $record, array $oldData = [], array $newData = []): void
    {
        if ($this->enableLogging && class_exists(SystemLoggerService::class)) {
            if ($record instanceof Model) {
                SystemLoggerService::logModel($record, 'update', $oldData, $newData);
            } else {
                SystemLoggerService::logModification($oldData, $newData);
            }
        }
    }

    protected function recordDeletion(Model|array $record, array $oldData = []): void
    {
        if ($this->enableLogging && class_exists(SystemLoggerService::class)) {
            if ($record instanceof Model) {
                SystemLoggerService::logModel($record, 'delete', $oldData, null);
            } else {
                SystemLoggerService::logDeletion($oldData ?: $record);
            }
        }
    }

    protected function recordRestore(Model $record): void
    {
        if ($this->enableLogging && class_exists(SystemLoggerService::class)) {
            SystemLoggerService::logModel($record, 'restore', null, $record->toArray());
        }
    }

    protected function recordForceDeletion(Model|array $record, array $oldData = []): void
    {
        if ($this->enableLogging && class_exists(SystemLoggerService::class)) {
            if ($record instanceof Model) {
                SystemLoggerService::logModel($record, 'force_delete', $oldData, null);
            } else {
                SystemLoggerService::logDeletion($oldData ?: $record);
            }
        }
    }

    protected function recordBulkDeletion(array $ids): void
    {
        if ($this->enableLogging && class_exists(SystemLoggerService::class)) {
            $entityType = class_basename($this->model);
            SystemLoggerService::log(
                module: strtolower($entityType),
                action: 'bulk_delete',
                entityType: $entityType,
                oldData: ['ids' => $ids],
            );
        }
    }

    protected function recordBulkRestore(array $ids): void
    {
        if ($this->enableLogging && class_exists(SystemLoggerService::class)) {
            $entityType = class_basename($this->model);
            SystemLoggerService::log(
                module: strtolower($entityType),
                action: 'bulk_restore',
                entityType: $entityType,
                newData: ['ids' => $ids],
            );
        }
    }

    protected function recordBulkForceDelete(array $ids): void
    {
        if ($this->enableLogging && class_exists(SystemLoggerService::class)) {
            $entityType = class_basename($this->model);
            SystemLoggerService::log(
                module: strtolower($entityType),
                action: 'bulk_force_delete',
                entityType: $entityType,
                oldData: ['ids' => $ids],
            );
        }
    }

    public function getModel()
    {
        return $this->model;
    }
}
