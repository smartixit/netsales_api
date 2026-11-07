---
trigger: always_on
description: Laravel 12 backend architecture, service boundaries, FormRequest validation, and Eloquent resources.
---

# Rule: Laravel Backend Architecture and Service Standards

## 1. Architectural Layers & Service Boundaries
- **Controllers:** Thin HTTP coordinators. Responsible for routing, invoking FormRequests, delegating to domain services, and returning Eloquent API Resources.
- **FormRequests:** Sole authority for HTTP input validation, type coercion, and initial request authorization.
- **Services:** Dedicated business domain orchestrators. Encapsulate business logic, calculations, and transactional operations. Do not duplicate service logic across controllers.
- **Repositories:** Responsible for low-level data access and persistence queries where established (e.g. `PosWalletLogRepository`, `VoucherPackageRepository`).
- **API Resources:** The sole authority for JSON serialization and wire contract mapping. Must expose exact typed fields and translated `*_label` fields.

## 2. Cursor Pagination Standard for Growing Collections
- **Cursor Pagination as Default:** For unbounded or potentially large incremental collections, Laravel `cursorPaginate()` via `BaseRepository.paginateOrAll()` is the standard pagination strategy.
- **Pre-Pagination Scoping:** Authorization, active tenant scoping, search, and filters must be applied BEFORE cursor pagination.
- **Deterministic Ordering:** Cursors require deterministic order with a unique tie-breaker (e.g. `orderBy('created_at', 'desc')->orderBy('id', 'desc')`) to prevent skipped or duplicate records.
- **Allowlist Filtering:** Validate and constrain all client-provided filter/search parameters. Never allow query parameters to bypass tenant isolation boundaries.
- **Envelope Contracts:** `ApiResponseTrait` serializes cursors to top-level `next_cursor` and `prev_cursor` fields.

## 3. Domain-Aware Enum Usage
- Always use backed PHP Enums (`app/Enums/`).
- Enums must implement `label(): string` returning authoritative Arabic text.
- Validate incoming enum requests using `Rule::enum(MyEnum::class)`.
- Cast Eloquent model attributes to their respective Enum class via model `casts()` method.
- API Resources returning enum attributes must expose the raw backed value and companion translated `*_label` (e.g. `'status' => $this->status?->value, 'status_label' => $this->status?->label()`).
- Never introduce arbitrary magic strings for fields that map to backed Enums.

## 4. Formatting, Analysis, and Testing Standards
- **Pint:** Run `vendor/bin/pint --format agent` to format PHP files before finalizing changes.
- **PHPUnit:** Tests must use `php artisan test --compact` or `vendor/bin/phpunit`. Run the narrowest set covering the changed behavior.
- Every financial calculation, wallet mutation, cursor pagination endpoint, and authorization policy modification requires dedicated feature tests.
