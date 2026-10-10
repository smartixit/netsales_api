<?php

namespace App\DTOs;

use Illuminate\Http\Request;

readonly class FilterDTO extends BaseDTO
{
    public function __construct(
        public int $perPage = 15,
        public ?string $search = null,
        public ?string $status = null,
        public ?int $storeId = null,
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public array $extra = []
    ) {}

    public static function fromRequest(Request $request, int $defaultPerPage = 15): static
    {
        return new static(
            perPage: min(max(1, $request->integer('per_page', $defaultPerPage)), 50),
            search: $request->filled('search') ? trim((string) $request->input('search')) : null,
            status: $request->filled('status') ? trim((string) $request->input('status')) : null,
            storeId: $request->filled('store_id') ? (int) $request->input('store_id') : null,
            fromDate: $request->input('from_date'),
            toDate: $request->input('to_date'),
            extra: $request->except(['per_page', 'page', 'search', 'status', 'store_id', 'from_date', 'to_date'])
        );
    }

    public static function fromArray(array $data, int $defaultPerPage = 15): static
    {
        return new static(
            perPage: min(max(1, (int) ($data['per_page'] ?? $defaultPerPage)), 50),
            search: !empty($data['search']) ? trim((string) $data['search']) : null,
            status: !empty($data['status']) ? trim((string) $data['status']) : null,
            storeId: !empty($data['store_id']) ? (int) $data['store_id'] : null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
            extra: $data
        );
    }
}
