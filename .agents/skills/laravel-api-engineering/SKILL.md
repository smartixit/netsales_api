---
name: laravel-api-engineering
description: >-
  Standardized procedure for implementing Laravel API endpoints, FormRequests, domain services,
  API Resources, and Eloquent models in NetSales API. Enforces service-oriented architecture,
  strict FormRequest validation, backed Enums, database transactions, and Pint code formatting.
---

# Skill: NetSales Laravel API Engineering

## Purpose
Guides the design, implementation, and testing of backend API endpoints in the `netsales_api` codebase, maintaining architectural separation and financial integrity.

---

## Engineering Rules & Invariants

1. **Layer Responsibilities:**
   - **FormRequest (`app/Http/Requests/API/V1/`):** Sole authority for request parameter validation and initial user authorization. Validate backed enums with `Rule::enum(MyEnum::class)`.
   - **Controller (`app/Http/Controllers/API/V1/`):** Thin HTTP coordinators. Validate via FormRequest, delegate immediately to a domain service, and return an API Resource.
   - **Domain Service (`app/Services/`):** Encapsulate all business logic, discount rules, state machines, and calculations. Wrap financial mutations in `DB::transaction(...)` with pessimistic row locking (`lockForUpdate()`).
   - **Repository (`app/Repositories/`):** Encapsulate complex ledger and wallet queries where established (e.g. `PosWalletLogRepository`).
   - **API Resource (`app/Http/Resources/API/V1/`):** Sole authority for JSON wire contract. Expose raw typed values and localized `*_label` fields.
2. **Domain-Aware Enums:**
   - Re-use existing backed PHP Enums (`app/Enums/`).
   - Cast Eloquent model attributes to their respective Enum class via model `casts()` method.
3. **Tenant Context Authorization:**
   - Check authorization using `User::hasPermission($key, $tenantId)` for the specific target tenant context.
4. **Code Formatting & Verification:**
   - Run `vendor/bin/pint --format agent` on all modified PHP files before finalizing.
   - Run affected tests with `php artisan test --compact` or `vendor/bin/phpunit`.
