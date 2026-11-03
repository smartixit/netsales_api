---
trigger: always_on
description: Laravel 12 backend architecture, service boundaries, FormRequest validation, and Eloquent resources.
---

# Rule: Laravel Backend Architecture and Service Standards

## 1. Architectural Layers & Service Boundaries
- **Controllers:** Thin HTTP coordinators. Responsible for routing, invoking FormRequests, delegating to domain services, and returning Eloquent API Resources.
- **FormRequests:** Sole authority for HTTP input validation, type coercion, and initial request authorization.
- **Services:** Dedicated business domain orchestrators. Encapsulate business logic, calculations, and transactional operations. Do not duplicate service logic across controllers.
- **Repositories:** Responsible for low-level data access and persistence queries where established (e.g. `PosWalletLogRepository`).
- **API Resources:** The sole authority for JSON serialization and wire contract mapping. Must expose exact typed fields and translated `*_label` fields.

## 2. Domain-Aware Enum Usage
- Always use backed PHP Enums (`app/Enums/`).
- Enums must implement `label(): string` returning authoritative Arabic text.
- Validate incoming enum requests using `Rule::enum(MyEnum::class)`.
- Cast Eloquent model attributes to their respective Enum class via model `casts()` method.
- API Resources returning enum attributes must expose the raw backed value and companion translated `*_label` (e.g. `'status' => $this->status?->value, 'status_label' => $this->status?->label()`).
- Never introduce arbitrary magic strings for fields that map to backed Enums.

## 3. Formatting, Analysis, and Testing Standards
- **Pint:** Run `vendor/bin/pint --format agent` to format PHP files before finalizing changes.
- **PHPUnit:** Tests must use `php artisan test --compact` or `vendor/bin/phpunit`. Run the narrowest set covering the changed behavior.
- Every financial calculation, wallet mutation, and authorization policy modification requires dedicated feature tests.
