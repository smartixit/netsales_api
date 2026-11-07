---
name: laravel-api-engineering
description: >-
  Standardized procedure for implementing Laravel API endpoints, FormRequests, domain services,
  API Resources, and Eloquent models in NetSales API. Enforces service-oriented architecture,
  strict FormRequest validation, backed Enums, database transactions, and Pint code formatting.
---

# Skill: NetSales Laravel API Engineering

## Purpose
Guides the design, implementation, and testing of backend API endpoints in the `netsales_api` codebase, maintaining architectural separation, cursor pagination standards, and financial integrity.

---

## Engineering Rules & Invariants

1. **Layer Responsibilities:**
   - **FormRequest (`app/Http/Requests/API/V1/`):** Sole authority for request parameter validation and initial user authorization. Validate backed enums with `Rule::enum(MyEnum::class)`. Validate and constrain search/filter parameters against strict allowlists.
   - **Controller (`app/Http/Controllers/API/V1/`):** Thin HTTP coordinators. Validate via FormRequest, delegate immediately to a domain service or repository, and return an API Resource.
   - **Domain Service (`app/Services/`):** Encapsulate all business logic, discount rules, state machines, and calculations. Wrap financial mutations in `DB::transaction(...)` with pessimistic row locking (`lockForUpdate()`).
   - **Repository (`app/Repositories/`):** Encapsulate complex ledger, wallet, and collection queries. Apply tenant scoping and authorization before calling `BaseRepository.paginateOrAll()`.
   - **API Resource (`app/Http/Resources/API/V1/`):** Sole authority for JSON wire contract. Expose raw typed values and localized `*_label` fields.
2. **Cursor Pagination Standard for Growing Collections:**
   - Use `cursorPaginate()` via `BaseRepository.paginateOrAll()` as the default pagination strategy for unbounded collections.
   - Apply tenant scoping and filter queries before pagination.
   - Guarantee deterministic ordering with a unique tie-breaker (e.g. `orderBy('created_at', 'desc')->orderBy('id', 'desc')`).
   - `ApiResponseTrait` automatically formats cursors to top-level `next_cursor` and `prev_cursor` fields.
3. **Domain-Aware Enums:**
   - Re-use existing backed PHP Enums (`app/Enums/`).
   - Cast Eloquent model attributes to their respective Enum class via model `casts()` method.
4. **Tenant Context Authorization:**
   - Check authorization using `User::hasPermission($key, $tenantId)` for the specific target tenant context.
5. **Code Formatting & Verification:**
   - Run `vendor/bin/pint --format agent` on all modified PHP files before finalizing.
   - Run affected tests with `php artisan test --compact` or `vendor/bin/phpunit`.
