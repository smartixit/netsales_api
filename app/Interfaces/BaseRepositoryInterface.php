<?php

namespace App\Interfaces;

interface BaseRepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(array $columns = ['*']);

    /**
     * Get all records.
     */
    public function getAll(array $columns = ['*']);

    /**
     * Get paginated records.
     */
    public function getAllPaginated(int $perPage = 15);

    /**
     * Get filtered records with relations and cursor pagination.
     */
    public function getFiltered(array $filters = [], array $relations = []);

    /**
     * Find a record by ID.
     *
     * @param  int|string  $id
     * @param  array  $columns
     * @param  bool  $lock
     */
    public function findById($id, array $columns = ['*'], bool $lock = false);

    /**
     * Find a record by ID with pessimistic lock (lockForUpdate).
     *
     * @param  int|string  $id
     * @param  array  $columns
     */
    public function findByIdForUpdate($id, array $columns = ['*']);

    /**
     * Create a new record.
     */
    public function create(array $data);

    /**
     * Update an entire record (PUT logic).
     *
     * @param  int|string  $id
     */
    public function update($id, array $data);

    /**
     * Partially update a record (PATCH logic).
     *
     * @param  int|string  $id
     */
    public function patch($id, array $data);

    /**
     * Delete a specific record.
     *
     * @param  int|string  $id
     */
    public function delete($id);

    /**
     * Delete multiple records at once (soft delete if supported).
     */
    public function bulkDelete(array $ids);

    /**
     * Restore a soft-deleted record by ID.
     *
     * @param  int|string  $id
     */
    public function restore($id);

    /**
     * Permanently delete a record by ID (force delete).
     *
     * @param  int|string  $id
     */
    public function forceDelete($id);

    /**
     * Restore multiple soft-deleted records at once.
     */
    public function bulkRestore(array $ids);

    /**
     * Permanently delete multiple records at once.
     */
    public function bulkForceDelete(array $ids);

    /**
     * Find a trashed record by ID.
     *
     * @param  int|string  $id
     * @param  array  $columns
     * @param  bool  $lock
     */
    public function findTrashedById($id, array $columns = ['*'], bool $lock = false);

    /**
     * Get soft-deleted records with cursor pagination.
     */
    public function getTrashed(int $perPage = 15);

    /**
     * Get filtered soft-deleted records with relations and cursor pagination.
     */
    public function getTrashedFiltered(array $filters = [], array $relations = []);

    /**
     * Determine if the underlying model uses SoftDeletes.
     */
    public function usesSoftDeletes(): bool;

    /**
     * Get the model instance.
     */
    public function getModel();
}
